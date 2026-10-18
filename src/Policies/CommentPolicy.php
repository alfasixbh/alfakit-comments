<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Policies;

use AlfaSix\Alfakit\Support\Authorization;
use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may comment, edit, delete and react. Extend it and register yours with
 * `Gate::policy(Comment::class, MyCommentPolicy::class)` to change the rules.
 *
 * - `create`: users who may `view` the commented record (its policy decides);
 * - `update` / `delete`: the comment's author;
 * - `react`: users who may `view` the commented record.
 */
class CommentPolicy
{
    public function create(mixed $user, Model $commentable): bool
    {
        return $user !== null && Authorization::allows('view', $commentable);
    }

    public function update(mixed $user, Comment $comment): bool
    {
        return $user instanceof Model && $comment->isAuthoredBy($user);
    }

    public function delete(mixed $user, Comment $comment): bool
    {
        return $user instanceof Model && $comment->isAuthoredBy($user);
    }

    public function react(mixed $user, Comment $comment): bool
    {
        $commentable = $comment->commentable;

        return $user !== null && $commentable instanceof Model && Authorization::allows('view', $commentable);
    }
}
