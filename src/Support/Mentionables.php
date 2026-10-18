<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitComments\Support;

use AlfaSix\Alfakit\Contracts\HasTenants;
use AlfaSix\AlfakitComments\CommentsPlugin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * The users that can be mentioned in the current panel: the plugin's `mentionables()`, or
 * the panel's users, limited to the members of the current tenant on tenancy panels.
 */
final class Mentionables
{
    /** Tenant relationships tried, in order, for the members of a tenant. */
    public const MEMBER_RELATIONSHIPS = ['members', 'users'];

    /**
     * @return Builder<Model>|null
     */
    public static function query(): ?Builder
    {
        $tenant = self::tenant();
        $query = CommentsPlugin::get()->getMentionablesQuery($tenant);

        if ($query !== null) {
            return $query;
        }

        $model = self::userModel();

        if ($model === null) {
            return null;
        }

        /** @var Builder<Model> $query */
        $query = $model::query();

        if ($tenant !== null) {
            foreach (self::MEMBER_RELATIONSHIPS as $relationship) {
                if (! method_exists($tenant, $relationship)) {
                    continue;
                }

                $relation = $tenant->{$relationship}();

                $instance = $query->getModel();

                // Members are the panel's users (the same table, maybe another class).
                if ($relation instanceof Relation && $relation->getRelated()->getTable() === $instance->getTable()) {

                    return $query->whereIn(
                        $instance->getQualifiedKeyName(),
                        $relation->getQuery()->toBase()->select($relation->getRelated()->getQualifiedKeyName()),
                    );
                }
            }
        }

        return $query;
    }

    /**
     * Mention search results, `id => name`, for the rich editor.
     *
     * @return array<string, string>
     */
    public static function search(string $search, int $limit = 10): array
    {
        $query = self::query();

        if ($query === null) {
            return [];
        }

        $search = trim($search);

        if ($search !== '') {
            $columns = CommentsPlugin::get()->getMentionableSearchColumns();
            $like = '%'.mb_strtolower($search).'%';

            $query->where(static function (Builder $query) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $query->orWhereRaw('LOWER('.$query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column)).') LIKE ?', [$like]);
                }
            });
        }

        $results = [];

        foreach ($query->limit($limit * 3)->get() as $user) {
            if (! self::isAccessible($user)) {
                continue;
            }

            $results[(string) $user->getKey()] = Commenters::name($user);

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * The mentionable users with these ids (others, e.g. users of another tenant, are left out).
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Model>
     */
    public static function find(array $ids): Collection
    {
        $query = $ids === [] ? null : self::query();

        if ($query === null) {
            return collect();
        }

        return $query->whereKey(array_values($ids))->get()
            ->filter(self::isAccessible(...))
            ->values()
            ->toBase();
    }

    /**
     * Users with these ids for showing their current names in a comment, whether or not they
     * can still be mentioned.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Model>
     */
    public static function findForDisplay(array $ids): Collection
    {
        $query = $ids === [] ? null : self::query();

        if ($query === null) {
            return collect();
        }

        return $query->getModel()->newQuery()->whereKey(array_values($ids))->get()->toBase();
    }

    /**
     * On tenancy panels, users that implement `HasTenants` must be able to access the tenant.
     */
    public static function isAccessible(Model $user): bool
    {
        $tenant = self::tenant();

        return $tenant === null || ! $user instanceof HasTenants || $user->canAccessTenant($tenant);
    }

    private static function tenant(): ?Model
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();

        return $panel !== null && $panel->hasTenancy() ? alfakit()->getTenant() : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function userModel(): ?string
    {
        $panel = alfakit()->getCurrentPanel() ?? alfakit()->getDefaultPanel();
        $guard = $panel?->getAuthGuard() ?? config('auth.defaults.guard');
        $provider = config("auth.guards.{$guard}.provider");
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }
}
