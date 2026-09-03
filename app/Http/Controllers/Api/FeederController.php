<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeederService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeederController extends Controller
{
    private FeederService $feeder;

    public function __construct(FeederService $feeder)
    {
        $this->feeder = $feeder;
    }

    public function cariByNpm(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'npm' => 'required|alpha_num|max:20',
        ], [
            'npm.required'  => 'NPM wajib diisi.',
            'npm.alpha_num' => 'NPM hanya boleh berisi angka dan huruf.',
            'npm.max'       => 'NPM maksimal 20 karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        $npm = $request->input('npm');

        $result = $this->feeder->getData([
            'act'    => 'GetListMahasiswa',
            'filter' => "nim='{$npm}'",
            'order'  => '',
            'limit'  => 1,
            'offset' => 0,
        ]);

        if (($result['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $result['error_desc'] ?? 'Gagal mengambil data mahasiswa.',
                null,
                500,
                $result['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        $mahasiswa = $result['data'][0] ?? null;

        if (! $mahasiswa) {
            return ApiResponse::error(
                "Mahasiswa dengan NPM {$npm} tidak ditemukan.",
                null,
                404,
                ErrorCode::DATA_NOT_FOUND,
            );
        }

        $id_mahasiswa = $mahasiswa['id_mahasiswa'];

        $biodata = $this->feeder->getData([
            'act'    => 'GetBiodataMahasiswa',
            'filter' => "id_mahasiswa='{$id_mahasiswa}'",
            'order'  => '',
            'limit'  => 1,
            'offset' => 0,
        ]);

        if (($biodata['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $biodata['error_desc'] ?? 'Gagal mengambil biodata mahasiswa.',
                null,
                500,
                $biodata['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        $biodatamahasiswa = $biodata['data'][0] ?? [];

        $data = array_merge($mahasiswa, $biodatamahasiswa);
        $data = array_filter($data, fn($key) => !str_starts_with($key, 'id_'), ARRAY_FILTER_USE_KEY);

        return ApiResponse::success($data, 'Data mahasiswa ditemukan.');
    }
    public function mahasiswaAkm(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'id_prodi'   => 'nullable|string|max:50',
            'periode'    => 'nullable|array',
            'periode.*'  => 'string|max:20',
        ], [
            'id_prodi.max'    => 'ID Prodi maksimal 50 karakter.',
            'periode.array'   => 'Periode harus berupa array.',
            'periode.*.max'   => 'Periode maksimal 20 karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        $idProdi  = $request->input('id_prodi');
        $periode  = $request->input('periode', []);

        $conditions = [];
        if ($idProdi) {
            $conditions[] = "id_prodi='" . addslashes($idProdi) . "'";
        }
        if ($periode) {
            $periodeList = array_map(fn($p) => "'" . addslashes($p) . "'", $periode);
            $conditions[] = 'id_semester IN (' . implode(',', $periodeList) . ')';
        }
        $filter = implode(' AND ', $conditions);

        $result = $this->feeder->getData([
            'act'    => 'GetListPerkuliahanMahasiswa',
            'filter' => $filter,
            'order'  => '',
            'limit'  => 0,
            'offset' => 0,
        ]);

        if (($result['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $result['error_desc'] ?? 'Gagal mengambil data mahasiswa.',
                null,
                500,
                $result['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        return ApiResponse::success($result, 'Data mahasiswa ditemukan.');
    }
    public function mahasiswaKeluar(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'id_prodi'   => 'nullable|string|max:50',
            'periode'    => 'nullable|array',
            'periode.*'  => 'string|max:20',
        ], [
            'id_prodi.max'    => 'ID Prodi maksimal 50 karakter.',
            'periode.array'   => 'Periode harus berupa array.',
            'periode.*.max'   => 'Periode maksimal 20 karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        $idProdi  = $request->input('id_prodi');
        $periode  = $request->input('periode', []);

        $conditions = [];
        if ($idProdi) {
            $conditions[] = "id_prodi='" . addslashes($idProdi) . "'";
        }
        if ($periode) {
            $periodeList = array_map(fn($p) => "'" . addslashes($p) . "'", $periode);
            $conditions[] = 'id_periode_keluar IN (' . implode(',', $periodeList) . ')';
        }
        $filter = implode(' AND ', $conditions);

        $result = $this->feeder->getData([
            'act'    => 'GetListRiwayatPendidikanMahasiswa',
            'filter' => $filter,
            'order'  => '',
            'limit'  => 0,
            'offset' => 0,
        ]);

        if (($result['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $result['error_desc'] ?? 'Gagal mengambil data mahasiswa.',
                null,
                500,
                $result['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        return ApiResponse::success($result, 'Data mahasiswa ditemukan.');
    }
    public function mahasiswaMasuk(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'id_prodi'   => 'nullable|string|max:50',
            'periode'    => 'nullable|array',
            'periode.*'  => 'string|max:20',
        ], [
            'id_prodi.max'    => 'ID Prodi maksimal 50 karakter.',
            'periode.array'   => 'Periode harus berupa array.',
            'periode.*.max'   => 'Periode maksimal 20 karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                $validator->errors()->first(),
                $validator->errors()->toArray(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        $idProdi  = $request->input('id_prodi');
        $periode  = $request->input('periode', []);

        $conditions = [];
        if ($idProdi) {
            $conditions[] = "id_prodi='" . addslashes($idProdi) . "'";
        }
        if ($periode) {
            $periodeList = array_map(fn($p) => "'" . addslashes($p) . "'", $periode);
            $conditions[] = 'id_periode_masuk IN (' . implode(',', $periodeList) . ')';
        }
        $filter = implode(' AND ', $conditions);

        $result = $this->feeder->getData([
            'act'    => 'GetListRiwayatPendidikanMahasiswa',
            'filter' => $filter,
            'order'  => '',
            'limit'  => 0,
            'offset' => 0,
        ]);

        if (($result['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $result['error_desc'] ?? 'Gagal mengambil data mahasiswa.',
                null,
                500,
                $result['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        return ApiResponse::success($result, 'Data mahasiswa ditemukan.');
    }
}