<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests;

use AlfaSix\Alfakit\Tests\TestCase as AlfakitTestCase;
use AlfaSix\AlfakitComments\AlfakitCommentsServiceProvider;
use AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser;
use AlfaSix\AlfakitComments\Tests\Fixtures\Models\Ticket;
use AlfaSix\AlfakitComments\Tests\Fixtures\Panels\CommentsPanelProvider;
use AlfaSix\AlfakitComments\Tests\Fixtures\Panels\CommentsTenantPanelProvider;
use AlfaSix\AlfakitComments\Tests\Fixtures\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;

abstract class TestCase extends AlfakitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Ticket::class, TicketPolicy::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            AlfakitCommentsServiceProvider::class,
            CommentsPanelProvider::class,
            CommentsTenantPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', CommentsUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
