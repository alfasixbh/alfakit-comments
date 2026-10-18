<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Concerns;

use AlfaSix\AlfakitComments\Support\Commenters;

/**
 * Defaults for the `Commenter` contract: the name and avatar the panel shows for the user
 * (`HasName` / `HasAvatar`, the `name` attribute, the panel's `defaultAvatarProvider()`),
 * falling back to an initials avatar drawn on the server.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait IsCommenter
{
    public function getCommenterName(): string
    {
        return Commenters::defaultName($this);
    }

    public function getCommenterAvatarUrl(): ?string
    {
        return Commenters::defaultAvatarUrl($this);
    }
}
