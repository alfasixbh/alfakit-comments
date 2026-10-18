<?php

declare(strict_types=1);

use AlfaSix\AlfakitComments\CommentsPlugin;
use AlfaSix\AlfakitComments\Events\CommentWasCreated;
use AlfaSix\AlfakitComments\Events\CommentWasReacted;
use AlfaSix\AlfakitComments\Events\UserWasMentioned;
use AlfaSix\AlfakitComments\Models\Comment;
use AlfaSix\AlfakitComments\Models\CommentReaction;
use AlfaSix\AlfakitComments\Support\CommentBody;
use AlfaSix\AlfakitComments\Support\Comments;
use AlfaSix\AlfakitComments\Support\Mentionables;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

use function AlfaSix\AlfakitComments\Tests\mention;
use function AlfaSix\AlfakitComments\Tests\ticket;
use function AlfaSix\AlfakitComments\Tests\user;
use function Pest\Laravel\actingAs;

uses(AlfaSix\AlfakitComments\Tests\TestCase::class);

require_once __DIR__.'/helpers.php';

beforeEach(function (): void {
    alfakit()->setCurrentPanel(alfakit()->getPanel('comments'));
});

it('registers the plugin with its defaults', function (): void {
    $plugin = alfakit()->getPanel('comments')->getPlugin(CommentsPlugin::ID);

    expect($plugin)->toBeInstanceOf(CommentsPlugin::class)
        ->and(CommentsPlugin::get())->toBe($plugin)
        ->and($plugin->getReactions())->toBe(['👍', '❤️', '😄', '🎉'])
        ->and($plugin->isNewestFirst())->toBeTrue()
        ->and($plugin->getPerPage())->toBe(10)
        ->and($plugin->getCommentModel())->toBe(Comment::class)
        ->and($plugin->getReactionModel())->toBe(CommentReaction::class)
        ->and(Gate::getPolicyFor(Comment::class))->toBeInstanceOf(AlfaSix\AlfakitComments\Policies\CommentPolicy::class);
});

it('configures the plugin options', function (): void {
    $plugin = CommentsPlugin::make()
        ->reactions(['🚀', ' 👀 ', '🚀'])
        ->oldestFirst()
        ->perPage(25)
        ->pollingInterval('5s')
        ->authorUsing(fn () => null);

    expect($plugin->getReactions())->toBe(['🚀', '👀'])
        ->and($plugin->isNewestFirst())->toBeFalse()
        ->and($plugin->getPerPage())->toBe(25)
        ->and($plugin->getPollingInterval())->toBe('5s')
        ->and($plugin->getAuthor())->toBeNull()
        ->and(CommentsPlugin::make()->reactions(false)->getReactions())->toBe([]);

    expect(fn () => CommentsPlugin::make()->commentModel(CommentReaction::class))->toThrow(InvalidArgumentException::class);
});

it('posts a sanitised comment and dispatches the created event', function (): void {
    Event::fake([CommentWasCreated::class]);
    $author = user('Ada');
    $ticket = ticket();

    $comment = $ticket->comment('<p onclick="steal()">Hello <script>alert(1)</script><strong>there</strong> <a href="javascript:alert(1)">x</a></p>', $author);

    expect($comment->exists)->toBeTrue()
        ->and($comment->body)->not->toContain('<script')
        ->and($comment->body)->not->toContain('onclick')
        ->and($comment->body)->not->toContain('javascript:')
        ->and($comment->body)->toContain('<strong>there</strong>')
        ->and($comment->isAuthoredBy($author))->toBeTrue()
        ->and($ticket->comments()->count())->toBe(1);

    Event::assertDispatched(CommentWasCreated::class, fn (CommentWasCreated $event): bool => $event->comment->is($comment));
});

it('sanitises the body on every save', function (): void {
    $comment = ticket()->comment('<p>Fine</p>', user());
    $comment->update(['body' => '<p>Changed<img src=x onerror=alert(1)></p><script>bad()</script>']);

    expect($comment->fresh()?->body)->not->toContain('onerror')->not->toContain('<script');
});

it('posts as the signed-in user by default', function (): void {
    $author = user('Grace');
    actingAs($author);

    $comment = ticket()->comment('<p>Mine</p>');

    expect($comment->isAuthoredBy($author))->toBeTrue()
        ->and($comment->getAuthorName())->toBe('Grace')
        ->and($comment->getAuthorAvatarUrl())->toStartWith('data:image/svg+xml');
});

it('extracts mentions, notifies the mentioned users and dispatches the mention events', function (): void {
    Event::fake([UserWasMentioned::class]);
    $author = user('Ada');
    $jane = user('Jane');
    $bob = user('Bob');
    actingAs($author);

    $comment = ticket()->comment('<p>Hi '.mention($jane).' and '.mention($bob).', also '.mention($jane).' and me '.mention($author).'</p>', $author);

    expect($comment->getMentionedIds())->toBe([(string) $jane->id, (string) $bob->id, (string) $author->id])
        ->and($comment->getMentioned()->pluck('name')->all())->toEqualCanonicalizing(['Jane', 'Bob', 'Ada'])
        ->and($comment->body)->toContain('class="alfakit-mention"');

    Event::assertDispatchedTimes(UserWasMentioned::class, 2);
    Event::assertDispatched(UserWasMentioned::class, fn (UserWasMentioned $event): bool => $event->user->is($jane));
    Event::assertNotDispatched(UserWasMentioned::class, fn (UserWasMentioned $event): bool => $event->user->is($author));

    $notification = $jane->notifications()->sole();

    expect($notification->data['title'])->toBe('Ada mentioned you in a comment')
        ->and($notification->data['body'])->toContain('Hi @Jane and @Bob')
        ->and($notification->data['actions'][0]['url'] ?? null)->toBe('http://localhost/comments/tickets/'.$comment->commentable_id)
        ->and($bob->notifications()->count())->toBe(1)
        ->and($author->notifications()->count())->toBe(0);
});

