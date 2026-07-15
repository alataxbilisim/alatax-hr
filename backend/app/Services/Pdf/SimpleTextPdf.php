<?php

namespace App\Services\Pdf;

/**
 * Bağımlılıksız basit metin PDF (Helvetica / WinAnsi).
 * Türkçe karakterler ASCII'ye yaklaştırılır.
 */
class SimpleTextPdf
{
    /**
     * @param  list<string>  $lines
     */
    public function render(string $title, array $lines): string
    {
        $content = "BT\n/F1 14 Tf\n50 780 Td\n(". $this->escape($this->ascii($title)) .") Tj\n";
        $content .= "/F1 10 Tf\n0 -24 Td\n";

        foreach ($lines as $line) {
            $safe = $this->escape($this->ascii($line));
            $content .= "0 -14 Td\n({$safe}) Tj\n";
        }

        $content .= "ET";

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            .'/Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj';
        $objects[] = '4 0 obj<< /Length '.strlen($content)." >>stream\n{$content}\nendstream endobj";
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj."\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= 'xref'."\n";
        $pdf .= '0 '.count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size '.count($offsets).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n".$xrefPos."\n%%EOF";

        return $pdf;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function ascii(string $text): string
    {
        $map = [
            'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G',
            'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O',
            'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U',
        ];

        return strtr($text, $map);
    }
}
