<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiCredentials;
use App\Models\ApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

#[Signature('api-client:create {name? : A label to identify this client}')]
#[Description('Create a new API client for CRUD access: an RSA keypair (asymmetric, for the token request) plus a client_secret (symmetric, for per-request signing)')]
class ApiClientCreate extends Command
{
    use GeneratesApiCredentials;

    public function handle(): int
    {
        $name = $this->argument('name') ?: text(
            label: 'What should this client be called?',
            placeholder: 'e.g. Mobile App, Partner XYZ',
            required: 'A name is required.',
        );

        if (ApiClient::where('name', $name)->exists()) {
            $proceed = confirm(
                label: "A client named \"{$name}\" already exists. Create another one anyway?",
                default: false,
            );

            if (! $proceed) {
                $this->components->warn('Cancelled.');

                return self::FAILURE;
            }
        }

        $clientId = (string) Str::uuid();
        $credentials = $this->generateApiCredentials();

        ApiClient::create([
            'name' => $name,
            'client_id' => $clientId,
            'public_key' => $credentials['public_key'],
            'client_secret' => $credentials['client_secret'],
            'is_active' => true,
        ]);

        $this->components->info("API client \"{$name}\" created successfully.");
        $this->newLine();

        $this->line('  <fg=green>Client ID</>');
        $this->line($clientId);
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
