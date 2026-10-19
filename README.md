# AlfaKit Comments

<p><picture><source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/alfasixbh/alfakit/main/art/alfasix/alfa-six-micro-white.svg"><img src="https://raw.githubusercontent.com/alfasixbh/alfakit/main/art/alfasix/alfa-six-micro-navy.svg" alt="" width="24" height="18" align="top"></picture> <sub>by <a href="https://alfasixbh.com">Alfa Six</a></sub></p>

> This is a read-only split of the [AlfaKit monorepo](https://github.com/alfasixbh/alfakit/tree/main/packages/plugins/comments). Please open issues and pull requests there.

Comments with `@` mentions, emoji reactions and notifications on any record of an [AlfaKit](https://alfakit.dev) panel, in the spirit of Filament's Commentions. It renders in React, Vue and Svelte with AlfaKit's built-in components.

## Installation

```bash
composer require alfasix/alfakit-comments
php artisan vendor:publish --tag=alfakit-comments-migrations
php artisan migrate
```

Mention notifications use Laravel's `notifications` table (`php artisan make:notifications-table`) and show in the panel's bell with `->databaseNotifications()`.

## Register

```php
use AlfaSix\AlfakitComments\CommentsPlugin;

$panel->plugin(
    CommentsPlugin::make()
        ->reactions(['👍', '❤️', '😄', '🎉'])
        ->newestFirst()
        ->perPage(10),
);
```

```php
use AlfaSix\AlfakitComments\Concerns\HasComments;
use AlfaSix\AlfakitComments\Concerns\IsCommenter;
use AlfaSix\AlfakitComments\Contracts\Commentable;
use AlfaSix\AlfakitComments\Contracts\Commenter;

class Ticket extends Model implements Commentable
{
    use HasComments;
}

class User extends Authenticatable implements Commenter
{
    use IsCommenter;
}
```

## Usage

```php
use AlfaSix\AlfakitComments\Actions\CommentsAction;
use AlfaSix\AlfakitComments\Actions\CommentsTableAction;
use AlfaSix\AlfakitComments\Infolists\Components\CommentsEntry;
use AlfaSix\AlfakitComments\Widgets\CommentsWidget;

// Infolist (view page), or a form (edit page)
Section::make('Comments')->schema([CommentsEntry::make()]);

// Header action: the comments in a slide-over
CommentsAction::make();

// Table row action
$table->recordActions([CommentsTableAction::make()]);

// A polling feed in a record page's footer widgets
protected function getFooterWidgets(): array
{
    return [CommentsWidget::class];
}

// From code
$ticket->comment('<p>Done!</p>', $user);
```

See the [documentation](https://alfakit.dev/docs/plugins/comments) for every option, the events and the policy.

## License

MIT. See [LICENSE.md](LICENSE.md).
