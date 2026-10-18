<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Models\CommentReaction;
use Illuminate\Database\Eloquent\Model;

/**
 * Reaction counts of a comment, from its loaded `reactions`.
 */
final class Reactions
{
    /**
     * @return array{count: int, mine: bool, names: array<int, string>}
     */
    public static function summary(Comment $comment, string $reaction, ?Model $user): array
    {
        $reactions = $comment->reactions->filter(static fn (CommentReaction $item): bool => (string) $item->getAttribute('reaction') === $reaction);

        return [
            'count' => $reactions->count(),
            'mine' => $user !== null && $reactions->contains(static fn (CommentReaction $item): bool => (string) $item->getAttribute('reactor_type') === $user->getMorphClass()
                && (string) $item->getAttribute('reactor_id') === (string) $user->getKey()),
            'names' => $reactions->map(static fn (CommentReaction $item): string => Commenters::name($item->reactor))->take(10)->values()->all(),
        ];
    }

    /**
     * The button label: the emoji, with the count once someone reacted.
     *
     * @param  array{count: int, mine: bool, names: array<int, string>}  $summary
     */
    public static function label(string $reaction, array $summary): string
    {
        return $summary['count'] > 0 ? $reaction.' '.$summary['count'] : $reaction;
    }

    /**
     * The button tooltip: who reacted, or "React with 👍".
     *
     * @param  array{count: int, mine: bool, names: array<int, string>}  $summary
     */
    public static function tooltip(string $reaction, array $summary): string
    {
        return $summary['names'] === []
            ? (string) __('alfakit-comments::comments.reactions.react', ['reaction' => $reaction])
            : implode(', ', $summary['names']);
    }
}
