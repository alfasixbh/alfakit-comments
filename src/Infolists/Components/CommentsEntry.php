<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Infolists\Components;

use AlfaSix\Alfakit\Actions\Action;
use AlfaSix\Alfakit\Actions\ActionGroup;
use AlfaSix\Alfakit\Infolists\Components\ImageEntry;
use AlfaSix\Alfakit\Infolists\Components\TextEntry;
use AlfaSix\Alfakit\Schemas\Components\Actions;
use AlfaSix\Alfakit\Schemas\Components\Component;
use AlfaSix\Alfakit\Schemas\Components\Flex;
use AlfaSix\Alfakit\Schemas\Components\Group;
use AlfaSix\Alfakit\Schemas\Components\Text;
use AlfaSix\Alfakit\Support\Enums\Size;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Support\CommentEditor;
use AlfaSix\AlfakitComments\Support\Comments;
use AlfaSix\AlfakitComments\Support\PageRefresh;
use AlfaSix\AlfakitComments\Support\Reactions;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The comments of the record, with a composer, reactions and per-comment actions. Built
 * from AlfaKit's own components (entries, `Actions`, `RichEditor` modals), so it renders in
 * every renderer. Use it in an infolist, a form (an edit page) or an action's modal:
 *
 * ```php
 * Section::make('Comments')->schema([
 *     CommentsEntry::make()->perPage(10)->newestFirst(),
 * ])
 * ```
 *
 * Its actions run through the field action endpoint: in a form they re-render the form in
 * place; in an infolist the page reloads.
 */
class CommentsEntry extends Group
{
    /** Rendered by the renderers' `Group`: no frontend code of its own. */
    protected ?string $type = 'Group';

    protected int|Closure|null $perPage = null;

    protected bool|Closure|null $isNewestFirst = null;

    protected bool|Closure $isReadOnly = false;

    protected bool|Closure $hasReactions = true;

    protected bool|Closure $hasAvatars = true;

    protected Model|Closure|null $commentable = null;

    protected string|Closure|null $loadMoreLabel = null;

    /** @var Collection<int, Comment>|null */
    protected ?Collection $cachedComments = null;

    protected bool $hasMoreComments = false;

    /** @var array<Component>|null */
    protected ?array $cachedComponents = null;

    final public function __construct(protected string $name = 'comments')
    {
        parent::__construct();
    }

    public static function make(mixed ...$arguments): static
    {
        return parent::make(...$arguments);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->key(str_replace(['.', '>', ':'], '-', $this->name));
        $this->columns(1);
        $this->columnSpanFull();
        $this->extraAttributes(['data-alfakit-comments' => true]);
    }

    /**
     * The composer, the comments and "Load more", built once per request (and again after
     * an action changed the comments).
     *
     * @return array<Component>
     */
    public function getChildComponents(): array
    {
        return $this->cachedComponents ??= $this->buildComponents();
    }

    // ---------------------------------------------------------------- Options

    /**
     * Comment on another record than the schema's.
     */
    public function commentable(Model|Closure|null $record): static
    {
        $this->commentable = $record;

        return $this;
    }

    public function getCommentable(): ?Model
    {
        $record = $this->evaluate($this->commentable) ?? $this->getRecord();

        return $record instanceof Model && $record->exists ? $record : null;
    }

