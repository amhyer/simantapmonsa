<?php

namespace Tests\Feature;

use App\Services\ExcelExportService;
use App\Services\MesinPresensi\PresensiMesinFileParser;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use ZipArchive;

class PhpSpreadsheetUpgradeSafetyTest extends TestCase
{
    private function makeTempPath(string $prefix, string $ext = 'xlsx'): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$prefix.'_'.bin2hex(random_bytes(6)).'.'.$ext;
    }

    public function test_excel_export_service_generates_valid_readable_xlsx(): void
    {
        $service = app(ExcelExportService::class);
        $response = $service->download(
            'uji-ekspor.xlsx',
            'Rekap Uji',
            ['No', 'Nama', 'Nilai'],
            [
                [1, 'Andi Saputra', 90],
                [2, '=SUM(1,1)', 85],
            ]
        );

        $tmpFile = $this->makeTempPath('simantap_export');
        try {
            ob_start();
            $response->sendContent();
            $binary = (string) ob_get_clean();

            $this->assertNotEmpty($binary);
            file_put_contents($tmpFile, $binary);

            $loaded = IOFactory::load($tmpFile);
            $sheet = $loaded->getActiveSheet();

            $this->assertSame('Rekap Uji', $sheet->getTitle());
            $this->assertSame('No', (string) $sheet->getCell('A1')->getValue());
            $this->assertSame('Andi Saputra', (string) $sheet->getCell('B2')->getValue());
            $this->assertSame('90', (string) $sheet->getCell('C2')->getFormattedValue());
            // Formula injection guard: nilai diawali '=' disimpan sebagai string aman.
            $this->assertStringContainsString('SUM(1,1)', (string) $sheet->getCell('B3')->getValue());
            $loaded->disconnectWorksheets();
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_presensi_file_parser_reads_real_xlsx_from_temp_dir(): void
    {
        $tmpFile = $this->makeTempPath('simantap_mesin');
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['NIK', 'Tanggal', 'Jam Masuk', 'Jam Pulang'],
                ['PIN777', '2026-10-19', '07:05', '14:15'],
            ]);
            (new Xlsx($spreadsheet))->save($tmpFile);
            $spreadsheet->disconnectWorksheets();

            $upload = new UploadedFile(
                $tmpFile,
                'log-mesin.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $parsed = app(PresensiMesinFileParser::class)->parseLogScans($upload);

            $this->assertSame(0, $parsed['baris_diabaikan']);
            $this->assertCount(2, $parsed['scans']);
            $this->assertSame('PIN777', $parsed['scans'][0]['nik']);
            $this->assertSame('masuk', $parsed['scans'][0]['jenis']);
            $this->assertSame('2026-10-19 07:05:00', $parsed['scans'][0]['waktu']->format('Y-m-d H:i:s'));
            $this->assertSame('pulang', $parsed['scans'][1]['jenis']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_xlsx_with_doctype_xxe_payload_is_rejected_without_reading_external_entity(): void
    {
        $tmpFile = $this->makeTempPath('simantap_xxe');
        try {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->setCellValue('A1', 'NIK');
            (new Xlsx($spreadsheet))->save($tmpFile);
            $spreadsheet->disconnectWorksheets();

            // Sisipkan DOCTYPE/ENTITY berbahaya pada xl/workbook.xml secara lokal (tanpa jaringan).
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($tmpFile) === true);
            $xxeXml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file:///nonexistent_secret_file"> ]>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="&xxe;" sheetId="1" r:id="rId1"/></sheets></workbook>';
            $zip->addFromString('xl/workbook.xml', $xxeXml);
            $zip->close();

            $upload = new UploadedFile(
                $tmpFile,
                'malicious.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $this->expectException(\Throwable::class);
            app(PresensiMesinFileParser::class)->readRows($upload);
        } finally {
            @unlink($tmpFile);
        }
    }
}
