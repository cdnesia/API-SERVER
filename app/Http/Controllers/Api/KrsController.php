<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AkademikService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KrsController extends Controller
{
    public function __construct(
        protected AkademikService $akademik,
    ) {}

    /**
     * Cetak Kartu Rencana Studi (KRS) dalam format PDF.
     *
     * Endpoint:  POST /api/krs/cetak
     * Body (JSON): { "npm": "...", "periode?": "...", "view": "inline|download" }
     *
     * - npm     (required) NPM mahasiswa
     * - periode (optional) filter semester tertentu, kosong = semua semester
     * - view    (optional) "inline" atau "download"
     */
    public function cetak(Request $request): mixed
    {
        $validator = Validator::make($request->json()->all(), [
            'npm'     => ['required', 'string', 'max:20'],
            'periode' => ['nullable', 'string', 'regex:/^\d{4}[12]$/'],
            'view'    => ['nullable', 'string', 'in:inline,download'],
        ], [
            'npm.required'  => 'NPM wajib diisi.',
            'npm.max'       => 'NPM maksimal :max karakter.',
            'periode.regex' => 'Format periode tidak valid. Gunakan format YYYY1 (Ganjil) atau YYYY2 (Genap), contoh: 20241.',
            'view.in'       => 'Nilai view harus "inline" atau "download".',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(
                'Validasi gagal',
                $validator->errors(),
                422,
            );
        }

        $npm     = $request->json('npm');
        $periode = $request->json('periode');
        $view    = $request->json('view', 'download');

        try {
            $saya = $this->akademik->getStudent($npm);
            $krs  = $this->akademik->getKrs($npm, $periode);
        } catch (QueryException $e) {
            return ApiResponse::error(
                'Gagal terhubung ke database. Silakan coba beberapa saat lagi.',
                null,
                503,
                503001,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 404);
        } catch (\Throwable $e) {
            return ApiResponse::error('Gagal memproses permintaan', null, 500);
        }

        $pdf = Pdf::loadView('pdf.krs', compact('saya', 'krs', 'periode'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => true,
                'chroot'          => public_path(),
            ]);

        $suffix = $periode ?? 'semua';
        $filename = "KRS_{$npm}_{$suffix}.pdf";

        return $view === 'inline'
            ? $pdf->inline($filename)
            : $pdf->download($filename);
    }
}
