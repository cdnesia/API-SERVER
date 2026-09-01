<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeederService;
use App\Support\ApiResponse;
use App\Support\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdiController extends Controller
{
    public function __construct(
        protected FeederService $feeder,
    ) {}

    /**
     * Daftar program studi dari Neo Feeder.
     *
     * Opsional filter: id_prodi, nama_program_studi, jenjang.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'id_prodi'             => ['nullable', 'string', 'max:50'],
            'nama_program_studi'   => ['nullable', 'string', 'max:100'],
            'jenjang'              => ['nullable', 'string', 'max:10'],
        ], [
            'id_prodi.max'            => 'ID Prodi maksimal 50 karakter.',
            'nama_program_studi.max'  => 'Nama prodi maksimal 100 karakter.',
            'jenjang.max'             => 'Jenjang maksimal 10 karakter.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Validasi gagal',
                $validator->errors(),
                422,
                ErrorCode::VALIDATION_FAILED,
            );
        }

        // Build filter untuk Neo Feeder
        $conditions = [];

        if ($request->input('id_prodi')) {
            $conditions[] = "id_prodi='" . addslashes($request->input('id_prodi')) . "'";
        }

        if ($request->input('nama_program_studi')) {
            $conditions[] = "nama_program_studi='" . addslashes($request->input('nama_program_studi')) . "'";
        }

        if ($request->input('jenjang')) {
            $conditions[] = "nama_jenjang_pendidikan='" . addslashes($request->input('jenjang')) . "'";
        }

        $filter = implode(' AND ', $conditions);

        $result = $this->feeder->getData([
            'act'    => 'GetProdi',
            'filter' => $filter,
            'order'  => 'nama_jenjang_pendidikan,nama_program_studi',
            'limit'  => 0,
            'offset' => 0,
        ]);

        if (($result['error_code'] ?? 1) !== 0) {
            return ApiResponse::error(
                $result['error_desc'] ?? 'Gagal mengambil data prodi dari Neo Feeder.',
                null,
                500,
                $result['error_code'] ?? ErrorCode::EXTERNAL_API_ERROR,
            );
        }

        $data = $result['data'] ?? [];

        if (empty($data)) {
            return ApiResponse::error(
                'Data program studi tidak ditemukan.',
                null,
                404,
                ErrorCode::DATA_NOT_FOUND,
            );
        }

        return ApiResponse::success($data, 'Data program studi berhasil diambil.');
    }
}

