<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\Alfakit\AvatarProviders\InitialsAvatarProvider;
use AlfaSix\Alfakit\Contracts\HasAvatar;
use AlfaSix\Alfakit\Contracts\HasName;
use AlfaSix\AlfakitComments\Contracts\Commenter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Names and avatars of comment authors and mentioned users.
 */
final class Commenters
{
    public static function name(?Model $commenter): string
    {
        if ($commenter === null) {
            return (string) __('alfakit-comments::comments.unknown_author');
        }

        return $commenter instanceof Commenter ? $commenter->getCommenterName() : self::defaultName($commenter);
    }

    public static function avatarUrl(?Model $commenter): ?string
    {
        if ($commenter === null) {
            return null;
        }

        return $commenter instanceof Commenter ? $commenter->getCommenterAvatarUrl() : self::defaultAvatarUrl($commenter);
    }

    /**
     * The panel's name for the user (`userName()`, `HasName`, `name`, `email`).
     */
    public static function defaultName(Model $commenter): string
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();

        if ($panel !== null && $commenter instanceof Authenticatable) {
            return $panel->getUserName($commenter);
        }

        if ($commenter instanceof HasName) {
            return \AlfaSix\Alfakit\Support\Html::toText($commenter->getAlfakitName());
        }

        $name = $commenter->getAttribute('name') ?? $commenter->getAttribute('email') ?? $commenter->getKey();

        return is_scalar($name) ? (string) $name : '';
    }

    /**
     * The panel's avatar for the user (`HasAvatar`, `avatar_url`, `defaultAvatarProvider()`),
     * else initials drawn on the server.
     */
    public static function defaultAvatarUrl(Model $commenter): string
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();
        $url = null;

        if ($panel !== null && $commenter instanceof Authenticatable) {
            $url = $panel->getUserAvatarUrl($commenter);
        } elseif ($commenter instanceof HasAvatar) {
            $url = $commenter->getAlfakitAvatarUrl();
        }

        return is_string($url) && $url !== '' ? $url : (new InitialsAvatarProvider)->get($commenter);
    }
}
