<?php

declare(strict_types=1);

use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Support\Comments;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource\Pages\EditTicket;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource\Pages\ListTickets;
use AlfaSix\AlfakitComments\Tests\Fixtures\Resources\TicketResource\Pages\ViewTicket;

use function AlfaSix\AlfakitComments\Tests\component;
use function AlfaSix\AlfakitComments\Tests\flatten;
use function AlfaSix\AlfakitComments\Tests\mention;
use function AlfaSix\AlfakitComments\Tests\pageProps;
use function AlfaSix\AlfakitComments\Tests\ticket;
use function AlfaSix\AlfakitComments\Tests\user;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(AlfaSix\AlfakitComments\Tests\TestCase::class);

require_once __DIR__.'/helpers.php';

/**
 * Run a comment action through the field action endpoint as the browser does.
 *
 * @param  array<string, mixed>  $data
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function cmRunAction(string $url, string $component, string $action, array $data = []): Illuminate\Testing\TestResponse
{
    $token = pageProps($url)['token'];

    return postJson('/comments/_alfakit/actions', [
        'token' => $token,
        'component' => $component,
        'action' => $action,
        'data' => $data,
        'state' => [],
    ], ['Referer' => 'http://localhost'.$url]);
}

function cmPlugin(): CommentsPlugin
{
    $plugin = alfakit()->getPanel('comments')->getPlugin(CommentsPlugin::ID);

    return $plugin instanceof CommentsPlugin ? $plugin : throw new RuntimeException('The comments plugin is not registered.');
}

it('renders the comments on a resource view page', function (): void {
    $ada = user('Ada');
    $jane = user('Jane');
    actingAs($ada);
    $ticket = ticket();
    $first = $ticket->comment('<p>First</p>', $jane);
    $second = $ticket->comment('<p>Hey '.mention($jane).'</p>', $ada);
    Comments::toggleReaction($second, $jane, '👍');
    $second->forceFill(['created_at' => now()->addMinute()])->save();

    $content = pageProps('/comments/tickets/'.$ticket->getKey())['content'];
    $entry = component($content, 'comments');

    expect($entry)->not->toBeNull()
        ->and($entry['type'])->toBe('Group')
        ->and(component($content, 'comments-composer')['actions'][0]['name'])->toBe('postComment');

    // Newest first: the comments in order, with author, avatar, time and body.
    $keys = array_values(array_filter(array_column(flatten($entry['schema']), 'key'), static fn (string $key): bool => preg_match('/^comments-\d+$/', $key) === 1));

    expect($keys)->toBe(['comments-'.$second->getKey(), 'comments-'.$first->getKey()])
        ->and(component($content, 'comments-'.$second->getKey().'-author')['content'])->toBe('Ada')
        ->and(component($content, 'comments-'.$second->getKey().'-avatar')['state'][0])->toStartWith('data:image/svg+xml')
        ->and(component($content, 'comments-'.$second->getKey().'-body')['state'])->toContain('alfakit-mention')->toContain('Jane')
        ->and(component($content, 'comments-'.$second->getKey().'-time')['tooltip'])->not->toBeEmpty();

    // Reactions: the used ones as buttons with counts, the others in the "add reaction" menu.
    $actions = component($content, 'comments-'.$second->getKey().'-actions')['actions'];

    expect($actions[0]['label'])->toBe('👍 1')
        ->and($actions[0]['tooltip'])->toBe('Jane')
        ->and(array_column($actions[1]['actions'], 'label'))->toBe(['❤️', '😄', '🎉'])
        ->and(array_column($actions[2]['actions'], 'name'))->toBe(['editComment', 'deleteComment']);

    // Jane's comment: Ada can react but neither edit nor delete it.
    $theirs = component($content, 'comments-'.$first->getKey().'-actions')['actions'];

    expect(array_column(flatten($theirs), 'name'))->not->toContain('editComment')->not->toContain('deleteComment')
        ->and(array_column(flatten($theirs), 'name'))->toContain('react-0');
});

it('posts a comment through the action endpoint and reloads the page', function (): void {
    $ada = user('Ada');
    $jane = user('Jane');
    actingAs($ada);
    $ticket = ticket();
    $url = '/comments/tickets/'.$ticket->getKey();

    $response = cmRunAction($url, 'field:infolist::comments-composer', 'postComment', ['body' => '<p>Done '.mention($jane).'<script>x()</script></p>']);

    $response->assertOk()
        ->assertJson(['status' => 'success', 'redirect' => 'http://localhost'.$url]);

    $comment = Comment::query()->sole();

    expect($comment->body)->not->toContain('script')
        ->and($comment->isAuthoredBy($ada))->toBeTrue()
        ->and($jane->notifications()->count())->toBe(1);
});

it('rejects blank comments', function (): void {
    actingAs(user());
    $ticket = ticket();

    cmRunAction('/comments/tickets/'.$ticket->getKey(), 'field:infolist::comments-composer', 'postComment', ['body' => '<p> </p>'])
        ->assertUnprocessable();

    expect(Comment::query()->count())->toBe(0);
});

it('edits, reacts to and deletes comments with the page tester', function (): void {
    $ada = user('Ada');
    actingAs($ada);
    $ticket = ticket();
    $comment = $ticket->comment('<p>Typo</p>', $ada);
    $key = 'comments-'.$comment->getKey().'-actions';

    alfakit()->testPage(ViewTicket::class, ['record' => $ticket], panel: 'comments')
        ->callFormComponentAction($key, 'editComment', ['body' => '<p>Fixed</p>'], schema: 'infolist')
        ->assertHasNoFormErrors();

    expect($comment->fresh()?->body)->toBe('<p>Fixed</p>');

    alfakit()->testPage(ViewTicket::class, ['record' => $ticket], panel: 'comments')
        ->callFormComponentAction($key, 'react-1', schema: 'infolist');

    expect($comment->reactions()->pluck('reaction')->all())->toBe(['❤️']);

    alfakit()->testPage(ViewTicket::class, ['record' => $ticket], panel: 'comments')
        ->callFormComponentAction($key, 'react-1', schema: 'infolist');

    expect($comment->reactions()->count())->toBe(0);

    alfakit()->testPage(ViewTicket::class, ['record' => $ticket], panel: 'comments')
        ->callFormComponentAction($key, 'deleteComment', schema: 'infolist');

    expect(Comment::query()->count())->toBe(0);
});

it('does not let users edit or delete the comments of others', function (): void {
    $ada = user('Ada');
    $bob = user('Bob');
    $ticket = ticket();
    $comment = $ticket->comment('<p>Ada was here</p>', $ada);
    actingAs($bob);
    $url = '/comments/tickets/'.$ticket->getKey();
    $component = 'field:infolist::comments-'.$comment->getKey().'-actions';

    cmRunAction($url, $component, 'editComment', ['body' => '<p>Bob was here</p>'])->assertForbidden();
    cmRunAction($url, $component, 'deleteComment')->assertForbidden();

    expect($comment->fresh()?->body)->toBe('<p>Ada was here</p>');

    // Reacting is fine.
    cmRunAction($url, $component, 'react-0')->assertOk();

    expect($comment->reactions()->sole()->reaction)->toBe('👍');
});

it('never reaches comments of another record', function (): void {
    actingAs($ada = user());
    $mine = ticket('Mine');
    $other = ticket('Other');
    $comment = $other->comment('<p>Elsewhere</p>', $ada);

    cmRunAction('/comments/tickets/'.$mine->getKey(), 'field:infolist::comments-'.$comment->getKey().'-actions', 'deleteComment')
        ->assertNotFound();

    expect(Comment::query()->count())->toBe(1);
});

it('loads more comments', function (): void {
    actingAs($ada = user());
    cmPlugin()->perPage(2);
    $ticket = ticket();

    foreach (['One', 'Two', 'Three'] as $index => $body) {
        $ticket->comment("<p>{$body}</p>", $ada)->forceFill(['created_at' => now()->addMinutes($index)])->save();
    }

    $url = '/comments/tickets/'.$ticket->getKey();
    $content = pageProps($url)['content'];

    expect(component($content, 'comments-3'))->not->toBeNull()
        ->and(component($content, 'comments-1'))->toBeNull()
        ->and(component($content, 'comments-more'))->not->toBeNull();

    // The oldest comment's actions can't be reached before it is shown.
    cmRunAction($url, 'field:infolist::comments-1-actions', 'react-0')->assertNotFound();

    cmRunAction($url, 'field:infolist::comments-more', 'loadMoreComments')->assertOk();

    $content = pageProps($url)['content'];

    expect(component($content, 'comments-1'))->not->toBeNull()
        ->and(component($content, 'comments-more'))->toBeNull();

    cmRunAction($url, 'field:infolist::comments-1-actions', 'react-0')->assertOk();

});

it('lists the oldest first', function (): void {
    actingAs($ada = user());
    $ticket = ticket();
    $ticket->comment('<p>Old</p>', $ada)->forceFill(['created_at' => now()->subHour()])->save();
    $ticket->comment('<p>New</p>', $ada);

    cmPlugin()->oldestFirst();

    $keys = array_values(array_filter(array_column(flatten(pageProps('/comments/tickets/'.$ticket->getKey())['content']), 'key'), static fn (string $key): bool => preg_match('/^comments-\d+$/', $key) === 1));

    expect($keys)->toBe(['comments-1', 'comments-2']);

});

it('opens the comments in a slide-over and updates it in place', function (): void {
    actingAs($ada = user('Ada'));
    $ticket = ticket();
    $ticket->comment('<p>Existing</p>', $ada);

    $test = alfakit()->testPage(ViewTicket::class, ['record' => $ticket], panel: 'comments')
        ->assertActionVisible('comments')
        ->mountAction('comments')
        ->assertMountedActionModalSee('Existing');

    expect($test->getMountedActionModal()['slideOver'] ?? null)->toBeTrue();

    $test->callFormComponentAction('comments-composer', 'postComment', ['body' => '<p>From the slide-over</p>']);

    expect(Comment::query()->count())->toBe(2);

    $test->assertSchemaComponentExists('comments-2-body', checkComponentUsing: fn (array $component): bool => str_contains((string) $component['state'], 'From the slide-over'));
});

it('opens the comments of a table row', function (): void {
    actingAs($ada = user());
    $ticket = ticket();
    $ticket->comment('<p>Row comment</p>', $ada);

    alfakit()->testPage(ListTickets::class, panel: 'comments')
        ->assertTableActionVisible('comments', $ticket)
        ->mountTableAction('comments', $ticket)
        ->assertMountedActionModalSee('Row comment')
        ->callFormComponentAction('comments-composer', 'postComment', ['body' => '<p>Row reply</p>'])
        ->assertSchemaComponentExists('comments-2-body');

    expect(Comment::query()->latest('id')->first()?->commentable_id)->toBe($ticket->getKey());
});

it('updates the comments of an edit page form in place', function (): void {
    actingAs($ada = user());
    $ticket = ticket();

    alfakit()->testPage(EditTicket::class, ['record' => $ticket], panel: 'comments')
        ->callFormComponentAction('comments-composer', 'postComment', ['body' => '<p>From the form</p>'])
        ->assertSchemaComponentExists('comments-'.(Comment::query()->value('id') ?? 0).'-body');

    expect(Comment::query()->sole()->body)->toBe('<p>From the form</p>');
});

it('hides the composer and buttons when read-only', function (): void {
    actingAs($ada = user());
    $ticket = ticket();
    $comment = $ticket->comment('<p>Hi</p>', $ada);

    $entry = AlfaSix\AlfakitComments\Infolists\Components\CommentsEntry::make()->readOnly()->disableReactions()->commentable($ticket);
    $schema = AlfaSix\Alfakit\Schemas\Schema::make()->components([$entry])->record($ticket)->toSchema();

    expect(component($schema['components'] ?? $schema, 'comments-composer'))->toBeNull()
        ->and(component($schema['components'] ?? $schema, 'comments-'.$comment->getKey().'-actions')['actions'] ?? [])->toBe([]);
});
