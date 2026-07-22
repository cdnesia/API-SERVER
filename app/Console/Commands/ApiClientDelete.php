<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SelectsApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('api-client:delete {client? : The client_id or name of the client to delete}')]
#[Description('Permanently delete an API client from the database')]
class ApiClientDelete extends Command
{
    use SelectsApiClient;

    public function handle(): int
    {
        $client = $this->findApiClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $confirmed = confirm(
            label: "Permanently delete \"{$client->name}\" ({$client->client_id})? This cannot be undone.",
            default: false,
        );

        if (! $confirmed) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        $client->delete();

        $this->components->info("\"{$client->name}\" has been deleted.");

        return self::SUCCESS;
    }
}
