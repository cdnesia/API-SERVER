<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Cetak Kartu Hasil Studi</title>
</head>
<style>
    body {
        /* font-family: DejaVu Sans, sans-serif; */
        font-size: 14px;
        /* Default ukuran huruf */
    }

    .table-krs {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .table-krs thead th {
        background-color: #eeeeee;
        border: 1px solid #444;
        padding: 8px 6px;
        font-weight: bold;
        text-align: center;
    }

    .table-krs tbody td {
        border: 1px solid #888;
        padding: 7px 6px;
        vertical-align: middle;
    }

    .table-krs tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }
</style>

<body>
    <table style="width: 100%; margin-top: -10px">
        <tr>
            <td style="width: 100px">
                <img src="{{ public_path('assets/images/favicon-32x32.png') }}" width="100px">
            </td>
            <td style="text-align: center">
                <span style="font-size: 24px; font-weight: bold">MAJELIS DIKTILITBANG MUHAMMADIYAH</span> <br>
                <span style="font-size: 28px; font-weight: bold">UNIVERSITAS MUHAMMADIYAH JAMBI</span> <br>
                @php
                    $fakultas = Str::upper($saya['nama_fakultas']);
                    $len = mb_strlen($fakultas);
                    // Semakin panjang teks, semakin kecil font
                    $fontSize = match (true) {
                        $len <= 25 => '24px',
                        $len <= 30 => '22px',
                        $len <= 35 => '22px',
                        $len <= 40 => '22px',
                        $len <= 50 => '20px',
                        default    => '22px',
                    };
                @endphp
                <span style="font-size: {{ $fontSize }}; font-weight: bold">{{ $fakultas }}</span> <br>
                <span style="font-size: 12px; font-weight: bold">Jl. Kapten Pattimura, Simpang IV Sipin, Kec. Telanaipura, Kota Jambi, Jambi 36124 - Telp: (0741) 60825</span>
            </td>
        </tr>
    </table>
    <div style="border-top: 3px solid black; margin-top: 4px;"></div>
    <div style="border-top: 1px solid black; margin-top: 2px;"></div>
    <p style="text-align:center; font-weight: bold; font-size: 20px; text-decoration: underline">KARTU HASIL STUDI
    </p>
    <table style="font-weight: bold;">
        <tr>
            <td>Nama Mahasiswa</td>
            <td>: {{ $saya['nama_mahasiswa'] }}</td>
        </tr>
        <tr>
            <td>NPM</td>
            <td>: {{ $saya['npm'] }}</td>
        </tr>
        @if ($periode)
            @php
                $tahun = substr($periode, 0, 4);
                $isGenap = ((int) substr($periode, -1)) % 2 == 0;
            @endphp
            <tr>
                <td>Tahun Akademik</td>
                <td>: {{ $tahun }}/{{ $tahun + 1 }} {{ $isGenap ? 'Genap' : 'Ganjil' }}</td>
            </tr>
        @endif
        <tr>
            <td>Fakultas</td>
            <td>: {{ $saya['nama_fakultas'] }}</td>
        </tr>
        <tr>
            <td>Program Studi</td>
            <td>: {{ $saya['nama_program_studi'] }}</td>
        </tr>
    </table>

    {{-- Mode: semua semester --}}
    @if (empty($periode) && ! empty($krs['semester']))
        @foreach ($krs['semester'] as $sem)
            <p style="text-align:center; font-weight: bold; font-size: 20px; margin-top: 30px;">
                Semester {{ $sem['label'] }}
            </p>
            <table class="table-krs">
                <thead>
                    <tr>
                        <th rowspan="2" width="5%">No</th>
                        <th rowspan="2" width="12%">Kode</th>
                        <th rowspan="2">Mata Kuliah</th>
                        <th rowspan="2" width="8%">SKS</th>
                        <th colspan="2">Nilai</th>
                    </tr>
                    <tr>
                        <th width="12%">Angka</th>
                        <th width="12%">Huruf</th>
                    </tr>
                </thead>
                <tbody>
                    @if (empty($sem['krs']))
                        <tr>
                            <td colspan="6" class="text-center"><em>Tidak ada mata kuliah</em></td>
                        </tr>
                    @else
                        @foreach ($sem['krs'] as $item)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td class="text-center">{{ $item['kode_mata_kuliah'] }}</td>
                                <td>{{ $item['nama_mata_kuliah'] }}</td>
                                <td class="text-center">{{ $item['sks_matakuliah'] }}</td>
                                <td class="text-center">{{ $item['nilai_angka'] }}</td>
                                <td class="text-center">{{ $item['nilai_huruf'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td colspan="3" class="text-right"><strong>Total SKS</strong></td>
                        <td class="text-center"><strong>{{ $sem['total_sks'] }}</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
            <table>
                <tr>
                    <td><b>IP Semester</b></td>
                    <td><b>: {{ $sem['ips'] }}</b></td>
                </tr>
            </table>
        @endforeach

        <p style="text-align:right; font-weight: bold; margin-top: 20px;">
            IP Kumulatif: {{ $krs['ipk'] }}
        </p>

    {{-- Mode: satu semester --}}
    @else
        <table class="table-krs">
            <thead>
                <tr>
                    <th rowspan="2" width="5%">No</th>
                    <th rowspan="2" width="12%">Kode</th>
                    <th rowspan="2">Mata Kuliah</th>
                    <th rowspan="2" width="8%">SKS</th>
                    <th colspan="2">Nilai</th>
                </tr>
                <tr>
                    <th width="12%">Angka</th>
                    <th width="12%">Huruf</th>
                </tr>
            </thead>
            <tbody>
                @php $totalSks = 0; @endphp

                @foreach ($krs['krs'] ?? [] as $item)
                    @php $totalSks += $item['sks_matakuliah']; @endphp
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center">{{ $item['kode_mata_kuliah'] }}</td>
                        <td>{{ $item['nama_mata_kuliah'] }}</td>
                        <td class="text-center">{{ $item['sks_matakuliah'] }}</td>
                        <td class="text-center">{{ $item['nilai_angka'] }}</td>
                        <td class="text-center">{{ $item['nilai_huruf'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" class="text-right"><strong>Total SKS</strong></td>
                    <td class="text-center"><strong>{{ $totalSks }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
        <table>
            <tr>
                <td><b>IP Semester</b></td>
                <td><b>: {{ $krs['metadata']['ips'] }}</b></td>
            </tr>
            <tr>
                <td><b>IP Kumulatif</b></td>
                <td><b>: {{ $krs['metadata']['ipk'] }}</b></td>
            </tr>
        </table>
    @endif
    @php
        \Carbon\Carbon::setLocale('id');
        $tanggal = \Carbon\Carbon::now()->translatedFormat('d F Y');
    @endphp

    <br><br>

    <table style="width:100%; margin-top:10px;">
        <tr>
            <td style="width:50%;"></td>
            <td style="width:50%; text-align:right;">
                Jambi, {{ $tanggal }}
            </td>
        </tr>
    </table>

    <br>

    <table style="width:100%;">
        <tr>
            <td style="width:50%; text-align:center;">
                Dekan,
            </td>
            <td style="width:50%; text-align:center;">
                Mahasiswa
            </td>
        </tr>

        <tr>
            <td style="height:80px;"></td>
            <td></td>
        </tr>

        <tr>
            <td style="text-align:center; text-decoration: underline">
                <strong>{{ $saya['nama_dekan'] }}</strong>
            </td>
            <td style="text-align:center; text-decoration: underline">
                <strong>{{ $saya['nama_mahasiswa'] }}</strong>
            </td>
        </tr>

        <tr>
            <td style="text-align:center;">
                NIDN: {{ $saya['nidn_dekan'] }}
            </td>
            <td style="text-align:center;">
                NPM: {{ $saya['npm'] }}
            </td>
        </tr>
    </table>

    <br>
    <table style="width:100%; font-size: 12px; margin-top: 10px;">
        <tr>
            <td style="font-weight: bold;" colspan="2">Catatan:</td>
        </tr>
        <tr>
            <td>1.</td>
            <td>KHS dinyatakan sah bila ditandatangani Dekan dan distempel basah.</td>
        </tr>
        <tr>
            <td>2.</td>
            <td>Data KHS yang sah adalah yang sesuai dengan database UM JAMBI, jika ada perbedaan versi cetak dengan yang ada didatabase maka KHS Cetak dianggap tidak sah.</td>
        </tr>
    </table>
</body>

</html>
