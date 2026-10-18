<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Tests;

use AlfaSix\Alfakit\Tests\Fixtures\Models\Team;
use AlfaSix\AlfakitComments\Tests\Fixtures\Models\CommentsUser;
use AlfaSix\AlfakitComments\Tests\Fixtures\Models\Ticket;
use Illuminate\Testing\TestResponse;

/**
 * Perform an Inertia GET as the renderer does.
 *
 * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
 */
function inertiaGet(string $url): TestResponse
{
    return \Pest\Laravel\get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
}

/**
 * The page schema of an Inertia page.
 *
 * @return array<string, mixed>
 */
function pageProps(string $url): array
{
    $response = inertiaGet($url);
    $response->assertOk();

    return $response->json('props.page');
}

function user(string $name = 'Ada Admin'): CommentsUser
{
    return CommentsUser::query()->create(['name' => $name, 'email' => uniqid('user', true).'@example.com', 'password' => 'password']);
}

function ticket(string $title = 'Printer on fire', ?Team $team = null): Ticket
{
    return Ticket::query()->create(['title' => $title, 'team_id' => $team?->getKey()]);
}

function team(string $name): Team
{
    return Team::query()->create(['name' => $name, 'slug' => strtolower($name)]);
}

/**
 * A mention marker as the rich editor stores it.
 */
function mention(CommentsUser $user): string
{
    return '<span data-type="mention" data-id="'.$user->getKey().'" data-label="'.e((string) $user->getAttribute('name')).'" data-char="@">@'.e((string) $user->getAttribute('name')).'</span>';
}

/**
 * Every component in a serialised schema tree, depth-first.
 *
 * @param  array<int|string, mixed>  $nodes
 * @return array<int, array<string, mixed>>
 */
function flatten(array $nodes): array
{
    $flat = [];

    foreach ($nodes as $node) {
        if (! is_array($node)) {
            continue;
        }

        if (isset($node['type']) && is_string($node['type'])) {
            $flat[] = $node;
        }

        foreach (['components', 'schema', 'children', 'items', 'actions', 'content'] as $key) {
            if (isset($node[$key]) && is_array($node[$key])) {
                array_push($flat, ...flatten(array_is_list($node[$key]) ? $node[$key] : [$node[$key]]));
            }
        }
    }

    return $flat;
}

/**
 * The serialised component with this key.
 *
 * @param  array<int|string, mixed>  $nodes
 * @return array<string, mixed>|null
 */
function component(array $nodes, string $key): ?array
{
    foreach (flatten($nodes) as $node) {
        if (($node['key'] ?? null) === $key) {
            return $node;
        }
    }

    return null;
}
