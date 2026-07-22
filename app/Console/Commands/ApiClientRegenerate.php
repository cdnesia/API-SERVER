<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiCredentials;
use App\Console\Commands\Concerns\SelectsApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

#[Signature('api-client:regenerate {client? : The client_id or name of the client to regenerate}')]
#[Description('Regenerate the RSA keypair and client_secret for an existing API client, invalidating the old ones')]
class ApiClientRegenerate extends Command
{
    use GeneratesApiCredentials;
    use SelectsApiClient;

    public function handle(): int
    {
        $client = $this->findApiClient($this->argument('client'));

        if (! $client) {
            return self::FAILURE;
        }

        $confirmed = confirm(
            label: "Regenerate credentials for \"{$client->name}\"? The current private key and client_secret will stop working immediately.",
            default: false,
        );

        if (! $confirmed) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        $credentials = $this->generateApiCredentials();

        $client->update([
            'public_key' => $credentials['public_key'],
            'client_secret' => $credentials['client_secret'],
        ]);

        $this->components->info("Credentials for \"{$client->name}\" regenerated successfully.");
        $this->newLine();

        $this->line('  <fg=green>Client ID</>');
        $this->line($client->client_id);
        $this->newLine();

        $this->line('  <fg=green>Client Secret</>');
        $this->line($credentials['client_secret']);
        $this->newLine();

        $this->line('  <fg=green>Private Key</>');
        $this->line($credentials['private_key']);
        $this->newLine();

        $this->components->warn('The private key is asymmetric — give it to the client, do not store it here.');
        $this->components->warn('Neither the private key nor the client_secret can be recovered later — copy them now.');

        return self::SUCCESS;
    }
}
