<?php

namespace App\Services;

use App\Models\Dosen;
use Illuminate\Support\Collection;

class DosenService
{
    /**
     * Cari dosen berdasarkan ID pegawai.
     */
    public function getById(int $id): ?array
    {
        $dosen = Dosen::find($id);

        return $dosen ? $this->format($dosen) : null;
    }

    /**
     * Cari dosen berdasarkan NIDN.
     */
    public function getByNidn(string $nidn): ?array
    {
        $dosen = Dosen::where('nidn', $nidn)->first();

        return $dosen ? $this->format($dosen) : null;
    }

    /**
     * Cari dosen berdasarkan NIK.
     */
    public function getByNik(string $nik): ?array
    {
        $dosen = Dosen::where('nik', $nik)->first();

        return $dosen ? $this->format($dosen) : null;
    }

    /**
     * Ambil semua dosen aktif.
     */
    public function getAllActive(): Collection
    {
        return Dosen::where('status_aktif', 1)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn ($d) => $this->format($d));
    }

    /**
     * Cari dosen berdasarkan nama (LIKE).
     */
    public function search(string $keyword): Collection
    {
        return Dosen::where('nama_lengkap', 'like', "%{$keyword}%")
            ->orWhere('nidn', 'like', "%{$keyword}%")
            ->orWhere('nik', 'like', "%{$keyword}%")
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn ($d) => $this->format($d));
    }

    /**
     * Cari dosen berdasarkan array ID.
     *
     * @param  int[]  $ids
     */
    public function getByIds(array $ids): Collection
    {
        return Dosen::whereIn('id', $ids)
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn ($d) => $this->format($d));
    }

    /**
     * Format standar data dosen.
     */
    public function format(Dosen $dosen): array
    {
        return [
            'id'            => $dosen->id,
            'nama_lengkap'  => $dosen->nama_lengkap,
            'gelar'         => $this->extractGelar($dosen->nama_lengkap),
            'nama_tanpa_gelar' => $this->stripGelar($dosen->nama_lengkap),
            'nidn'          => $dosen->nidn,
            'nik'           => $dosen->nik,
            'email'         => $dosen->email_instansi ?: $dosen->email_pribadi,
            'hp'            => $dosen->hp,
            'telepon'       => $dosen->telepon,
            'status_aktif'  => $dosen->status_aktif,
        ];
    }

    /**
     * Format singkat (nama, nidn) — untuk keperluan cetak/PDF.
     */
    public function formatSingkat(Dosen $dosen): array
    {
        return [
            'nama' => $dosen->nama_lengkap,
            'nidn' => $dosen->nidn,
        ];
    }

    /**
     * Format nama + NIDN untuk ditampilkan di PDF (nama / NIDN).
     */
    public function formatLabel(Dosen $dosen): string
    {
        $nama = $this->stripGelar($dosen->nama_lengkap);
        $nidn = $dosen->nidn;

        return $nidn ? "{$nama} / {$nidn}" : $nama;
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Pisahkan gelar dari nama lengkap.
     * Contoh: "Ahmad Parlaongan, S.P.,M.Si" → ["Ahmad Parlaongan", "S.P.,M.Si"]
     */
    protected function extractGelar(string $namaLengkap): string
    {
        $parts = explode(',', $namaLengkap, 2);

        return isset($parts[1]) ? trim($parts[1]) : '';
    }

    /**
     * Ambil nama saja tanpa gelar.
     */
    protected function stripGelar(string $namaLengkap): string
    {
        $parts = explode(',', $namaLengkap, 2);

        return trim($parts[0]);
    }
}
