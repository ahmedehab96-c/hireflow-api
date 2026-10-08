<?php

namespace Tests\Support;

/** Builds tiny but valid text PDFs for upload tests (no binary fixtures in the repo). */
final class Pdf
{
    /** @param  list<string>  $lines */
    public static function withText(array $lines): string
    {
        $ops = ['BT', '/F1 12 Tf', '14 TL', '72 720 Td'];
        foreach ($lines as $line) {
            $ops[] = '('.addcslashes($line, '()\\').') Tj T*';
        }
        $ops[] = 'ET';

        return self::build(implode("\n", $ops));
    }

    /** A structurally valid PDF with no text at all (like a scanned image). */
    public static function blank(): string
    {
        return self::build('');
    }

    private static function build(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
