<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments;

use AlfaSix\Alfakit\Contracts\Plugin;
use AlfaSix\Alfakit\Panel;
use AlfaSix\Alfakit\Support\Concerns\EvaluatesClosures;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Models\CommentReaction;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * Comments with mentions and reactions on any record of a panel.
 *
 * ```php
 * $panel->plugin(
 *     CommentsPlugin::make()
 *         ->mentionables(fn (?Model $tenant) => User::query()->where('is_staff', true))
 *         ->reactions(['👍', '❤️', '😄', '🎉'])
 *         ->newestFirst()
 *         ->perPage(10),
 * );
 * ```
 *
 * Every option is optional: without the plugin on a panel (or before one is resolved),
 * `CommentsPlugin::get()` returns a plugin with the defaults.
 */
class CommentsPlugin implements Plugin
{
    use EvaluatesClosures;

    public const ID = 'comments';

    public const DEFAULT_REACTIONS = ['👍', '❤️', '😄', '🎉'];

    /** @var class-string<Comment> */
    protected string $commentModel = Comment::class;

    /** @var class-string<CommentReaction> */
    protected string $reactionModel = CommentReaction::class;

    protected ?Closure $mentionablesUsing = null;

    /** @var array<int, string> */
    protected array $mentionableSearchColumns = ['name'];

    protected ?Closure $mentionUrlUsing = null;

    /** @var array<int, string>|Closure */
    protected array|Closure $reactions = self::DEFAULT_REACTIONS;

    protected bool|Closure $isNewestFirst = true;

    protected int|Closure $perPage = 10;

    protected string|Closure|null $pollingInterval = null;

    protected ?Closure $authorUsing = null;

    protected ?Closure $commentUrlUsing = null;

    protected bool|Closure $notifiesMentions = true;

