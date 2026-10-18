<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Contracts;

use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A model that can be commented on. Implemented by the `HasComments` trait.
 */
interface Commentable
{
    /**
     * @return MorphMany<Comment, Model>
     */
    public function comments(): MorphMany;

    public function comment(string $body, ?Model $author = null): Comment;
}
