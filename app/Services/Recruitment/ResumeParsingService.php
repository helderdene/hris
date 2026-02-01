<?php

namespace App\Services\Recruitment;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Service for extracting text content from resume files (PDF and DOCX).
 */
class ResumeParsingService
{
    /**
     * Parse an uploaded resume file and return extracted text.
     */
    public function parseFile(UploadedFile $file): string
    {
        $mimeType = $file->getMimeType();

        return match (true) {
            $mimeType === 'application/pdf' => $this->parsePdf($file),
            in_array($mimeType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword',
            ]) => $this->parseDocx($file),
            default => '',
        };
    }

    /**
     * Extract text from a PDF file.
     */
    private function parsePdf(UploadedFile $file): string
    {
        try {
            $parser = new PdfParser;
            $document = $parser->parseFile($file->getRealPath());

            return trim($document->getText());
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Extract text from a DOCX file.
     */
    private function parseDocx(UploadedFile $file): string
    {
        $phpWord = PhpWordIOFactory::load($file->getRealPath());
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $elementText = $element->getText();
                    if (is_string($elementText)) {
                        $text .= $elementText."\n";
                    }
                }
            }
        }

        return trim($text);
    }
}