    /** @var array<int, string>|Closure */
    protected array|Closure $toolbarButtons = ['bold', 'italic', 'strike', 'link', '|', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'];

    public static function make(): static
    {
        return new static;
    }

    /**
     * The plugin registered on the current panel, or a plugin with the default options when
     * the panel doesn't have it.
     */
    public static function get(): static
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();

        if ($panel !== null && $panel->hasPlugin(self::ID)) {
            $plugin = $panel->getPlugin(self::ID);

            if ($plugin instanceof static) {
                return $plugin;
            }
        }

        return static::make();
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    protected function getEvaluationIdentifier(): string
    {
        return 'plugin';
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();

        return match ($parameterName) {
            'panel' => [$panel],
            'user' => [$panel?->auth()->user()],
            'tenant' => [alfakit()->getTenant()],
            default => [],
        };
    }

    // ---------------------------------------------------------------- Models

    /**
     * Use your own comment model (a subclass of `Comment`).
     *
     * @param  class-string<Comment>  $model
     */
    public function commentModel(string $model): static
    {
        if (! is_a($model, Comment::class, true)) {
            throw new InvalidArgumentException("The comment model [{$model}] must extend [".Comment::class.'].');
        }

        $this->commentModel = $model;

        return $this;
    }

    /**
     * @return class-string<Comment>
     */
    public function getCommentModel(): string
    {
        return $this->commentModel;
    }

    /**
     * Use your own reaction model (a subclass of `CommentReaction`).
     *
     * @param  class-string<CommentReaction>  $model
     */
    public function reactionModel(string $model): static
    {
        if (! is_a($model, CommentReaction::class, true)) {
            throw new InvalidArgumentException("The reaction model [{$model}] must extend [".CommentReaction::class.'].');
        }

        $this->reactionModel = $model;

        return $this;
    }

    /**
     * @return class-string<CommentReaction>
     */
    public function getReactionModel(): string
    {
        return $this->reactionModel;
    }

    // ---------------------------------------------------------------- Mentions

    /**
     * The users that can be mentioned: `fn (?Model $tenant): Builder => User::query()`. By
     * default, the panel's users, or the members of the current tenant on tenancy panels.
     */
    public function mentionables(?Closure $callback): static
    {
        $this->mentionablesUsing = $callback;

        return $this;
    }

    public function getMentionablesUsing(): ?Closure
    {
        return $this->mentionablesUsing;
    }

    /**
     * @return Builder<Model>|null
     */
    public function getMentionablesQuery(?Model $tenant): ?Builder
    {
        if ($this->mentionablesUsing === null) {
            return null;
        }

        $query = $this->evaluate($this->mentionablesUsing, ['tenant' => $tenant], $tenant === null ? [] : [Model::class => $tenant]);

        return $query instanceof Builder ? $query : null;
    }

    /**
     * The columns the mention search looks in (`name` by default).
     *
     * @param  array<int, string>  $columns
     */
    public function mentionableSearchColumns(array $columns): static
    {
        $this->mentionableSearchColumns = array_values($columns);

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getMentionableSearchColumns(): array
    {
        return $this->mentionableSearchColumns;
    }

    /**
     * Turn rendered mentions into links: `fn (Model $user): ?string => UserResource::getUrl('view', ['record' => $user])`.
     */
    public function mentionUrl(?Closure $callback): static
    {
        $this->mentionUrlUsing = $callback;

        return $this;
    }

    public function getMentionUrl(Model $user): ?string
    {
        if ($this->mentionUrlUsing === null) {
            return null;
        }

        $url = $this->evaluate($this->mentionUrlUsing, ['user' => $user, 'record' => $user, 'mentioned' => $user], [Model::class => $user, $user::class => $user]);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Send a database notification (shown in the panel's bell) to mentioned users (default).
     */
    public function notifyMentions(bool|Closure $condition = true): static
    {
        $this->notifiesMentions = $condition;

        return $this;
    }

    public function notifiesMentions(): bool
    {
        return (bool) $this->evaluate($this->notifiesMentions);
    }

    // ---------------------------------------------------------------- Reactions

    /**
     * The reactions users can toggle on a comment; an empty list (or `false`) turns them off.
     *
     * @param  array<int, string>|Closure|false  $reactions
     */
    public function reactions(array|Closure|false $reactions): static
    {
        $this->reactions = $reactions === false ? [] : $reactions;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getReactions(): array
    {
        $reactions = $this->evaluate($this->reactions);

        return is_array($reactions)
            ? array_values(array_unique(array_filter(array_map(static fn (mixed $reaction): string => is_scalar($reaction) ? mb_substr(trim((string) $reaction), 0, 32) : '', $reactions))))
            : [];
    }

    // ---------------------------------------------------------------- Listing

    public function newestFirst(bool|Closure $condition = true): static
    {
        $this->isNewestFirst = $condition;

        return $this;
    }

    public function oldestFirst(bool|Closure $condition = true): static
    {
        $this->isNewestFirst = $condition instanceof Closure ? fn (): bool => ! $this->evaluate($condition) : ! $condition;

        return $this;
    }

    public function isNewestFirst(): bool
    {
        return (bool) $this->evaluate($this->isNewestFirst);
    }

    /**
     * How many comments are shown at first, and added by "Load more".
     */
    public function perPage(int|Closure $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return max(1, (int) $this->evaluate($this->perPage));
    }

    /**
     * Refresh the comments of `CommentsWidget` every interval (`'10s'`); null turns polling off.
     */
    public function pollingInterval(string|Closure|null $interval = '10s'): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getPollingInterval(): ?string
    {
        $interval = $this->evaluate($this->pollingInterval);

        return is_string($interval) && $interval !== '' ? $interval : null;
    }

    /**
     * The toolbar of the comment editor (RichEditor toolbar button names).
     *
     * @param  array<int, string>|Closure  $buttons
     */
    public function toolbarButtons(array|Closure $buttons): static
    {
        $this->toolbarButtons = $buttons;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getToolbarButtons(): array
    {
        $buttons = $this->evaluate($this->toolbarButtons);

        return is_array($buttons) ? array_values(array_filter($buttons, is_string(...))) : [];
    }

    // ---------------------------------------------------------------- Authors and links

    /**
     * Who writes comments and reacts: `fn (): ?Model => auth()->user()`. Defaults to the
     * panel's signed-in user.
     */
    public function authorUsing(?Closure $callback): static
    {
        $this->authorUsing = $callback;

        return $this;
    }

    public function getAuthor(): ?Model
    {
        if ($this->authorUsing !== null) {
            $author = $this->evaluate($this->authorUsing);

            return $author instanceof Model ? $author : null;
        }

        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();
        $user = $panel?->auth()->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * The link of mention notifications: `fn (Comment $comment, Model $commentable): ?string`.
     * Defaults to the view (or edit) page of the commentable's resource in the current panel.
     */
    public function commentUrlUsing(?Closure $callback): static
    {
        $this->commentUrlUsing = $callback;

        return $this;
    }

    public function getCommentUrl(Comment $comment): ?string
    {
        $commentable = $comment->commentable;

        if (! $commentable instanceof Model) {
            return null;
        }

        if ($this->commentUrlUsing !== null) {
            $url = $this->evaluate($this->commentUrlUsing, ['comment' => $comment, 'commentable' => $commentable, 'record' => $commentable], [Comment::class => $comment, $comment::class => $comment]);

            return is_string($url) && $url !== '' ? $url : null;
        }

        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();
        $resource = $panel?->getModelResource($commentable);

        if ($resource === null) {
            return null;
        }

        foreach (['view', 'edit'] as $page) {
            if ($resource::hasPage($page)) {
                try {
                    return $resource::getUrl($page, ['record' => $commentable], panel: $panel);
                } catch (Throwable) {
                    return null;
                }
            }
        }

        return null;
    }
}
