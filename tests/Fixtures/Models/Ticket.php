<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Models;

use AlfaSix\Alfakit\Tests\Fixtures\Models\Team;
use AlfaSix\AlfakitComments\Concerns\HasComments;
use AlfaSix\AlfakitComments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model implements Commentable
{
    use HasComments;

    protected $table = 'comments_tickets';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
