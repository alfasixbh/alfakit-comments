<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Concerns;

use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Support\Comments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Makes a model commentable:
 *
 * ```php
 * class Ticket extends Model implements Commentable
 * {
 *     use HasComments;
 * }
 *
 * $ticket->comment('<p>Looks good, @Jane!</p>', $user);
 * $ticket->comments()->latest()->get();
 * ```
 *
 * @mixin Model
 */
trait HasComments
{
    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(CommentsPlugin::get()->getCommentModel(), 'commentable');
    }

    /**
     * Post a comment: the body is sanitised, mentioned users are notified and the
     * `CommentWasCreated` / `UserWasMentioned` events are dispatched. Without an author, the
     * plugin's author (the signed-in user) writes it.
     */
    public function comment(string $body, ?Model $author = null): Comment
    {
        return Comments::post($this, $body, $author ?? CommentsPlugin::get()->getAuthor());
    }
}
