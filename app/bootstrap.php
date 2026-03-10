<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/FormDefinition.php';
require_once __DIR__ . '/TemplateSchema.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PdfReport.php';

Env::load(__DIR__ . '/../.env');

$database = new Database(storage_path('database/checklists.sqlite'));

function generate_submission_pdf(array $submission): string
{
    global $database;
    $schema = TemplateSchema::fromSubmission($database, $submission);
    $responses = is_array($submission['responses'] ?? null) ? $submission['responses'] : [];

    $pdf = new PdfReport();
    $pdf->addTitle((string) ($schema['header']['title'] ?? 'CHECKLIST'));
    $pdf->addSubtitle((string) ($schema['header']['subtitle'] ?? ''));
    $pdf->addParagraph('Tanggal: ' . ($submission['tanggal'] ?? '-'), 10, 'sans_bold', 1);
    $pdf->addParagraph('Floor Captain: ' . ($submission['floor_captain'] ?? '-'), 10, 'sans_bold', 6);
    $pdf->addDivider();

    $renderPdfSection = static function (array $section, string $indexPrefix = '') use (&$renderPdfSection, $pdf, $responses): void {
        $sectionTitle = trim((string) ($section['title'] ?? ''));
        if ($sectionTitle !== '') {
            $title = $indexPrefix !== '' ? $indexPrefix . '. ' . $sectionTitle : $sectionTitle;
            $pdf->addSection($title);
        } else {
            $pdf->addSpacer(2);
        }

        foreach ((array) ($section['fields'] ?? []) as $field) {
            $fieldId = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? 'single_line_text');
            $label = (string) ($field['label'] ?? $fieldId);
            $value = $responses[$fieldId] ?? null;

            if ($type === 'checkbox') {
                $pdf->addChecklistItem($label, !empty($value));
                continue;
            }

            $pdf->addStatusItem($label, TemplateSchema::responseDisplayValue($field, $value));
        }

        $children = is_array($section['children'] ?? null) ? $section['children'] : [];
        foreach ($children as $childIndex => $childSection) {
            if (!is_array($childSection)) {
                continue;
            }
            $childPrefix = $indexPrefix !== ''
                ? $indexPrefix . '.' . ($childIndex + 1)
                : (string) ($childIndex + 1);
            $renderPdfSection($childSection, $childPrefix);
        }
        $pdf->addSpacer(6);
    };

    foreach ($schema['sections'] as $sectionIndex => $section) {
        $renderPdfSection($section, (string) ($sectionIndex + 1));
    }

    $pdf->addSection('Persetujuan');
    $pdf->addParagraph('Nama Floor Captain: ' . ($submission['floor_captain'] ?? '-'));
    $pdf->addSignature($submission['signature_strokes'] ?? []);

    return $pdf->output();
}

function save_submission_pdf(array $submission): string
{
    $path = pdf_file_path($submission['submission_code']);
    $bytes = file_put_contents($path, generate_submission_pdf($submission));
    if ($bytes === false) {
        throw new RuntimeException('Gagal menyimpan PDF ke storage.');
    }
    ensure_writable_file($path);
    return $path;
}
