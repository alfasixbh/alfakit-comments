<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Widgets;

use AlfaSix\Alfakit\Actions\Action;
use AlfaSix\Alfakit\Actions\ActionGroup;
use AlfaSix\Alfakit\Support\Enums\Size;
use AlfaSix\Alfakit\Tables\Columns\ImageColumn;
use AlfaSix\Alfakit\Tables\Columns\Layout\Split;
use AlfaSix\Alfakit\Tables\Columns\Layout\Stack;
use AlfaSix\Alfakit\Tables\Columns\TextColumn;
use AlfaSix\Alfakit\Tables\Table;
use AlfaSix\Alfakit\Widgets\TableWidget;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Support\CommentEditor;
use AlfaSix\AlfakitComments\Support\Comments;
use AlfaSix\AlfakitComments\Support\Reactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The record's comments as a paginated, polling feed for the header or footer widgets of a
 * view or edit page. It refreshes every `CommentsPlugin::pollingInterval()`.
 *
 * ```php
 * protected function getFooterWidgets(): array
 * {
 *     return [CommentsWidget::class];
 * }
 * ```
 */
class CommentsWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        $plugin = CommentsPlugin::get();
        $perPage = $plugin->getPerPage();

        return $table
            ->heading(__('alfakit-comments::comments.label'))
            ->query(fn (): Builder => $this->getCommentsQuery())
            ->columns([
                Split::make([
                    ImageColumn::make('author_avatar')
                        ->label(__('alfakit-comments::comments.columns.avatar'))
                        ->getStateUsing(static fn (): ?string => null)
                        ->defaultImageUrl(static fn (Model $record): ?string => $record instanceof Comment ? $record->getAuthorAvatarUrl() : null)
                        ->circular()
                        ->size(32)
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('author_name')
                            ->label(__('alfakit-comments::comments.columns.author'))
                            ->getStateUsing(static fn (Model $record): string => $record instanceof Comment ? $record->getAuthorName() : '')
                            ->weight('semibold'),
                        TextColumn::make('created_at')
                            ->label(__('alfakit-comments::comments.columns.date'))
                            ->since()
                            ->dateTimeTooltip()
                            ->color('gray')
                            ->size('sm'),
                        TextColumn::make('body')
                            ->label(__('alfakit-comments::comments.columns.body'))
                            ->getStateUsing(static fn (Model $record): string => $record instanceof Comment ? $record->getRenderedBody() : '')
                            ->html()
                            ->wrap(),
                    ])->space(1),
                ]),
            ])
            ->headerActions([$this->makePostAction()])
            ->recordActions([
                ...$this->makeReactionActions(),
                ActionGroup::make([$this->makeEditAction(), $this->makeDeleteAction()])
                    ->label(__('alfakit-comments::comments.actions.more'))
                    ->icon('ellipsis')
                    ->iconButton()
                    ->size(Size::ExtraSmall)
                    ->color('gray'),
            ])
            ->paginationPageOptions([$perPage, $perPage * 2, $perPage * 5])
            ->defaultPaginationPageOption($perPage)
            ->poll($plugin->getPollingInterval())
            ->emptyStateHeading(__('alfakit-comments::comments.empty'));
    }

    /**
     * @return Builder<Comment>
     */
    protected function getCommentsQuery(): Builder
    {
        $record = $this->getRecord();

        if ($record === null || ! Comments::canView($record)) {
            $model = CommentsPlugin::get()->getCommentModel();

            return $model::query()->whereRaw('1 = 0');
        }

        return Comments::queryFor($record);
    }

    protected function makePostAction(): Action
    {
        return Action::make('postComment')
            ->label(__('alfakit-comments::comments.actions.post'))
            ->icon('message-square-plus')
            ->modalHeading(__('alfakit-comments::comments.actions.post'))
            ->modalSubmitActionLabel(__('alfakit-comments::comments.actions.post_submit'))
            ->schema([CommentEditor::make('body')])
            ->visible(fn (): bool => ($record = $this->getRecord()) !== null && Comments::can('create', $record))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.posted'))
            ->action(function (array $data): void {
                $record = $this->getRecord();

                abort_unless($record !== null && Comments::can('create', $record), 403);

                Comments::post($record, (string) ($data['body'] ?? ''), CommentsPlugin::get()->getAuthor());
            });
    }

    /**
     * @return array<int, Action>
     */
    protected function makeReactionActions(): array
    {
        $actions = [];

        foreach (CommentsPlugin::get()->getReactions() as $index => $reaction) {
            $summary = static fn (Model $record): array => $record instanceof Comment
                ? Reactions::summary($record, $reaction, CommentsPlugin::get()->getAuthor())
                : ['count' => 0, 'mine' => false, 'names' => []];

            $actions[] = Action::make("react-{$index}")
                ->label(static fn (Model $record): string => Reactions::label($reaction, $summary($record)))
                ->tooltip(static fn (Model $record): string => Reactions::tooltip($reaction, $summary($record)))
                ->size(Size::ExtraSmall)
                ->color(static fn (Model $record): string => $summary($record)['mine'] ? 'primary' : 'gray')
                ->outlined(static fn (Model $record): bool => ! $summary($record)['mine'])
                ->disabled(static fn (Model $record): bool => ! $record instanceof Comment || ! Comments::can('react', $record))
                ->action(static function (Model $record) use ($reaction): void {
                    $user = CommentsPlugin::get()->getAuthor();

                    abort_unless($record instanceof Comment && $user !== null && Comments::can('react', $record, $user), 403);

                    Comments::toggleReaction($record, $user, $reaction);
                });
        }

        return $actions;
    }

    protected function makeEditAction(): Action
    {
        return Action::make('editComment')
            ->label(__('alfakit-comments::comments.actions.edit'))
            ->icon('pencil')
            ->modalHeading(__('alfakit-comments::comments.actions.edit_heading'))
            ->modalSubmitActionLabel(__('alfakit-comments::comments.actions.save'))
            ->fillForm(static fn (Model $record): array => ['body' => (string) $record->getAttribute('body')])
            ->schema([CommentEditor::make('body')])
            ->visible(static fn (Model $record): bool => $record instanceof Comment && Comments::can('update', $record))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.updated'))
            ->action(static function (Model $record, array $data): void {
                abort_unless($record instanceof Comment && Comments::can('update', $record), 403);

                Comments::update($record, (string) ($data['body'] ?? ''));
            });
    }

    protected function makeDeleteAction(): Action
    {
        return Action::make('deleteComment')
            ->label(__('alfakit-comments::comments.actions.delete'))
            ->icon('trash-2')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('alfakit-comments::comments.actions.delete_heading'))
            ->modalDescription(__('alfakit-comments::comments.actions.delete_description'))
            ->visible(static fn (Model $record): bool => $record instanceof Comment && Comments::can('delete', $record))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.deleted'))
            ->action(static function (Model $record): void {
                abort_unless($record instanceof Comment && Comments::can('delete', $record), 403);

                Comments::delete($record);
            });
    }
}
