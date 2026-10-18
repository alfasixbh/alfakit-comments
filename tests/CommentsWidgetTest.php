<?php

declare(strict_types=1);

use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Models\Comment;
use Illuminate\Testing\TestResponse;

use function AlfaSix\AlfakitComments\Tests\inertiaGet;
use function AlfaSix\AlfakitComments\Tests\pageProps;
use function AlfaSix\AlfakitComments\Tests\ticket;
use function AlfaSix\AlfakitComments\Tests\user;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(AlfaSix\AlfakitComments\Tests\TestCase::class);

require_once __DIR__.'/helpers.php';

const CM_WIDGET = 'widget:alfa-six-alfakit-comments-widgets-comments-widget';

/**
 * The widget's table as a poll refreshes it.
 *
 * @return array<string, mixed>
 */
function cmWidgetTable(string $url): array
{
    $component = inertiaGet($url)->json('component');

    return get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => '',
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => 'alfakitPoll',
        'X-Alfakit-Poll' => CM_WIDGET,
    ])->assertOk()->json('props.alfakitPoll')[CM_WIDGET]['table'];
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function cmWidgetAction(string $url, string $action, int|string|null $record = null, array $data = []): TestResponse
{
    return post('/comments/_alfakit/actions', array_filter([
        'token' => pageProps($url)['token'],
        'component' => CM_WIDGET,
        'action' => $action,
        'record' => $record,
        'data' => $data,
    ], static fn (mixed $value): bool => $value !== null), ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
}

it('lists the record\'s comments in a polling table widget', function (): void {
    $plugin = alfakit()->getPanel('comments')->getPlugin(CommentsPlugin::ID);
    assert($plugin instanceof CommentsPlugin);
    $plugin->pollingInterval('15s')->perPage(5);

    actingAs($ada = user('Ada'));
    $ticket = ticket();
    $other = ticket('Other');
    $ticket->comment('<p>On this ticket</p>', $ada);
    $other->comment('<p>On another ticket</p>', $ada);

    $table = cmWidgetTable('/comments/tickets/'.$ticket->getKey().'/edit');

    expect($table['pollingInterval'] ?? null)->toBe('15s')
        ->and($table['pagination']['perPage'])->toBe(5)
        ->and($table['records'])->toHaveCount(1)
        ->and($table['records'][0]['cells']['body']['state'])->toBe('<p>On this ticket</p>')
        ->and($table['records'][0]['cells']['author_name']['state'])->toBe('Ada')
        ->and(array_column($table['headerActions'], 'name'))->toBe(['postComment']);
});

it('posts, reacts and deletes through the widget actions', function (): void {
    actingAs($ada = user('Ada'));
    $ticket = ticket();
    $url = '/comments/tickets/'.$ticket->getKey().'/edit';

    cmWidgetAction($url, 'postComment', data: ['body' => '<p>Via widget</p>'])->assertRedirect();

    $comment = Comment::query()->sole();

    expect($comment->body)->toBe('<p>Via widget</p>');

    cmWidgetAction($url, 'react-2', $comment->getKey())->assertRedirect();

    expect($comment->reactions()->sole()->reaction)->toBe('😄')
        ->and(cmWidgetTable($url)['records'][0]['actions'][2]['label'])->toBe('😄 1');

    cmWidgetAction($url, 'deleteComment', $comment->getKey())->assertRedirect();

    expect(Comment::query()->count())->toBe(0);
});

it('keeps the widget actions to the page record and to the author', function (): void {
    $ada = user('Ada');
    $ticket = ticket();
    $other = ticket('Other');
    $mine = $ticket->comment('<p>Ada</p>', $ada);
    $elsewhere = $other->comment('<p>Elsewhere</p>', $ada);
    actingAs(user('Bob'));
    $url = '/comments/tickets/'.$ticket->getKey().'/edit';

    cmWidgetAction($url, 'deleteComment', $elsewhere->getKey())->assertNotFound();
    cmWidgetAction($url, 'deleteComment', $mine->getKey())->assertForbidden();

    expect(Comment::query()->count())->toBe(2);
});
