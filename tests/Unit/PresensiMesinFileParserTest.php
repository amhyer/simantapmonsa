<?php

namespace Tests\Unit;

use App\Services\MesinPresensi\PresensiMesinFileParser;
use App\Services\MesinPresensi\PresensiMesinKaryawanService;
use App\Services\MesinPresensi\PresensiMesinPullService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class PresensiMesinFileParserTest extends TestCase
{
    private function parser(): PresensiMesinFileParser
    {
        return new PresensiMesinFileParser(app(PresensiMesinKaryawanService::class));
    }

    public function test_parse_log_rows_supports_column_mode_and_counts_ignored_rows(): void
    {
        $rows = [
            [
                ['raw' => 'NIK', 'fmt' => 'NIK'],
                ['raw' => 'Tanggal', 'fmt' => 'Tanggal'],
                ['raw' => 'Jam Masuk', 'fmt' => 'Jam Masuk'],
                ['raw' => 'Jam Pulang', 'fmt' => 'Jam Pulang'],
            ],
            [
                ['raw' => 'PIN001', 'fmt' => 'PIN001'],
                ['raw' => '2026-10-19', 'fmt' => '2026-10-19'],
                ['raw' => '07:05', 'fmt' => '07:05'],
                ['raw' => '14:15', 'fmt' => '14:15'],
            ],
            [
                ['raw' => '', 'fmt' => ''],
                ['raw' => '2026-10-19', 'fmt' => '2026-10-19'],
                ['raw' => '07:10', 'fmt' => '07:10'],
                ['raw' => '', 'fmt' => ''],
            ],
            [
                ['raw' => 'PIN002', 'fmt' => 'PIN002'],
                ['raw' => 'bukan-tanggal', 'fmt' => 'bukan-tanggal'],
                ['raw' => '07:10', 'fmt' => '07:10'],
                ['raw' => '', 'fmt' => ''],
            ],
        ];

        $result = $this->parser()->parseLogRows($rows);

        $this->assertSame(2, $result['baris_diabaikan']);
        $this->assertCount(2, $result['scans']);
        $this->assertSame('PIN001', $result['scans'][0]['nik']);
        $this->assertSame('masuk', $result['scans'][0]['jenis']);
        $this->assertSame('2026-10-19 07:05:00', $result['scans'][0]['waktu']->format('Y-m-d H:i:s'));
        $this->assertSame('pulang', $result['scans'][1]['jenis']);
        $this->assertSame('2026-10-19 14:15:00', $result['scans'][1]['waktu']->format('Y-m-d H:i:s'));
    }

    public function test_parse_log_rows_supports_datetime_mode_and_rejects_unrecognized_headers(): void
    {
        $rows = [
            [
                ['raw' => 'No. PIN', 'fmt' => 'No. PIN'],
                ['raw' => 'Tgl/Waktu', 'fmt' => 'Tgl/Waktu'],
                ['raw' => 'Status', 'fmt' => 'Status'],
            ],
            [
                ['raw' => '7371010101900001', 'fmt' => '7371010101900001'],
                ['raw' => '19/10/2026 06:55:00', 'fmt' => '19/10/2026 06:55:00'],
                ['raw' => 'C/Masuk', 'fmt' => 'C/Masuk'],
            ],
            [
                ['raw' => '7371010101900001', 'fmt' => '7371010101900001'],
                ['raw' => '19/10/2026 14:20:00', 'fmt' => '19/10/2026 14:20:00'],
                ['raw' => '', 'fmt' => ''],
            ],
        ];

        $result = $this->parser()->parseLogRows($rows);

        $this->assertSame(0, $result['baris_diabaikan']);
        $this->assertCount(2, $result['scans']);
        $this->assertSame('masuk', $result['scans'][0]['jenis']);
        $this->assertSame('pulang', $result['scans'][1]['jenis']);

        $this->expectException(InvalidArgumentException::class);
        $this->parser()->parseLogRows([
            [['raw' => 'Foo', 'fmt' => 'Foo'], ['raw' => 'Bar', 'fmt' => 'Bar']],
        ]);
    }

    public function test_pull_service_chunks_access_date_ranges_by_31_days_and_keeps_tcp_single_chunk(): void
    {
        $pull = new PresensiMesinPullService;
        $dari = Carbon::parse('2026-01-01');
        $sampai = Carbon::parse('2026-03-10');

        $tcpChunks = $pull->susunRentangBaca($dari, $sampai, 'tcp');
        $this->assertCount(1, $tcpChunks);
        $this->assertSame('2026-01-01', $tcpChunks[0][0]->toDateString());
        $this->assertSame('2026-03-10', $tcpChunks[0][1]->toDateString());

        $odbcChunks = $pull->susunRentangBaca($dari, $sampai, 'odbc');
        $this->assertCount(3, $odbcChunks);
        $this->assertSame('2026-01-01', $odbcChunks[0][0]->toDateString());
        $this->assertSame('2026-01-31', $odbcChunks[0][1]->toDateString());
        $this->assertSame('2026-02-01', $odbcChunks[1][0]->toDateString());
        $this->assertSame('2026-03-03', $odbcChunks[1][1]->toDateString());
        $this->assertSame('2026-03-04', $odbcChunks[2][0]->toDateString());
        $this->assertSame('2026-03-10', $odbcChunks[2][1]->toDateString());
    }
}
