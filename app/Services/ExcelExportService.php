<?php

namespace App\Services;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared, formula-safe XLSX writer for admin report downloads.
 */
class ExcelExportService
{
    /**
     * @param list<string> $headers
     * @param iterable<array<int, mixed>> $rows
     */
    public function download(string $fileName, string $sheetTitle, array $headers, iterable $rows): StreamedResponse
    {
        abort_if($headers === [], 500, 'Kolom ekspor tidak boleh kosong.');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->safeSheetTitle($sheetTitle));

        foreach (array_values($headers) as $column => $header) {
            $coordinate = Coordinate::stringFromColumnIndex($column + 1) . '1';
            $sheet->setCellValueExplicit($coordinate, $header, DataType::TYPE_STRING);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            foreach (array_values($row) as $column => $value) {
                if ($column >= count($headers)) {
                    break;
                }

                $coordinate = Coordinate::stringFromColumnIndex($column + 1) . $rowNumber;
                $this->setCellValue($sheet, $coordinate, $value);
            }
        }

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getStyle("A1:{$lastColumn}{$rowNumber}")->getAlignment()->setVertical('top')->setWrapText(true);
        $sheet->setAutoFilter("A1:{$lastColumn}{$rowNumber}");
        $sheet->freezePane('A2');

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(20);
        }
        $sheet->getColumnDimension('A')->setWidth(8);

        $fileName = basename($fileName);
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static function () use ($writer, $spreadsheet): void {
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, must-revalidate, no-store',
            ],
        );
    }

    private function safeSheetTitle(string $title): string
    {
        $title = str_replace([chr(92), '/', '?', '*', '[', ']', ':'], ' ', $title);

        return mb_substr(trim($title) ?: 'Laporan', 0, 31);
    }

    private function setCellValue($sheet, string $coordinate, mixed $value): void
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if (is_int($value) || is_float($value)) {
            $sheet->setCellValue($coordinate, $value);

            return;
        }

        if (is_bool($value)) {
            $sheet->setCellValue($coordinate, $value);

            return;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        // Explicit string typing prevents names beginning with '=' or other
        // user-controlled values from being interpreted as spreadsheet formulas.
        $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
    }
}
