<?php

namespace App\Services;

use App\Models\BipotPerAngkatan;
use App\Models\BipotPerSemester;
use App\Models\MasterBipot;
use Illuminate\Support\Collection;

class BipotService
{
    // ──────────────────────────────────────────────
    //  Master Bipot (jenis biaya)
    // ──────────────────────────────────────────────

    /**
     * Ambil semua jenis biaya (bipot), diurutkan sesuai kolom `urutan`.
     */
    public function getAllBipot(): Collection
    {
        return MasterBipot::orderBy('urutan')->orderBy('id')->get();
    }

    public function getBipotById(int $id): ?MasterBipot
    {
        return MasterBipot::find($id);
    }

    public function createBipot(array $data): MasterBipot
    {
        return MasterBipot::create([
            'nama_bipot' => $data['nama_bipot'],
            'trxid'      => $data['trxid'],
            'urutan'     => $data['urutan'] ?? null,
        ]);
    }

    public function updateBipot(int $id, array $data): ?MasterBipot
    {
        $bipot = $this->getBipotById($id);

        if (! $bipot) {
            return null;
        }

        $bipot->fill($data)->save();

        return $bipot->fresh();
    }

    public function deleteBipot(int $id): bool
    {
        $bipot = $this->getBipotById($id);

        if (! $bipot) {
            return false;
        }

        return (bool) $bipot->delete();
    }

    // ──────────────────────────────────────────────
    //  Bipot per Angkatan (mapping tahun akademik + prodi)
    // ──────────────────────────────────────────────

    /**
     * Cari mapping angkatan, opsional filter kode_prodi / kode_tahun / id_program_kuliah.
     */
    public function getAngkatan(?string $kodeProdi = null, ?string $kodeTahun = null, ?int $idProgramKuliah = null): Collection
    {
        return BipotPerAngkatan::query()
            ->when($kodeProdi, fn ($q) => $q->where('kode_prodi', $kodeProdi))
            ->when($kodeTahun, fn ($q) => $q->where('kode_tahun', $kodeTahun))
            ->when($idProgramKuliah, fn ($q) => $q->where('id_program_kuliah', $idProgramKuliah))
            ->orderBy('kode_tahun', 'desc')
            ->get();
    }

    public function getAngkatanById(int $id): ?BipotPerAngkatan
    {
        return BipotPerAngkatan::find($id);
    }

    public function createAngkatan(array $data): BipotPerAngkatan
    {
        return BipotPerAngkatan::create([
            'kode_tahun'        => $data['kode_tahun'],
            'nama_tahun'        => $data['nama_tahun'],
            'id_program_kuliah' => $data['id_program_kuliah'],
            'kode_prodi'        => $data['kode_prodi'],
        ]);
    }

    public function updateAngkatan(int $id, array $data): ?BipotPerAngkatan
    {
        $angkatan = $this->getAngkatanById($id);

        if (! $angkatan) {
            return null;
        }

        $angkatan->fill($data)->save();

        return $angkatan->fresh();
    }

    public function deleteAngkatan(int $id): bool
    {
        $angkatan = $this->getAngkatanById($id);

        if (! $angkatan) {
            return false;
        }

        return (bool) $angkatan->delete();
    }

    // ──────────────────────────────────────────────
    //  Rincian nominal per semester
    // ──────────────────────────────────────────────

    /**
     * Rincian biaya (nominal per jenis bipot) untuk satu prodi + tahun akademik,
     * opsional difilter per semester dan status mahasiswa (baru/lama/pindahan/dll).
     */
    public function getRincianBiaya(string $kodeProdi, string $kodeTahun, ?int $semester = null, ?int $statusMahasiswa = null): Collection
    {
        $angkatanIds = BipotPerAngkatan::where('kode_prodi', $kodeProdi)
            ->where('kode_tahun', $kodeTahun)
            ->pluck('id');

        if ($angkatanIds->isEmpty()) {
            return collect();
        }

        return BipotPerSemester::with('bipot', 'angkatan')
            ->whereIn('id_bipot_angkatan', $angkatanIds)
            ->when($semester, fn ($q) => $q->where('semester', $semester))
            ->when($statusMahasiswa, fn ($q) => $q->whereJsonContains('status_mahasiswa', $statusMahasiswa))
            ->get()
            ->sortBy(fn ($item) => $item->bipot?->urutan ?? 0)
            ->values();
    }

    public function getPerSemesterById(int $id): ?BipotPerSemester
    {
        return BipotPerSemester::with('bipot', 'angkatan')->find($id);
    }

    public function createPerSemester(array $data): BipotPerSemester
    {
        return BipotPerSemester::create([
            'id_bipot_angkatan' => $data['id_bipot_angkatan'],
            'id_bipot'          => $data['id_bipot'],
            'nominal'           => $data['nominal'],
            'semester'          => $data['semester'] ?? null,
            'status_awal'       => $data['status_awal'] ?? [],
            'status_mahasiswa'  => $data['status_mahasiswa'] ?? [],
        ]);
    }

    public function updatePerSemester(int $id, array $data): ?BipotPerSemester
    {
        $item = BipotPerSemester::find($id);

        if (! $item) {
            return null;
        }

        $item->fill($data)->save();

        return $item->fresh();
    }

    public function deletePerSemester(int $id): bool
    {
        $item = BipotPerSemester::find($id);

        if (! $item) {
            return false;
        }

        return (bool) $item->delete();
    }

    // ──────────────────────────────────────────────
    //  Format
    // ──────────────────────────────────────────────

    public function formatBipot(MasterBipot $bipot): array
    {
        return [
            'id'         => $bipot->id,
            'nama_bipot' => $bipot->nama_bipot,
            'trxid'      => $bipot->trxid,
            'urutan'     => $bipot->urutan,
        ];
    }

    public function formatAngkatan(BipotPerAngkatan $angkatan): array
    {
        return [
            'id'                 => $angkatan->id,
            'kode_tahun'         => $angkatan->kode_tahun,
            'nama_tahun'         => $angkatan->nama_tahun,
            'id_program_kuliah'  => $angkatan->id_program_kuliah,
            'kode_prodi'         => $angkatan->kode_prodi,
        ];
    }

    public function formatPerSemester(BipotPerSemester $item): array
    {
        return [
            'id'                => $item->id,
            'id_bipot_angkatan' => $item->id_bipot_angkatan,
            'id_bipot'          => $item->id_bipot,
            'nama_bipot'        => $item->bipot?->nama_bipot,
            'kode_tahun'        => $item->angkatan?->kode_tahun,
            'kode_prodi'        => $item->angkatan?->kode_prodi,
            'nominal'           => (float) $item->nominal,
            'semester'          => $item->semester,
            'status_awal'       => $item->status_awal,
            'status_mahasiswa'  => $item->status_mahasiswa,
        ];
    }
}
