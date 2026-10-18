<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Panels;

use AlfaSix\Alfakit\Panel;
use AlfaSix\Alfakit\PanelProvider;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource;

class CommentsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('comments')
            ->path('comments')
            ->login()
            ->middleware(['web'])
            ->databaseNotifications()
            ->resources([TicketResource::class])
            ->plugin(CommentsPlugin::make());
    }
}
