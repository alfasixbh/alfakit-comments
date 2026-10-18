<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments;

use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Policies\CommentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AlfakitCommentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'alfakit-comments');

        // Your own `Gate::policy(Comment::class, ...)` (registered later) replaces this one.
        if (! array_key_exists(Comment::class, Gate::policies())) {
            Gate::policy(Comment::class, CommentPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'alfakit-comments-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/alfakit-comments'),
            ], 'alfakit-comments-translations');
        }
    }
}
