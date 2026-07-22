<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

#[Signature('api-client:list')]
#[Description('List all API clients')]
class ApiClientList extends Command
{
    public function handle(): int
    {
        $clients = ApiClient::orderBy('name')->get();

        if ($clients->isEmpty()) {
            $this->components->info('No API clients found.');

            return self::SUCCESS;
        }

        table(
            headers: ['Name', 'Client ID', 'Status', 'Last Used', 'Created'],
            rows: $clients->map(fn (ApiClient $client) => [
                $client->name,
                $client->client_id,
                $client->is_active ? 'Active' : 'Revoked',
                $client->last_used_at?->diffForHumans() ?? 'Never',
                $client->created_at->format('Y-m-d H:i'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
