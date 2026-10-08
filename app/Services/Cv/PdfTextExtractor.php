<?php

namespace App\Services\Cv;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/** Extracts plain text from text-based PDFs. Kept separate from AI analysis on purpose. */
class PdfTextExtractor
{
    /** Below this many characters a PDF is almost certainly a scan or image-only file. */
    private const MIN_CHARS = 50;

    /** @throws CvTextExtractionException */
    public function extract(string $absolutePath): string
    {
        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $text = (new Parser([], $config))->parseFile($absolutePath)->getText();
        } catch (Throwable $e) {
            Log::info('CV text extraction failed', ['error' => $e->getMessage()]);
            throw new CvTextExtractionException('We could not read this PDF. It may be damaged or password-protected. Please export it again and retry.');
        }

        $text = $this->normalize($text);

        if (mb_strlen($text) < self::MIN_CHARS) {
            throw new CvTextExtractionException('No readable text was found in this PDF. Scanned or image-only CVs are not supported. Please upload a text-based PDF.');
        }

        return $text;
    }

    private function normalize(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[^\P{C}\n\t]/u', ' ', $text) ?? $text; // control characters
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n+/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
