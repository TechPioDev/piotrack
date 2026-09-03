<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal native .xlsx writer (IMEX-003) — the same no-dependency philosophy
 * as the crawler and the ICS builder. An xlsx file is a zip of XML parts;
 * this emits a valid single-sheet workbook using inline strings, with every
 * cell passed through the same formula-injection guard as the CSV exports.
 */
final class Xlsx
{
    /**
     * @param  list<array<int, mixed>>  $rows  first row is the header
     */
    public static function write(array $rows, string $sheetName = 'Export'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($file === false) {
            throw new RuntimeException('Could not allocate a temporary file for the export.');
        }

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open the export archive.');
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $row) {
            $sheet .= '<row>';
            foreach ($row as $cell) {
                $sheet .= '<c t="inlineStr"><is><t xml:space="preserve">'
                    .htmlspecialchars(Csv::cell($cell), ENT_XML1)
                    .'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $zip->close();
        $binary = (string) file_get_contents($file);
        @unlink($file);

        return $binary;
    }
}
