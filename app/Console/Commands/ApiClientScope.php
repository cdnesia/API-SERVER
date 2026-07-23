<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ManagesApiScopes;
use App\Console\Commands\Concerns\SelectsApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('api-client:scope {client? : The client_id or name of the client to update}')]
#[Description('Add, remove, or change scopes for an existing API client')]
class ApiClientScope extends Command
{
    use ManagesApiScopes, SelectsApiClient;

    public function handle(): int
    {
        $client = $this->findApiClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $this->newLine();

        // ── Tampilkan status saat ini ──
        $this->line("  <fg=gray>Client</>  <fg=cyan>{$client->name}</>");
        $this->line("  <fg=gray>ID</>      {$client->client_id}");
        $this->line('  <fg=gray>Status</>  '.($client->is_active ? '<fg=green>active</>' : '<fg=red>revoked</>'));

        $this->newLine();
        $this->line('  <fg=gray>Current scopes:</>');
        $this->displayScopes($client->scopes);
        $this->newLine();

        // ── Garis pemisah ──
        $this->line('  ─────────────────────────────────');

        // ── Pilih scope baru (pre-selected dengan scope lama) ──
        $newScopes = $this->promptForScopes($client->scopes);

        // ── Tampilkan perubahan ──
        $this->newLine();
        $this->line('  <fg=gray>New scopes:</>');
        $this->displayScopes($newScopes);
        $this->newLine();

        if ($newScopes === ($client->scopes ?? [])) {
            $this->components->warn('No changes to scopes.');
            return self::SUCCESS;
        }

        $confirmed = confirm(
            label: 'Apply these scope changes?',
            default: true,
        );

        if (! $confirmed) {
            $this->components->warn('Cancelled.');
            return self::FAILURE;
        }

        $client->update(['scopes' => $newScopes]);

        $this->newLine();
        $this->components->info("\"{$client->name}\" scopes updated.");
        $this->components->warn('Existing tokens will still use old scopes — client must obtain a new token.');

        return self::SUCCESS;
    }
}
