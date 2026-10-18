<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Rules;

use AlfaSix\AlfakitComments\Support\CommentBody;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The comment body must contain something besides empty paragraphs.
 */
class HasCommentContent implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || CommentBody::isBlank(CommentBody::sanitize($value))) {
            $fail(__('alfakit-comments::comments.validation.blank'));
        }
    }
}
