<?php

declare(strict_types=1);

final class PdfReport
{
    private float $width = 595.28;
    private float $height = 841.89;
    private float $margin = 42.0;
    private float $cursorY = 0.0;
    private int $pageIndex = -1;
    private array $pages = [];
    private array $fonts = [
        'sans' => 'Helvetica',
        'sans_bold' => 'Helvetica-Bold',
        'serif' => 'Times-Roman',
        'serif_bold' => 'Times-Bold',
    ];

    public function __construct()
    {
        $this->addPage();
    }

    public function addTitle(string $text): void
    {
        $this->ensureSpace(42);
        $this->textLine($text, 18, 'serif_bold');
        $this->cursorY -= 2;
    }

    public function addSubtitle(string $text): void
    {
        $this->ensureSpace(24);
        $this->textLine($text, 10.5, 'sans');
        $this->cursorY -= 8;
    }

    public function addDivider(): void
    {
        $this->ensureSpace(16);
        $y = $this->cursorY;
        $this->raw(sprintf("0.82 0.84 0.88 RG %.2F %.2F m %.2F %.2F l S\n", $this->margin, $y, $this->width - $this->margin, $y));
        $this->cursorY -= 12;
    }

    public function addSpacer(float $height = 8.0): void
    {
        $this->ensureSpace($height);
        $this->cursorY -= $height;
    }

    public function addSection(string $title): void
    {
        $this->ensureSpace(28);
        $this->textLine($title, 12.5, 'sans_bold');
        $this->cursorY -= 3;
    }

    public function addSubsection(string $title): void
    {
        $this->ensureSpace(20);
        $this->textLine($title, 10.5, 'sans_bold');
        $this->cursorY -= 1;
    }

    public function addParagraph(string $text, float $fontSize = 10.0, string $fontKey = 'sans', float $after = 2.0): void
    {
        $maxWidth = $this->width - ($this->margin * 2);
        $lineHeight = $fontSize + 4.5;
        foreach ($this->wrapText($text, $maxWidth, $fontSize) as $line) {
            $this->ensureSpace($lineHeight);
            $this->textLine($line, $fontSize, $fontKey, $lineHeight);
        }
        $this->cursorY -= $after;
    }

    public function addChecklistItem(string $label, bool $checked): void
    {
        $fontSize = 9.7;
        $lineHeight = $fontSize + 4.5;
        $iconSize = 8.5;
        $iconGap = 8.0;
        $textX = $this->margin + $iconSize + $iconGap;
        $maxWidth = $this->width - $this->margin - $textX;
        $lines = $this->wrapText($label, $maxWidth, $fontSize);

        if ($lines === []) {
            $lines = [''];
        }

        foreach ($lines as $index => $line) {
            $this->ensureSpace($lineHeight + 1);
            $baselineY = $this->cursorY;
            if ($index === 0) {
                // Align icon vertically with text line (baseline-based centering).
                $iconBottomY = $baselineY + ($fontSize * 0.2) - ($iconSize / 2.0) + 0.8;
                $this->drawChecklistIcon($this->margin, $iconBottomY, $iconSize, $checked);
            }
            $this->textAt($textX, $baselineY, $line, $fontSize, 'sans');
            $this->cursorY -= $lineHeight;
        }

        $this->cursorY -= 1.5;
    }

    public function addStatusItem(string $label, string $value): void
    {
        $this->addParagraph($label . ': ' . $value, 9.8, 'sans', 1.5);
    }

    public function addSignature(array $strokes): void
    {
        $boxHeight = 84.0;
        $boxWidth = 230.0;
        $this->ensureSpace($boxHeight + 42);
        $x = $this->margin;
        $yBottom = $this->cursorY - $boxHeight;

        $this->raw("0.55 0.58 0.64 RG 1 w\n");
        $this->raw(sprintf("%.2F %.2F %.2F %.2F re S\n", $x, $yBottom, $boxWidth, $boxHeight));

        $normalized = $this->normalizeSignatureStrokes($strokes, $x, $yBottom, $boxWidth, $boxHeight);
        foreach ($normalized as $points) {
            $this->raw("0.12 0.14 0.18 RG 1.35 w\n");
            $first = array_shift($points);
            $this->raw(sprintf("%.2F %.2F m\n", $first['x'], $first['y']));
            foreach ($points as $point) {
                $this->raw(sprintf("%.2F %.2F l\n", $point['x'], $point['y']));
            }
            $this->raw("S\n");
        }

        $this->cursorY = $yBottom - 30;
    }

