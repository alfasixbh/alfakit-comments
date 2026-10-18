<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Events;

use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched once per mentioned user when a comment is posted, or when an edit adds the
 * mention. The comment's author is never notified about mentioning themselves.
 */
class UserWasMentioned
{
    use Dispatchable;

    public function __construct(
        public readonly Comment $comment,
        public readonly Model $user,
    ) {}
}
