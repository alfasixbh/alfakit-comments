<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\Alfakit\Notifications\Actions\Action as NotificationAction;
use AlfaSix\Alfakit\Notifications\Notification;
use AlfaSix\Alfakit\Support\Authorization;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Events\CommentWasCreated;
use AlfaSix\AlfakitComments\Events\CommentWasReacted;
use AlfaSix\AlfakitComments\Events\UserWasMentioned;
use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Posting, editing, deleting and reacting to comments, with the mention notifications and
 * events. The actions and `HasComments::comment()` go through here.
 */
final class Comments
{
    /**
     * The comments of a record, in the plugin's order.
     *
     * @return Builder<Comment>
     */
    public static function queryFor(Model $commentable, ?bool $newestFirst = null): Builder
    {
        $model = CommentsPlugin::get()->getCommentModel();
        $instance = new $model;
        $newestFirst ??= CommentsPlugin::get()->isNewestFirst();

        /** @var Builder<Comment> $query */
        $query = $model::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->with(['author', 'reactions.reactor'])
            ->orderBy($instance->qualifyColumn('created_at'), $newestFirst ? 'desc' : 'asc')
            ->orderBy($instance->getQualifiedKeyName(), $newestFirst ? 'desc' : 'asc');

        return $query;
    }

    /**
     * Find a comment of the record by key: comments of other records are never returned.
     */
    public static function findFor(Model $commentable, mixed $key): ?Comment
    {
        if (! is_scalar($key) || (string) $key === '') {
            return null;
        }

        return self::queryFor($commentable)->whereKey($key)->first();
    }

    public static function post(Model $commentable, string $body, ?Model $author): Comment
    {
        $model = CommentsPlugin::get()->getCommentModel();

        /** @var Comment $comment */
        $comment = new $model;
        $comment->setAttribute('body', $body);
        $comment->commentable()->associate($commentable);

        if ($author !== null) {
            $comment->author()->associate($author);
        }

        $comment->save();
        $comment->setRelation('commentable', $commentable);

        if ($author !== null) {
            $comment->setRelation('author', $author);
        }

        $mentioned = self::mentionedUsers($comment, []);

        event(new CommentWasCreated($comment));

        self::notifyMentioned($comment, $mentioned);

        return $comment;
    }

    /**
     * Change the body. Users mentioned for the first time are notified.
     */
    public static function update(Comment $comment, string $body): Comment
    {
        $before = $comment->getMentionedIds();

        $comment->setAttribute('body', $body);
        $comment->save();

        self::notifyMentioned($comment, self::mentionedUsers($comment, $before));

        return $comment;
    }

    public static function delete(Comment $comment): void
    {
        DB::transaction(static function () use ($comment): void {
            $comment->reactions()->delete();
            $comment->delete();
        });
    }

    /**
     * Add the reaction, or remove it when the user already reacted with it. Returns whether
     * it was added.
     */
    public static function toggleReaction(Comment $comment, Model $reactor, string $reaction): bool
    {
        $existing = $comment->reactions()
            ->where('reactor_type', $reactor->getMorphClass())
            ->where('reactor_id', $reactor->getKey())
            ->where('reaction', $reaction)
            ->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $comment->reactions()->create([
                'reactor_type' => $reactor->getMorphClass(),
                'reactor_id' => $reactor->getKey(),
                'reaction' => $reaction,
            ]);
        }

        $comment->unsetRelation('reactions');

        event(new CommentWasReacted($comment, $reactor, $reaction, $existing === null));

        return $existing === null;
    }

    /**
     * Ask the comment policy (`CommentPolicy` unless you registered another one).
     *
     * @param  'create'|'update'|'delete'|'react'|string  $ability
     */
    public static function can(string $ability, Model $target, ?Model $user = null): bool
    {
        $user ??= CommentsPlugin::get()->getAuthor();

        if ($user === null) {
            return false;
        }

        $arguments = $ability === 'create' ? [CommentsPlugin::get()->getCommentModel(), $target] : [$target];
        $policy = Gate::getPolicyFor($arguments[0]);

        if ($policy === null || ! method_exists($policy, $ability)) {
            return ! Authorization::isStrict();
        }

        return Gate::forUser($user)->check($ability, $arguments);
    }

    /**
     * Whether the user may see the record's comments: the record's `view` policy.
     */
    public static function canView(Model $commentable): bool
    {
        return CommentsPlugin::get()->getAuthor() !== null && Authorization::allows('view', $commentable);
    }

    /**
     * The mentioned users that can be mentioned, minus the author and `$except` ids.
     *
     * @param  array<int, string>  $except
     * @return Collection<int, Model>
     */
    private static function mentionedUsers(Comment $comment, array $except): Collection
    {
        $ids = array_values(array_diff($comment->getMentionedIds(), $except));

        return Mentionables::find($ids)
            ->reject(static fn (Model $user): bool => $comment->isAuthoredBy($user))
            ->values();
    }

    /**
     * @param  Collection<int, Model>  $users
     */
    private static function notifyMentioned(Comment $comment, Collection $users): void
    {
        $plugin = CommentsPlugin::get();
        $notifies = $plugin->notifiesMentions();
        $url = $users->isEmpty() || ! $notifies ? null : $plugin->getCommentUrl($comment);

        foreach ($users as $user) {
            event(new UserWasMentioned($comment, $user));

            if (! $notifies || ! $user instanceof Authenticatable || ! method_exists($user, 'notify')) {
                continue;
            }

            $notification = Notification::make()
                ->title(__('alfakit-comments::comments.notifications.mentioned.title', ['name' => $comment->getAuthorName()]))
                ->body($comment->getExcerpt())
                ->icon('at-sign');

            if ($url !== null) {
                $notification->actions([
                    NotificationAction::make('view')
                        ->label(__('alfakit-comments::comments.notifications.mentioned.view'))
                        ->url($url)
                        ->markAsRead(),
                ]);
            }

            $notification->sendToDatabase($user);
        }
    }
}