    public function output(): string
    {
        $objects = [];
        $pageObjectIds = [];
        $contentObjectIds = [];
        $fontObjectIds = [];
        $nextObjectId = 1;

        foreach ($this->fonts as $key => $fontName) {
            $fontObjectIds[$key] = $nextObjectId++;
            $objects[$fontObjectIds[$key]] = sprintf("<< /Type /Font /Subtype /Type1 /BaseFont /%s >>", $fontName);
        }

        foreach ($this->pages as $content) {
            $contentObjectIds[] = $nextObjectId++;
            $pageObjectIds[] = $nextObjectId++;
        }

        $pagesRootId = $nextObjectId++;
        $catalogId = $nextObjectId++;

        foreach ($this->pages as $index => $content) {
            $contentId = $contentObjectIds[$index];
            $pageId = $pageObjectIds[$index];
            $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R /F4 %d 0 R >> >> /Contents %d 0 R >>",
                $pagesRootId,
                $this->width,
                $this->height,
                $fontObjectIds['sans'],
                $fontObjectIds['sans_bold'],
                $fontObjectIds['serif'],
                $fontObjectIds['serif_bold'],
                $contentId
            );
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds));
        $objects[$pagesRootId] = sprintf("<< /Type /Pages /Count %d /Kids [%s] >>", count($pageObjectIds), $kids);
        $objects[$catalogId] = sprintf("<< /Type /Catalog /Pages %d 0 R >>", $pagesRootId);

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n";
        $pdf .= sprintf("<< /Size %d /Root %d 0 R >>\n", count($objects) + 1, $catalogId);
        $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    private function addPage(): void
    {
        $this->pages[] = '';
        $this->pageIndex++;
        $this->cursorY = $this->height - $this->margin;
    }

    private function textLine(string $text, float $fontSize, string $fontKey = 'sans', ?float $lineHeight = null): void
    {
        $this->textAt($this->margin, $this->cursorY, $text, $fontSize, $fontKey);
        $this->cursorY -= $lineHeight ?? ($fontSize + 4);
    }

    private function textAt(float $x, float $y, string $text, float $fontSize, string $fontKey = 'sans'): void
    {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], normalize_pdf_text($text));
        $fontMap = [
            'sans' => 'F1',
            'sans_bold' => 'F2',
            'serif' => 'F3',
            'serif_bold' => 'F4',
        ];
        $fontRef = $fontMap[$fontKey] ?? 'F1';
        $this->raw(sprintf("BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $fontRef, $fontSize, $x, $y, $safe));
    }

    private function raw(string $command): void
    {
        $this->pages[$this->pageIndex] .= $command;
    }

    private function ensureSpace(float $needed): void
    {
        if (($this->cursorY - $needed) <= $this->margin) {
            $this->addPage();
        }
    }

    private function wrapText(string $text, float $maxWidth, float $fontSize): array
    {
        $text = normalize_pdf_text($text);
        $paragraphs = preg_split("/\n+/", $text) ?: [''];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            if ($words === [] || $words === ['']) {
                $lines[] = '';
                continue;
            }

            $currentLine = '';
            foreach ($words as $word) {
                $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                if ($this->estimateWidth($candidate, $fontSize) <= $maxWidth) {
                    $currentLine = $candidate;
                    continue;
                }

                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    private function estimateWidth(string $text, float $fontSize): float
    {
        return strlen(normalize_pdf_text($text)) * ($fontSize * 0.5);
    }

    private function drawChecklistIcon(float $x, float $yBottom, float $size, bool $checked): void
    {
        $this->raw("0.38 0.43 0.50 RG 1 w\n");
        $this->raw(sprintf("%.2F %.2F %.2F %.2F re S\n", $x, $yBottom, $size, $size));

        if (!$checked) {
            return;
        }

        $this->raw("0.10 0.50 0.23 RG 1.35 w\n");
        $x1 = $x + ($size * 0.18);
        $y1 = $yBottom + ($size * 0.50);
        $x2 = $x + ($size * 0.42);
        $y2 = $yBottom + ($size * 0.24);
        $x3 = $x + ($size * 0.82);
        $y3 = $yBottom + ($size * 0.78);
        $this->raw(sprintf("%.2F %.2F m %.2F %.2F l %.2F %.2F l S\n", $x1, $y1, $x2, $y2, $x3, $y3));
    }

    private function normalizeSignatureStrokes(array $strokes, float $boxX, float $boxBottom, float $boxWidth, float $boxHeight): array
    {
        $rawStrokes = [];
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        foreach ($strokes as $stroke) {
            if (!is_array($stroke) || count($stroke) < 2) {
                continue;
            }

            $points = [];
            foreach ($stroke as $point) {
                if (!is_array($point) || !isset($point['x'], $point['y'])) {
                    continue;
                }

                $x = (float) $point['x'];
                $y = (float) $point['y'];
                $points[] = ['x' => $x, 'y' => $y];
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }

            if (count($points) > 1) {
                $rawStrokes[] = $points;
            }
        }

        if ($rawStrokes === [] || !is_finite($minX) || !is_finite($minY) || !is_finite($maxX) || !is_finite($maxY)) {
            return [];
        }

        $contentWidth = max(1.0, $maxX - $minX);
        $contentHeight = max(1.0, $maxY - $minY);
        $paddingX = 14.0;
        $paddingY = 12.0;
        $availableWidth = max(1.0, $boxWidth - ($paddingX * 2));
        $availableHeight = max(1.0, $boxHeight - ($paddingY * 2));
        $scale = min($availableWidth / $contentWidth, $availableHeight / $contentHeight);
        $offsetX = $boxX + (($boxWidth - ($contentWidth * $scale)) / 2.0);
        $offsetY = $boxBottom + (($boxHeight - ($contentHeight * $scale)) / 2.0);

        $normalized = [];
        foreach ($rawStrokes as $stroke) {
            $points = [];
            foreach ($stroke as $point) {
                $points[] = [
                    'x' => $offsetX + (($point['x'] - $minX) * $scale),
                    'y' => $boxBottom + $boxHeight - ($offsetY - $boxBottom) - (($point['y'] - $minY) * $scale),
                ];
            }
            $normalized[] = $points;
        }

        return $normalized;
    }
}
