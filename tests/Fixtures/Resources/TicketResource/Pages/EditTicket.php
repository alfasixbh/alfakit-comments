<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource\Pages;

use AlfaSix\Alfakit\Resources\Pages\EditRecord;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource;
use AlfaSix\AlfakitComments\Widgets\CommentsWidget;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getFooterWidgets(): array
    {
        return [CommentsWidget::class];
    }
}
