<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Events;

use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a comment was posted (and its mentions resolved).
 */
class CommentWasCreated
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
