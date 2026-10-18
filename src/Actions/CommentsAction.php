<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Actions;

use AlfaSix\Alfakit\Actions\Action;
use AlfaSix\Alfakit\Support\Enums\Color;
use AlfaSix\AlfakitComments\Infolists\Components\CommentsEntry;
use AlfaSix\AlfakitComments\Support\Comments;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Opens the record's comments in a slide-over: a header action on view and edit pages, or
 * a record action in tables (`CommentsTableAction`). Posting, editing and reacting update
 * the slide-over in place.
 *
 * ```php
 * protected function getHeaderActions(): array
 * {
 *     return [CommentsAction::make()->perPage(20)];
 * }
 * ```
 */
class CommentsAction extends Action
{
    protected int|Closure|null $perPage = null;

    protected bool|Closure|null $isNewestFirst = null;

    protected bool|Closure $isReadOnly = false;

    protected bool|Closure $hasReactions = true;

    protected bool|Closure $hasAvatars = true;

    protected ?Closure $modifyEntryUsing = null;

    public static function make(string $name = 'comments'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('alfakit-comments::comments.label'));
        $this->icon('message-square');
        $this->color(Color::Gray);
        $this->slideOver();
        $this->modalHeading(__('alfakit-comments::comments.label'));
        $this->modalSubmitAction(false);
        $this->modalCancelActionLabel(__('alfakit-comments::comments.actions.close'));
        $this->visible(fn (): bool => ($record = $this->getRecordModel()) !== null && $record->exists && Comments::canView($record));
        $this->schema(fn (): array => [$this->makeCommentsEntry()]);
    }

    public function perPage(int|Closure|null $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function newestFirst(bool|Closure|null $condition = true): static
    {
        $this->isNewestFirst = $condition;

        return $this;
    }

    public function oldestFirst(bool $condition = true): static
    {
        return $this->newestFirst(! $condition);
    }

    public function readOnly(bool|Closure $condition = true): static
    {
        $this->isReadOnly = $condition;

        return $this;
    }

    public function disableReactions(bool|Closure $condition = true): static
    {
        $this->hasReactions = $condition instanceof Closure ? fn (): bool => ! $this->evaluate($condition) : ! $condition;

        return $this;
    }

    public function disableAvatars(bool|Closure $condition = true): static
    {
        $this->hasAvatars = $condition instanceof Closure ? fn (): bool => ! $this->evaluate($condition) : ! $condition;

        return $this;
    }

    /**
     * Configure the `CommentsEntry` in the slide-over: `fn (CommentsEntry $entry) => $entry->loadMoreLabel('Older')`.
     */
    public function modifyCommentsEntryUsing(?Closure $callback): static
    {
        $this->modifyEntryUsing = $callback;

        return $this;
    }

    protected function makeCommentsEntry(): CommentsEntry
    {
        $entry = CommentsEntry::make('comments')
            ->perPage($this->perPage)
            ->newestFirst($this->isNewestFirst)
            ->readOnly($this->isReadOnly)
            ->reactions($this->hasReactions)
            ->avatars($this->hasAvatars)
            ->commentable(fn (): ?Model => $this->getRecordModel());

        if ($this->modifyEntryUsing !== null) {
            $result = $this->evaluate($this->modifyEntryUsing, ['entry' => $entry, 'component' => $entry], [CommentsEntry::class => $entry]);

            if ($result instanceof CommentsEntry) {
                $entry = $result;
            }
        }

        return $entry;
    }
}
