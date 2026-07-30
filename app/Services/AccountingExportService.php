<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingExportService
{
    /** @param array<int, string> $headings @param iterable<int, array<int, mixed>> $rows */
    public function download(string $format, string $slug, string $title, array $period, array $headings, iterable $rows): Response|StreamedResponse
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);
        if ($format === 'pdf') {
            return Pdf::loadView('admin.accounting.exports.pdf', compact('title', 'period', 'headings', 'rows'))->setPaper('a4', 'landscape')->download($slug.'.pdf');
        }
        if ($format === 'xlsx') {
            return response(SimpleXlsxWriter::make($title, $headings, $rows), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$slug.'.xlsx"']);
        }
        return response()->streamDownload(function () use ($headings, $rows): void { $stream = fopen('php://output', 'wb'); fputcsv($stream, $headings); foreach ($rows as $row) fputcsv($stream, $row); fclose($stream); }, $slug.'.csv', ['Content-Type' => 'text/csv']);
    }
}

class SimpleXlsxWriter
{
    /** @param array<int, string> $headings @param array<int, array<int, mixed>> $rows */
    public static function make(string $title, array $headings, array $rows): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP Zip extension is required for XLSX exports.');
        }
        $path = tempnam(sys_get_temp_dir(), 'cb-xlsx-'); $zip = new \ZipArchive(); $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.self::escape(substr($title, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $all = array_merge([$headings], $rows); $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($all as $index => $row) { $sheet .= '<row r="'.($index + 1).'">'; foreach (array_values($row) as $column => $value) { $ref = self::column($column + 1).($index + 1); $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.self::escape((string) $value).'</t></is></c>'; } $sheet .= '</row>'; }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>'); $zip->close(); $contents = (string) file_get_contents($path); @unlink($path); return $contents;
    }
    private static function column(int $number): string { $name = ''; while ($number > 0) { $number--; $name = chr(65 + ($number % 26)).$name; $number = intdiv($number, 26); } return $name; }
    private static function escape(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
}
