<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Minimal native PDF writer (IMEX-004) — a valid, uncompressed PDF 1.4 with a
 * single A4 page of Helvetica text lines. Enough for a clean tabular report
 * export with no rendering dependency; branded layouts can layer on later.
 */
final class Pdf
{
    /**
     * @param  list<array{text: string, size?: int, bold?: bool}>  $lines
     */
    public static function document(string $title, array $lines): string
    {
        $content = "BT\n";
        $y = 800;
        $content .= "/F2 16 Tf\n1 0 0 1 50 {$y} Tm\n(".self::escape($title).") Tj\n";
        $y -= 30;

        foreach ($lines as $line) {
            if ($y < 40) {
                break; // one honest page; a paging writer can come later
            }
            $size = $line['size'] ?? 10;
            $font = ($line['bold'] ?? false) ? '/F2' : '/F1';
            $content .= "{$font} {$size} Tf\n1 0 0 1 50 {$y} Tm\n(".self::escape($line['text']).") Tj\n";
            $y -= (int) round($size * 1.6);
        }
        $content .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        // Latin-1 only in a Type1 non-embedded font; transliterate the rest.
        $text = (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
