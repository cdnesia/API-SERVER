<?php

namespace App\Console\Commands\Concerns;

use App\Models\ApiClient;
use Closure;
use Illuminate\Database\Eloquent\Builder;

use function Laravel\Prompts\select;

trait SelectsApiClient
{
    /**
     * Resolve an API client from an explicit identifier (client_id or name),
     * or prompt the user to pick one when no identifier was given.
     *
     * @param  (Closure(Builder): void)|null  $listScope  Restricts which clients appear in the interactive picker only.
     */
    protected function findApiClient(?string $identifier, ?Closure $listScope = null): ?ApiClient
    {
        if ($identifier !== null) {
            $client = ApiClient::where('client_id', $identifier)->first()
                ?? ApiClient::where('name', $identifier)->first();

            if (! $client) {
                $this->components->error("No API client matches \"{$identifier}\".");
            }

            return $client;
        }

        $query = ApiClient::query();

        if ($listScope) {
            $listScope($query);
        }

        $clients = $query->orderBy('name')->get();

        if ($clients->isEmpty()) {
            $this->components->error('No API clients found.');

            return null;
        }

        $clientId = select(
            label: 'Which client?',
            options: $clients->mapWithKeys(fn (ApiClient $client) => [
                $client->client_id => "{$client->name} ({$client->client_id})".($client->is_active ? '' : ' [revoked]'),
            ])->all(),
        );

        return $clients->firstWhere('client_id', $clientId);
    }
}