    /**
     * How many comments are shown at first and added by "Load more" (the plugin's
     * `perPage()` by default).
     */
    public function perPage(int|Closure|null $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        $perPage = $this->evaluate($this->perPage);

        return is_int($perPage) ? max(1, $perPage) : CommentsPlugin::get()->getPerPage();
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

    public function isNewestFirst(): bool
    {
        $condition = $this->evaluate($this->isNewestFirst);

        return is_bool($condition) ? $condition : CommentsPlugin::get()->isNewestFirst();
    }

    /**
     * Show the comments without the composer and the edit, delete and react buttons.
     */
    public function readOnly(bool|Closure $condition = true): static
    {
        $this->isReadOnly = $condition;

        return $this;
    }

    public function isReadOnly(): bool
    {
        return (bool) $this->evaluate($this->isReadOnly);
    }

    public function reactions(bool|Closure $condition = true): static
    {
        $this->hasReactions = $condition;

        return $this;
    }

    public function disableReactions(): static
    {
        return $this->reactions(false);
    }

    /**
     * @return array<int, string>
     */
    public function getReactions(): array
    {
        return $this->evaluate($this->hasReactions) ? CommentsPlugin::get()->getReactions() : [];
    }

    public function avatars(bool|Closure $condition = true): static
    {
        $this->hasAvatars = $condition;

        return $this;
    }

    public function disableAvatars(): static
    {
        return $this->avatars(false);
    }

    public function hasAvatars(): bool
    {
        return (bool) $this->evaluate($this->hasAvatars);
    }

    public function loadMoreLabel(string|Closure|null $label): static
    {
        $this->loadMoreLabel = $label;

        return $this;
    }

    public function getLoadMoreLabel(): string
    {
        $label = $this->evaluate($this->loadMoreLabel);

        return is_string($label) ? $label : (string) __('alfakit-comments::comments.actions.load_more');
    }

    /**
     * Hidden without a saved record, and for users who may not `view` the record.
     */
    public function isHidden(): bool
    {
        if (parent::isHidden()) {
            return true;
        }

        $commentable = $this->getCommentable();

        return $commentable === null || ! Comments::canView($commentable);
    }

    // ---------------------------------------------------------------- Comments

    /**
     * The comments shown: the first `perPage()` (more after "Load more"), in order.
     *
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        if ($this->cachedComments !== null) {
            return $this->cachedComments;
        }

        $commentable = $this->getCommentable();

        if ($commentable === null) {
            return $this->cachedComments = collect();
        }

        $limit = $this->getVisibleLimit();
        $comments = Comments::queryFor($commentable, $this->isNewestFirst())->limit($limit + 1)->get();

        $this->hasMoreComments = $comments->count() > $limit;

        foreach ($comments as $comment) {
            $comment->setRelation('commentable', $commentable);
        }

        return $this->cachedComments = $comments->take($limit)->values()->toBase();
    }

    public function hasMoreComments(): bool
    {
        $this->getComments();

        return $this->hasMoreComments;
    }

    /**
     * How many comments are shown. "Load more" raises it for the user's session, so the
     * actions of every shown comment stay resolvable on the next requests.
     */
    public function getVisibleLimit(): int
    {
        $perPage = $this->getPerPage();
        $stored = session()->get($this->getSessionKey());

        return is_int($stored) ? max($perPage, $stored) : $perPage;
    }

    public function loadMore(): void
    {
        session()->put($this->getSessionKey(), $this->getVisibleLimit() + $this->getPerPage());
        $this->flushComments();
    }

    public function flushComments(): void
    {
        $this->cachedComments = null;
        $this->hasMoreComments = false;
        $this->cachedComponents = null;
        $this->childSchema = null;
    }

    protected function getSessionKey(): string
    {
        $commentable = $this->getCommentable();

        return 'alfakit-comments.limit.'.sha1(implode('|', [$commentable?->getMorphClass(), $commentable?->getKey(), $this->getKey()]));
    }

    // ---------------------------------------------------------------- Components

    /**
     * @return array<Component>
     */
    protected function buildComponents(): array
    {
        $commentable = $this->getCommentable();

        if ($commentable === null) {
            return [];
        }

        $key = $this->getKey();
        $components = [];

        if (! $this->isReadOnly()) {
            $components[] = Actions::make([$this->makePostAction()])->key("{$key}-composer");
        }

        $comments = $this->getComments();

        if ($comments->isEmpty()) {
            $components[] = Text::make(__('alfakit-comments::comments.empty'))->color('gray')->key("{$key}-empty");
        }

        foreach ($comments as $comment) {
            $components[] = $this->makeCommentComponent($comment);
        }

        if ($this->hasMoreComments()) {
            $components[] = Actions::make([$this->makeLoadMoreAction()])->alignCenter()->key("{$key}-more");
        }

        return $components;
    }

    protected function makeCommentComponent(Comment $comment): Component
    {
        $prefix = $this->getKey().'-'.$comment->getKey();
        $name = $comment->getAuthorName();
        $created = $comment->getAttribute('created_at');

        $header = Flex::make([
            Text::make($name)->weight('semibold')->key("{$prefix}-author")->grow(false),
            TextEntry::make("{$prefix}-time")
                ->hiddenLabel()
                ->state($created)
                ->since()
                ->dateTimeTooltip()
                ->color('gray')
                ->size('sm')
                ->grow(false),
            Text::make(__('alfakit-comments::comments.edited'))
                ->color('gray')
                ->size('sm')
                ->key("{$prefix}-edited")
                ->visible($comment->wasEdited())
                ->grow(false),
        ])->verticallyAlignCenter()->key("{$prefix}-header");

        $content = Group::make([
            $header,
            TextEntry::make("{$prefix}-body")
                ->hiddenLabel()
                ->state($comment->getRenderedBody())
                ->html()
                ->prose()
                // A comment is written in its author's language, not the panel's: an English
                // comment in an Arabic panel keeps its punctuation where it belongs.
                ->extraAttributes(['dir' => 'auto']),
            Actions::make($this->makeCommentActions($comment))->key("{$prefix}-actions"),
        ])->dense()->key("{$prefix}-content");

        $avatar = $this->hasAvatars() ? $comment->getAuthorAvatarUrl() : null;

        return Flex::make(array_values(array_filter([
            $avatar === null ? null : ImageEntry::make("{$prefix}-avatar")
                ->hiddenLabel()
                ->state(null)
                ->defaultImageUrl($avatar)
                ->alt($name)
                ->circular()
                ->imageSize(32)
                ->grow(false),
            $content,
        ])))
            ->key($prefix)
            ->extraAttributes(['data-comment' => (string) $comment->getKey()]);
    }

    /**
     * Reactions (toggles), then a menu with the remaining reactions, edit and delete.
     *
     * @return array<Action|ActionGroup>
     */
    protected function makeCommentActions(Comment $comment): array
    {
        $readOnly = $this->isReadOnly();
        $user = CommentsPlugin::get()->getAuthor();
        $canReact = ! $readOnly && Comments::can('react', $comment);
        $used = [];
        $unused = [];

        foreach ($this->getReactions() as $index => $reaction) {
            $summary = Reactions::summary($comment, $reaction, $user);

            $action = Action::make("react-{$index}")
                ->record($comment)
                ->label(Reactions::label($reaction, $summary))
                ->tooltip(Reactions::tooltip($reaction, $summary))
                ->size(Size::ExtraSmall)
                ->color($summary['mine'] ? 'primary' : 'gray')
                ->extraAttributes(['data-reaction' => $reaction, 'aria-pressed' => $summary['mine'] ? 'true' : 'false'])
                ->disabled(! $canReact)
                ->successRedirectUrl(static fn (): ?string => PageRefresh::url())
                ->action(function () use ($comment, $reaction): void {
                    $user = CommentsPlugin::get()->getAuthor();

                    abort_unless($user !== null && Comments::can('react', $comment, $user), 403);

                    Comments::toggleReaction($comment, $user, $reaction);
                    $this->flushComments();
                });

            if ($summary['count'] === 0) {
                $unused[] = $action;
            } else {
                $used[] = $action->outlined(! $summary['mine']);
            }
        }

        $menu = [];

        if ($canReact && $unused !== []) {
            $menu[] = ActionGroup::make($unused)
                ->label(__('alfakit-comments::comments.reactions.add'))
                ->icon('smile-plus')
                ->iconButton()
                ->size(Size::ExtraSmall)
                ->color('gray')
                ->tooltip(__('alfakit-comments::comments.reactions.add'));
        }

        if (! $readOnly) {
            $menu[] = ActionGroup::make([
                $this->makeEditAction($comment),
                $this->makeDeleteAction($comment),
            ])
                ->label(__('alfakit-comments::comments.actions.more'))
                ->icon('ellipsis')
                ->iconButton()
                ->size(Size::ExtraSmall)
                ->color('gray')
                ->tooltip(__('alfakit-comments::comments.actions.more'));
        }

        return [...$used, ...$menu];
    }

    // ---------------------------------------------------------------- Actions

    protected function makePostAction(): Action
    {
        return Action::make('postComment')
            ->label(__('alfakit-comments::comments.actions.post'))
            ->icon('message-square-plus')
            ->modalHeading(__('alfakit-comments::comments.actions.post'))
            ->modalSubmitActionLabel(__('alfakit-comments::comments.actions.post_submit'))
            // Always empty: the action runs on the page's record, whose own `body` would fill it.
            ->fillForm(['body' => null])
            ->schema([CommentEditor::make('body')])
            ->visible(fn (): bool => ($commentable = $this->getCommentable()) !== null && Comments::can('create', $commentable))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.posted'))
            ->successRedirectUrl(static fn (): ?string => PageRefresh::url())
            ->action(function (array $data): void {
                $commentable = $this->getCommentable();

                abort_unless($commentable !== null && ! $this->isReadOnly() && Comments::can('create', $commentable), 403);

                Comments::post($commentable, (string) ($data['body'] ?? ''), CommentsPlugin::get()->getAuthor());
                $this->flushComments();
            });
    }

    protected function makeEditAction(Comment $comment): Action
    {
        return Action::make('editComment')
            ->record($comment)
            ->label(__('alfakit-comments::comments.actions.edit'))
            ->icon('pencil')
            ->modalHeading(__('alfakit-comments::comments.actions.edit_heading'))
            ->modalSubmitActionLabel(__('alfakit-comments::comments.actions.save'))
            ->fillForm(['body' => (string) $comment->getAttribute('body')])
            ->schema([CommentEditor::make('body')])
            ->visible(static fn (): bool => Comments::can('update', $comment))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.updated'))
            ->successRedirectUrl(static fn (): ?string => PageRefresh::url())
            ->action(function (array $data) use ($comment): void {
                abort_unless(Comments::can('update', $comment), 403);

                Comments::update($comment, (string) ($data['body'] ?? ''));
                $this->flushComments();
            });
    }

    protected function makeDeleteAction(Comment $comment): Action
    {
        return Action::make('deleteComment')
            ->record($comment)
            ->label(__('alfakit-comments::comments.actions.delete'))
            ->icon('trash-2')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('alfakit-comments::comments.actions.delete_heading'))
            ->modalDescription(__('alfakit-comments::comments.actions.delete_description'))
            ->visible(static fn (): bool => Comments::can('delete', $comment))
            ->successNotificationTitle(__('alfakit-comments::comments.notifications.deleted'))
            ->successRedirectUrl(static fn (): ?string => PageRefresh::url())
            ->action(function () use ($comment): void {
                abort_unless(Comments::can('delete', $comment), 403);

                Comments::delete($comment);
                $this->flushComments();
            });
    }

    protected function makeLoadMoreAction(): Action
    {
        return Action::make('loadMoreComments')
            ->label($this->getLoadMoreLabel())
            ->icon('chevrons-down')
            ->link()
            ->successRedirectUrl(static fn (): ?string => PageRefresh::url())
            ->action(function (): void {
                $this->loadMore();
            });
    }
}
