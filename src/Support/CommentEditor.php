<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\Alfakit\Forms\Components\RichEditor;
use AlfaSix\Alfakit\Forms\Components\RichEditor\MentionProvider;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Rules\HasCommentContent;
use Illuminate\Database\Eloquent\Model;

/**
 * The rich editor comments are written in: the plugin's toolbar and `@` mentions of the
 * mentionable users (searched on the server, limited to the current tenant's members).
 */
final class CommentEditor
{
    public static function make(string $name = 'body'): RichEditor
    {
        $plugin = CommentsPlugin::get();

        return RichEditor::make($name)
            ->hiddenLabel()
            ->placeholder(__('alfakit-comments::comments.editor.placeholder'))
            ->required()
            ->rule(new HasCommentContent)
            ->toolbarButtons($plugin->getToolbarButtons())
            ->mentions([self::mentionProvider()]);
    }

    public static function mentionProvider(): MentionProvider
    {
        return MentionProvider::make('@')
            ->getSearchResultsUsing(static fn (string $search): array => Mentionables::search($search))
            ->getLabelsUsing(static fn (array $ids): array => Mentionables::find(array_values(array_filter($ids, is_scalar(...))))
                ->mapWithKeys(static fn (Model $user): array => [(string) $user->getKey() => Commenters::name($user)])
                ->all());
    }
}
