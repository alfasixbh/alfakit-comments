<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource\Pages;

use AlfaSix\Alfakit\Resources\Pages\ViewRecord;
use AlfaSix\AlfakitComments\Actions\CommentsAction;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [CommentsAction::make()];
    }
}
