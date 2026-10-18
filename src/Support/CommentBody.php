<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\Alfakit\Support\Html;
use AlfaSix\AlfakitComments\CommentsPlugin;
use Illuminate\Support\Str;

/**
 * Sanitising, mention extraction and rendering of comment bodies (rich editor HTML).
 */
final class CommentBody
{
    /** A mention marker as the rich editor stores it (or as a link once rendered). */
    private const MENTION_PATTERN = '/<(span|a)\b([^>]*\bdata-type\s*=\s*["\']mention["\'][^>]*)>(.*?)<\/\1>/is';

    /**
     * Remove scripts, event handlers, unsafe URLs and unknown markup (`Html::sanitize()`),
     * and normalise mention markers to `<span data-type="mention" data-id="…" data-label="…"
     * class="alfakit-mention">@Label</span>`.
     */
    public static function sanitize(string $html): string
    {
        $html = Html::sanitize($html);

        $html = (string) preg_replace_callback(self::MENTION_PATTERN, static function (array $match): string {
            $id = self::attribute($match[2], 'data-id');

            if ($id === null || $id === '') {
                return e(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5));
            }

            $label = self::attribute($match[2], 'data-label') ?? ltrim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5), '@');

            return self::mention($id, $label);
        }, $html);

        return trim($html);
    }

    /**
     * Whether the body has visible content (text, an image or a mention).
     */
    public static function isBlank(?string $html): bool
    {
        if ($html === null) {
            return true;
        }

        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{00A0}");

        return $text === '' && ! str_contains($html, '<img');
    }

    /**
     * The ids of the mentioned users, in order of appearance, without duplicates.
     *
     * @return array<int, string>
     */
    public static function mentionedIds(string $html): array
    {
        preg_match_all(self::MENTION_PATTERN, $html, $matches, PREG_SET_ORDER);

        $ids = [];

        foreach ($matches as $match) {
            $id = self::attribute($match[2], 'data-id');

            if ($id !== null && $id !== '' && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * The body to display: mentions show the users' current names, as links when the
     * plugin has a `mentionUrl()`.
     */
    public static function render(string $html): string
    {
        $ids = self::mentionedIds($html);
        $users = $ids === [] ? collect() : Mentionables::findForDisplay($ids)->keyBy(static fn ($user): string => (string) $user->getKey());
        $plugin = CommentsPlugin::get();

        $html = (string) preg_replace_callback(self::MENTION_PATTERN, static function (array $match) use ($users, $plugin): string {
            $id = (string) self::attribute($match[2], 'data-id');
            $user = $users->get($id);
            $label = $user !== null ? Commenters::name($user) : (self::attribute($match[2], 'data-label') ?? ltrim(strip_tags($match[3]), '@'));
            $url = $user !== null ? $plugin->getMentionUrl($user) : null;

            if ($url !== null) {
                return '<a href="'.e($url).'" data-type="mention" data-id="'.e($id).'" class="alfakit-mention">@'.e($label).'</a>';
            }

            return self::mention($id, $label);
        }, $html);

        return Html::sanitize($html);
    }

    /**
     * The body as plain text (mentions as `@Name`), shortened to `$limit` characters.
     */
    public static function excerpt(string $html, int $limit = 140): string
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('/<\/(p|div|li|h[1-6]|blockquote|pre)>|<br\s*\/?>/i', ' ', $html)), ENT_QUOTES | ENT_HTML5);

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $limit);
    }

    private static function mention(string $id, string $label): string
    {
        $label = trim($label) === '' ? $id : trim($label);

        return '<span data-type="mention" data-id="'.e($id).'" data-label="'.e($label).'" class="alfakit-mention">@'.e($label).'</span>';
    }

    private static function attribute(string $attributes, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attributes, $match) !== 1) {
            return null;
        }

        $value = ($match[3] ?? '') !== '' ? $match[3] : $match[2];

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
}
