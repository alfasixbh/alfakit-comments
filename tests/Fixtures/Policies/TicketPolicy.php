<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Policies;

use AlfaSix\AlfakitComments\Tests\Fixtures\Models\Ticket;

class TicketPolicy
{
    public function view(mixed $user, Ticket $ticket): bool
    {
        return ! $ticket->getAttribute('is_private');
    }
}
