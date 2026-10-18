<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Events;

use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a user adds (`$added`) or removes a reaction.
 */
class CommentWasReacted
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly Model $reactor,
        public readonly string $reaction,
        public readonly bool $added,
    ) {}
}
