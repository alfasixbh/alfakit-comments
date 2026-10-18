<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Contracts;

/**
 * A model that writes comments and can be mentioned (usually the user model). Add the
 * `IsCommenter` trait for defaults that follow the panel (`HasName`, `HasAvatar`, the
 * panel's `defaultAvatarProvider()`, then initials).
 */
interface Commenter
{
    public function getCommenterName(): string;

    public function getCommenterAvatarUrl(): ?string;
}