it('ignores mentions of unknown users and can turn notifications off', function (): void {
    $author = user();
    $jane = user('Jane');

    CommentsPlugin::get()->notifyMentions(false);

    $comment = ticket()->comment('<p><span data-type="mention" data-id="999">@Ghost</span> '.mention($jane).'</p>', $author);

    expect($comment->getMentioned()->pluck('name')->all())->toBe(['Jane'])
        ->and($jane->notifications()->count())->toBe(0);

    CommentsPlugin::get()->notifyMentions();
});

it('notifies only users an edit mentions for the first time', function (): void {
    Event::fake([UserWasMentioned::class]);
    $author = user();
    $jane = user('Jane');
    $bob = user('Bob');

    $comment = ticket()->comment('<p>'.mention($jane).'</p>', $author);
    Comments::update($comment, '<p>'.mention($jane).' '.mention($bob).'</p>');

    Event::assertDispatchedTimes(UserWasMentioned::class, 2);
    expect($jane->notifications()->count())->toBe(1)->and($bob->notifications()->count())->toBe(1);
});

it('renders mentions with the current names, as links with mentionUrl()', function (): void {
    $jane = user('Jane');
    $comment = ticket()->comment('<p>'.mention($jane).'</p>', user());
    $jane->update(['name' => 'Jane Doe']);

    expect($comment->getRenderedBody())->toContain('data-label="Jane Doe"')->toContain('Jane Doe</span>')->toContain('alfakit-mention');

    CommentsPlugin::get()->mentionUrl(fn ($user): string => '/users/'.$user->getKey());

    expect($comment->getRenderedBody())->toContain('<a href="/users/'.$jane->id.'"')->toContain('Jane Doe</a>');

    CommentsPlugin::get()->mentionUrl(null);
});

it('searches the mentionable users', function (): void {
    user('Jane Smith');
    user('Bob Jones');

    expect(array_values(Mentionables::search('jan')))->toBe(['Jane Smith'])
        ->and(Mentionables::search(''))->toHaveCount(2);

    CommentsPlugin::get()->mentionables(fn () => AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser::query()->where('name', 'like', 'Bob%'));

    expect(array_values(Mentionables::search('')))->toBe(['Bob Jones']);

    CommentsPlugin::get()->mentionables(null);
});

it('toggles reactions per user', function (): void {
    Event::fake([CommentWasReacted::class]);
    $ada = user('Ada');
    $bob = user('Bob');
    $comment = ticket()->comment('<p>Ship it</p>', $ada);

    expect(Comments::toggleReaction($comment, $ada, '👍'))->toBeTrue()
        ->and(Comments::toggleReaction($comment, $bob, '👍'))->toBeTrue()
        ->and(Comments::toggleReaction($comment, $bob, '🎉'))->toBeTrue()
        ->and($comment->reactions()->count())->toBe(3)
        ->and(Comments::toggleReaction($comment, $ada, '👍'))->toBeFalse()
        ->and($comment->reactions()->pluck('reaction')->sort()->values()->all())->toBe(['🎉', '👍']);

    Event::assertDispatchedTimes(CommentWasReacted::class, 4);
    Event::assertDispatched(CommentWasReacted::class, fn (CommentWasReacted $event): bool => ! $event->added && $event->reactor->is($ada));
});

it('only lets authors edit and delete their comments', function (): void {
    $ada = user('Ada');
    $bob = user('Bob');
    $comment = ticket()->comment('<p>Mine</p>', $ada);

    expect(Comments::can('update', $comment, $ada))->toBeTrue()
        ->and(Comments::can('delete', $comment, $ada))->toBeTrue()
        ->and(Comments::can('update', $comment, $bob))->toBeFalse()
        ->and(Comments::can('delete', $comment, $bob))->toBeFalse()
        ->and(Comments::can('react', $comment, $bob))->toBeTrue()
        ->and(Comments::can('create', $comment->commentable ?? ticket(), $bob))->toBeTrue();
});

it('follows the commented record view policy', function (): void {
    $ada = user();
    $private = ticket('Secret');
    $private->update(['is_private' => true]);
    actingAs($ada);

    expect(Comments::canView($private))->toBeFalse()
        ->and(Comments::can('create', $private, $ada))->toBeFalse();
});

it('deletes the reactions with the comment', function (): void {
    $ada = user();
    $comment = ticket()->comment('<p>Bye</p>', $ada);
    Comments::toggleReaction($comment, $ada, '👍');

    Comments::delete($comment);

    expect(Comment::query()->count())->toBe(0)->and(CommentReaction::query()->count())->toBe(0);
});

it('detects blank bodies', function (): void {
    expect(CommentBody::isBlank('<p></p><p> &nbsp; </p>'))->toBeTrue()
        ->and(CommentBody::isBlank('<p>x</p>'))->toBeFalse()
        ->and(CommentBody::excerpt('<p>One</p><p>Two   three</p>', 7))->toBe('One Two...');
});
