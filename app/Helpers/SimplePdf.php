<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Générateur PDF minimal (tableaux, A4 paysage, police Helvetica, accents français WinAnsi).
 * Sans dépendance : fonctionne sur tout hébergement PHP.
 */
final class SimplePdf
{
    private const W = 841.89;  // A4 paysage
    private const H = 595.28;
    private const M = 34;

    private array $pages = [];
    private string $cur = '';
    private float $y = 0;
    private string $title;
    private string $subtitle;
    private array $head = [];
    private array $widths = [];

    public function __construct(string $title, string $subtitle = '')
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->newPage();
    }

    /** @param array<int,string> $headers @param array<int,float> $relWidths proportions */
    public function table(array $headers, array $relWidths, array $rows): void
    {
        $usable = self::W - 2 * self::M;
        $sum = array_sum($relWidths);
        $this->widths = array_map(static fn($w) => $w / $sum * $usable, $relWidths);
        $this->head = $headers;
        $this->drawHeaderRow();
        $alt = false;
        foreach ($rows as $row) {
            // retour à la ligne : nombre de lignes max parmi les cellules
            $cells = [];
            $maxLines = 1;
            foreach ($row as $i => $cell) {
                $lines = $this->wrap((string)$cell, $this->widths[$i] - 8, 8);
                $cells[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $maxLines = min($maxLines, 4);
            $rowH = 6 + $maxLines * 10;
            if ($this->y - $rowH < self::M + 14) {
                $this->newPage();
                $this->drawHeaderRow();
            }
            if ($alt) {
                $this->rect(self::M, $this->y - $rowH, $usable, $rowH, '0.955 0.965 0.98');
            }
            $alt = !$alt;
            $x = self::M;
            foreach ($cells as $i => $lines) {
                $ly = $this->y - 10;
                foreach (array_slice($lines, 0, 4) as $line) {
                    $this->text($x + 4, $ly, $line, 8);
                    $ly -= 10;
                }
                $x += $this->widths[$i];
            }
            $this->y -= $rowH;
        }
        if (!$rows) {
            $this->text(self::M + 4, $this->y - 14, 'Aucun résultat pour ces filtres.', 9);
        }
    }

    public function output(string $filename): never
    {
        $this->closePage();
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = 4; // 3 = police ; pages dès 4
        $pageObjs = [];
        foreach ($this->pages as $i => $content) {
            $pageId = $n++;
            $contId = $n++;
            $kids[] = $pageId . ' 0 R';
            $pageObjs[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 ' . ($n + 0) . ' 0 R >> >> /Contents ' . $contId . ' 0 R >>';
            $pageObjs[$contId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $boldId = $n++;
        // le numéro F2 injecté ci-dessus pointait sur $n au moment de la page ; on le recale :
        foreach ($pageObjs as $id => $o) {
            $pageObjs[$id] = preg_replace('#/F2 \d+ 0 R#', '/F2 ' . $boldId . ' 0 R', $o);
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        foreach ($pageObjs as $id => $o) {
            $objs[$id] = $o;
        }
        $objs[$boldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objs);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objs));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
        exit;
    }

    // ---------------------------------------------------------------

    private function newPage(): void
    {
        $this->closePage();
        $this->cur = '';
        // Bandeau marine + filet rouge
        $this->rect(0, self::H - 48, self::W, 48, '0.043 0.165 0.369');
        $this->rect(0, self::H - 52, self::W, 4, '0.89 0.024 0.075');
        $this->text(self::M, self::H - 30, 'ZT CONTINENTAL TRANS', 14, true, '1 1 1');
        $this->text(self::W - self::M - 210, self::H - 30, 'Gestion de flotte', 10, false, '1 1 1');
        $this->text(self::M, self::H - 74, $this->title, 15, true, '0.043 0.165 0.369');
        $this->text(self::M, self::H - 90, $this->subtitle, 8.5, false, '0.35 0.4 0.47');
        $this->y = self::H - 104;
        $this->pageNo = count($this->pages) + 1;
        $this->text(self::W / 2 - 20, 18, 'Page ' . $this->pageNo, 8, false, '0.4 0.4 0.4');
        $this->text(self::M, 18, 'Généré le ' . date('d/m/Y à H:i'), 8, false, '0.4 0.4 0.4');
    }

    private int $pageNo = 1;

    private function closePage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
            $this->cur = '';
        }
    }

    private function drawHeaderRow(): void
    {
        $usable = self::W - 2 * self::M;
        $this->rect(self::M, $this->y - 18, $usable, 18, '0.043 0.165 0.369');
        $x = self::M;
        foreach ($this->head as $i => $h) {
            $this->text($x + 4, $this->y - 12.5, $h, 8, true, '1 1 1');
            $x += $this->widths[$i];
        }
        $this->y -= 18;
    }

    private function rect(float $x, float $y, float $w, float $h, string $rgb): void
    {
        $this->cur .= sprintf("%s rg %.2f %.2f %.2f %.2f re f\n", $rgb, $x, $y, $w, $h);
    }

    private function text(float $x, float $y, string $s, float $size, bool $bold = false, string $rgb = '0.06 0.12 0.25'): void
    {
        $this->cur .= sprintf("BT /%s %.1f Tf %s rg %.2f %.2f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb, $x, $y, $this->esc($s));
    }

    private function esc(string $s): string
    {
        $s = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s) ?: '';
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $s);
    }

    /** Découpe un texte pour tenir dans $width (approximation Helvetica ≈ 0,5 em). */
    private function wrap(string $text, float $width, float $size): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [''];
        }
        $perLine = max(4, (int)floor($width / ($size * 0.5)));
        return explode("\n", wordwrap($text, $perLine, "\n", true));
    }
}
