<?php

namespace App\Services\Resume;

use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    /**
     * @throws \RuntimeException if the file can't be read or parsed
     */
    public function extract(string $path): string
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($path);
            $text = trim($pdf->getText());
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not parse PDF: {$e->getMessage()}");
        }

        if ($text === '') {
            throw new \RuntimeException(
                'PDF parsed but no text was extracted — it may be a scanned/image-only PDF. '
                . 'Try re-exporting it as a text-based PDF, or run it through OCR first.'
            );
        }

        // Collapse the excess whitespace PDF text extraction tends to leave behind.
        return preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
