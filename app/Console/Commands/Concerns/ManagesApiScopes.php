<?php

namespace App\Console\Commands\Concerns;

use function Laravel\Prompts\multiselect;

/**
 * @mixin \Illuminate\Console\Command
 */
trait ManagesApiScopes
{
    /**
     * Daftar scope yang tersedia beserta deskripsinya.
     */
    protected array $availableScopes = [
        'all'                   => 'Akses penuh ke semua endpoint',
        'khs:read'              => 'Cetak KHS',
        'krs:read'              => 'Cetak KRS',
        'tagihan:read'          => 'Semua endpoint tagihan',
        'tagihan:index'         => 'Tagihan — list semua',
        'tagihan:summary'       => 'Tagihan — summary',
        'tagihan:detail'        => 'Tagihan — detail',
        'tagihan:cek-lunas'     => 'Tagihan — cek lunas',
        'telegram:read'         => 'Semua endpoint telegram',
        'telegram:send-message' => 'Telegram — kirim pesan teks',
        'telegram:send-photo'   => 'Telegram — kirim foto',
        'telegram:send-document'=> 'Telegram — kirim dokumen',
        'telegram:broadcast'    => 'Telegram — broadcast',
    ];

    /**
     * Prompt user to select scopes via interactive multiselect.
     *
     * @param  string[]|null  $default  Scope yang sudah dipilih sebelumnya (pre-selected).
     */
    protected function promptForScopes(?array $default = null): array
    {
        $options = collect($this->availableScopes)
            ->map(fn ($desc, $key) => "{$key}  — {$desc}")
            ->values()
            ->toArray();

        // Pre-select scopes yang sudah ada
        $defaultValues = $default
            ? collect($options)->filter(fn ($opt) => in_array(trim(explode('  —', $opt)[0]), $default, true))->values()->toArray()
            : [];

        $choices = multiselect(
            label: 'Select scopes for this client',
            options: $options,
            default: $defaultValues,
            hint: 'Use arrow keys + space to select, Enter to confirm.',
        );

        // Ekstrak hanya scope key-nya (sebelum "  — ")
        return array_map(
            fn (string $choice) => trim(explode('  —', $choice)[0]),
            $choices,
        );
    }

    /**
     * Tampilkan opsi scope dalam bentuk tabel. Cocok untuk preview sebelum ubah.
     */
    protected function displayScopes(?array $scopes): void
    {
        if (empty($scopes)) {
            $this->line('  <fg=yellow>none</>');
            return;
        }

        foreach ($scopes as $scope) {
            $desc = $this->availableScopes[$scope] ?? '—';
            if ($scope === 'all') {
                $desc = "<fg=green>{$desc}</>";
            }
            $this->line("  <fg=cyan>●</> {$scope} <fg=gray>— {$desc}</>");
        }
    }
}
