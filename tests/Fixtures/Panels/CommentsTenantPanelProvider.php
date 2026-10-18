<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests\Fixtures\Panels;

use AlfaSix\Alfakit\Panel;
use AlfaSix\Alfakit\PanelProvider;
use AlfaSix\Alfakit\Tests\Fixtures\Models\Team;
use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource;

class CommentsTenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('comments-team')
            ->path('comments-team')
            ->login()
            ->middleware(['web'])
            ->tenant(Team::class, slugAttribute: 'slug')
            ->resources([TicketResource::class])
            ->plugin(CommentsPlugin::make());
    }
}
