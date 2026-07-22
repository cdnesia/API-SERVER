<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('api-client:revoke {client? : The client_id or name of the client to revoke}')]
#[Description('Deactivate an API client so it can no longer request tokens or access the API')]
class ApiClientRevoke extends Command
{
    use SelectsApiClient;

    public function handle(): int
    {
        $client = $this->findApiClient(
            $this->argument('client'),
            fn ($query) => $query->where('is_active', true),
        );

        if (! $client) {
            return self::FAILURE;
        }

        if (! $client->is_active) {
            $this->components->warn("\"{$client->name}\" is already revoked.");

            return self::SUCCESS;
        }

        $confirmed = confirm(
            label: "Revoke \"{$client->name}\"? It will no longer be able to request tokens or access the API.",
            default: false,
        );

        if (! $confirmed) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        $client->update(['is_active' => false]);

        $this->components->info("\"{$client->name}\" has been revoked.");

        return self::SUCCESS;
    }
}
