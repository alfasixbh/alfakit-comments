<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Models;

use AlfaSix\AlfakitComments\CommentsPlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One user's reaction (an emoji) to a comment. A user reacts at most once with each emoji.
 *
 * @property int|string $id
 * @property int|string $comment_id
 * @property string $reactor_type
 * @property int|string $reactor_id
 * @property string $reaction
 * @property-read Comment|null $comment
 * @property-read Model|null $reactor
 */
class CommentReaction extends Model
{
    protected $table = 'alfakit_comment_reactions';

    protected $guarded = [];

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommentsPlugin::get()->getCommentModel(), 'comment_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reactor(): MorphTo
    {
        return $this->morphTo();
    }
}
