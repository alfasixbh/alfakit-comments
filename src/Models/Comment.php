<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Models;

use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Support\CommentBody;
use AlfaSix\AlfakitComments\Support\Commenters;
use AlfaSix\AlfakitComments\Support\Mentionables;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A comment on any model (`commentable`), written by any model (`author`, usually a user).
 * The body is rich HTML, sanitised whenever it is saved; mentions are kept as the rich
 * editor's mention markers (`<span data-type="mention" data-id="5">@Jane</span>`).
 *
 * @property int|string $id
 * @property string $body
 * @property string $commentable_type
 * @property int|string $commentable_id
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|null $commentable
 * @property-read Model|null $author
 * @property-read Collection<int, CommentReaction> $reactions
 */
class Comment extends Model
{
    protected $table = 'alfakit_comments';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(static function (Comment $comment): void {
            $comment->setAttribute('body', CommentBody::sanitize((string) $comment->getAttribute('body')));
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentsPlugin::get()->getReactionModel(), 'comment_id');
    }

    public function isAuthoredBy(?Model $user): bool
    {
        return $user !== null
            && $this->getAttribute('author_id') !== null
            && (string) $this->getAttribute('author_type') === $user->getMorphClass()
            && (string) $this->getAttribute('author_id') === (string) $user->getKey();
    }

    public function getAuthorName(): string
    {
        return Commenters::name($this->author);
    }

    public function getAuthorAvatarUrl(): ?string
    {
        return Commenters::avatarUrl($this->author);
    }

    /**
     * The ids in the body's mention markers, in order of appearance.
     *
     * @return array<int, string>
     */
    public function getMentionedIds(): array
    {
        return CommentBody::mentionedIds((string) $this->getAttribute('body'));
    }

    /**
     * The mentioned users that are mentionable in the current panel (and tenant).
     *
     * @return \Illuminate\Support\Collection<int, Model>
     */
    public function getMentioned(): \Illuminate\Support\Collection
    {
        return Mentionables::find($this->getMentionedIds());
    }

    /**
     * The body ready to display: sanitised, with mentions as chips (or links with
     * `CommentsPlugin::mentionUrl()`) showing the users' current names.
     */
    public function getRenderedBody(): string
    {
        return CommentBody::render((string) $this->getAttribute('body'));
    }

    /**
     * The body as plain text, shortened to `$limit` characters.
     */
    public function getExcerpt(int $limit = 140): string
    {
        return CommentBody::excerpt((string) $this->getAttribute('body'), $limit);
    }

    public function wasEdited(): bool
    {
        $created = $this->getAttribute('created_at');
        $updated = $this->getAttribute('updated_at');

        return $created instanceof DateTimeInterface && $updated instanceof DateTimeInterface
            && $updated->getTimestamp() - $created->getTimestamp() > 1;
    }
}
