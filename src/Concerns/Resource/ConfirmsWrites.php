<?php

namespace Lomkit\Rest\Concerns\Resource;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Confirmation\Confirmation;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\PendingMutation;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Support\Overrides;

trait ConfirmsWrites
{
    private const array CONFIRMATION_HOOKS = [
        Endpoint::Mutate->value => 'mutateConfirmation',
        Endpoint::Destroy->value => 'destroyConfirmation',
        Endpoint::Restore->value => 'restoreConfirmation',
        Endpoint::ForceDestroy->value => 'forceDestroyConfirmation',
    ];

    public function mutateConfirmation(PendingMutation $mutation): ?Confirmation
    {
        return null;
    }

    /** @param Collection<int, Model> $models */
    public function destroyConfirmation(Collection $models): ?Confirmation
    {
        return null;
    }

    /** @param Collection<int, Model> $models */
    public function restoreConfirmation(Collection $models): ?Confirmation
    {
        return null;
    }

    /** @param Collection<int, Model> $models */
    public function forceDestroyConfirmation(Collection $models): ?Confirmation
    {
        return null;
    }

    final public function confirms(Endpoint $endpoint): bool
    {
        $hook = self::CONFIRMATION_HOOKS[$endpoint->value] ?? null;

        return $hook !== null && Overrides::method($this, $hook, Resource::class);
    }

    /** @return array<string, bool> */
    final public function confirmations(): array
    {
        $hooks = Trashed::supportedBy(static::modelClass()) ? self::CONFIRMATION_HOOKS : array_diff_key(self::CONFIRMATION_HOOKS, array_flip([Endpoint::Restore->value, Endpoint::ForceDestroy->value]));

        return array_map(fn (string $hook): bool => Overrides::method($this, $hook, Resource::class), $hooks);
    }
}
