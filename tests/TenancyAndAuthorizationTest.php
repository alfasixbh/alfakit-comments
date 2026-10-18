<?php

declare(strict_types=1);

use AlfaSix\Alfakit\Schemas\Schema;
use AlfaSix\AlfakitComments\Infolists\Components\CommentsEntry;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Support\Mentionables;

use function AlfaSix\AlfakitComments\Tests\component;
use function AlfaSix\AlfakitComments\Tests\inertiaGet;
use function AlfaSix\AlfakitComments\Tests\mention;
use function AlfaSix\AlfakitComments\Tests\pageProps;
use function AlfaSix\AlfakitComments\Tests\team;
use function AlfaSix\AlfakitComments\Tests\ticket;
use function AlfaSix\AlfakitComments\Tests\user;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(AlfaSix\AlfakitComments\Tests\TestCase::class);

require_once __DIR__.'/helpers.php';

/**
 * Two teams: Ada belongs to both, Jane to Alpha, Bob to Beta.
 *
 * @return array{alpha: AlfaSix\Alfakit\Tests\Fixtures\Models\Team, beta: AlfaSix\Alfakit\Tests\Fixtures\Models\Team, ada: AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser, jane: AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser, bob: AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser}
 */
function cmTeams(): array
{
    $alpha = team('Alpha');
    $beta = team('Beta');
    $ada = user('Ada');
    $jane = user('Jane');
    $bob = user('Bob');
    $ada->teams()->attach([$alpha->id, $beta->id]);
    $jane->teams()->attach($alpha);
    $bob->teams()->attach($beta);

    return compact('alpha', 'beta', 'ada', 'jane', 'bob');
}

it('never shows the comments of another tenant\'s record', function (): void {
    ['alpha' => $alpha, 'beta' => $beta, 'ada' => $ada] = cmTeams();
    actingAs($ada);
    $mine = ticket('Alpha ticket', $alpha);
    $theirs = ticket('Beta ticket', $beta);
    $mine->comment('<p>Alpha comment</p>', $ada);
    $secret = $theirs->comment('<p>Beta comment</p>', $ada);

    $content = pageProps('/comments-team/alpha/tickets/'.$mine->getKey())['content'];

    expect(json_encode($content))->toContain('Alpha comment')->not->toContain('Beta comment');

    inertiaGet('/comments-team/alpha/tickets/'.$theirs->getKey())->assertNotFound();

    // Actions of the other tenant's comment can't be reached from this tenant's page.
    $token = pageProps('/comments-team/alpha/tickets/'.$mine->getKey())['token'];

    postJson('/comments-team/alpha/_alfakit/actions', [
        'token' => $token,
        'component' => 'field:infolist::comments-'.$secret->getKey().'-actions',
        'action' => 'deleteComment',
        'state' => [],
    ])->assertNotFound();

    expect(Comment::query()->count())->toBe(2);
});

it('limits mentions to the members of the current tenant', function (): void {
    ['alpha' => $alpha, 'ada' => $ada, 'jane' => $jane, 'bob' => $bob] = cmTeams();
    actingAs($ada);
    $ticket = ticket('Alpha ticket', $alpha);
    $url = '/comments-team/alpha/tickets/'.$ticket->getKey();
    $token = pageProps($url)['token'];

    // The composer's editor searches the members of Alpha only.
    $response = getJson('/comments-team/alpha/_alfakit/schema/options?'.http_build_query([
        'token' => $token,
        'component' => 'action:field:infolist::comments-composer.postComment',
        'path' => 'body',
        'mention' => '@',
        'search' => '',
    ]))->assertOk();

    expect(array_column($response->json('options'), 'label'))->toEqualCanonicalizing(['Ada', 'Jane']);

    postJson('/comments-team/alpha/_alfakit/actions', [
        'token' => $token,
        'component' => 'field:infolist::comments-composer',
        'action' => 'postComment',
        'data' => ['body' => '<p>'.mention($jane).' '.mention($bob).'</p>'],
        'state' => [],
    ])->assertOk();

    expect($jane->notifications()->count())->toBe(1)
        ->and($bob->notifications()->count())->toBe(0)
        ->and(Comment::query()->sole()->getMentionedIds())->toBe([(string) $jane->id, (string) $bob->id]);
});

it('finds tenant members through the tenant relationship', function (): void {
    ['alpha' => $alpha, 'ada' => $ada] = cmTeams();
    actingAs($ada);
    alfakit()->setCurrentPanel(alfakit()->getPanel('comments-team'));
    alfakit()->setTenant($alpha);

    expect(array_values(Mentionables::search('')))->toEqualCanonicalizing(['Ada', 'Jane'])
        ->and(Mentionables::search('bo'))->toBe([]);
});

it('hides the comments from users who may not view the record', function (): void {
    actingAs($ada = user());
    alfakit()->setCurrentPanel(alfakit()->getPanel('comments'));
    $ticket = ticket('Secret');
    $ticket->comment('<p>Hush</p>', $ada);
    $ticket->update(['is_private' => true]);

    $schema = Schema::make()->components([CommentsEntry::make()])->record($ticket)->toSchema();

    expect(component([$schema], 'comments'))->toBeNull()
        ->and(json_encode($schema))->not->toContain('Hush');

    inertiaGet('/comments/tickets/'.$ticket->getKey())->assertForbidden();
});

it('needs a signed-in user', function (): void {
    alfakit()->setCurrentPanel(alfakit()->getPanel('comments'));
    $ticket = ticket();

    expect(CommentsEntry::make()->commentable($ticket)->isHidden())->toBeTrue();
});
