<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/FormDefinition.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PdfReport.php';

Env::load(__DIR__ . '/../.env');

$database = new Database(storage_path('database/checklists.sqlite'));

function generate_submission_pdf(array $submission): string
{
    $pdf = new PdfReport();
    $pdf->addTitle('FLOOR CAPTAIN CONTROL SHEET');
    $pdf->addSubtitle('Daily Operational Control');
    $pdf->addParagraph('Tanggal: ' . ($submission['tanggal'] ?? '-'), 10, 'sans_bold', 1);
    $pdf->addParagraph('Floor Captain: ' . ($submission['floor_captain'] ?? '-'), 10, 'sans_bold', 6);
    $pdf->addDivider();

    $pdf->addSection('1. Opening Control');
    foreach (FormDefinition::openingSections() as $section) {
        $pdf->addSubsection($section['title']);
        $values = $submission['opening_checks'][$section['key']] ?? [];
        foreach ($section['items'] as $index => $item) {
            $pdf->addChecklistItem($item, !empty($values[$index]));
        }
        $pdf->addSpacer(6);
    }

    $pdf->addSection('2. Team Control');
    foreach (FormDefinition::teamControlItems() as $key => $label) {
        $pdf->addStatusItem($label, strtoupper((string) ($submission['team_control'][$key] ?? '-')));
    }

    $pdf->addSection('3. Service Control');
    foreach (FormDefinition::serviceControlItems() as $key => $label) {
        $pdf->addStatusItem($label, strtoupper((string) ($submission['service_control'][$key] ?? '-')));
    }

    $pdf->addSection('4. Floor Awareness');
    foreach (FormDefinition::floorAwarenessItems() as $key => $label) {
        $pdf->addStatusItem($label, strtoupper((string) ($submission['floor_awareness'][$key] ?? '-')));
    }

    $pdf->addSection('5. Customer Experience');
    $customer = $submission['customer_experience'] ?? [];
    $pdf->addStatusItem('Ada komplain tamu hari ini', strtoupper((string) ($customer['ada_komplain'] ?? '-')));
    $pdf->addParagraph('Jenis komplain: ' . ($customer['jenis_komplain'] ?? '-'));
    $pdf->addParagraph('Penanganan dilakukan oleh: ' . ($customer['ditangani_oleh'] ?? '-'));

    $pdf->addSection('6. Closing Control');
    foreach (FormDefinition::closingControlItems() as $key => $label) {
        $pdf->addChecklistItem($label, !empty($submission['closing_control'][$key]));
    }

    $notes = $submission['operational_notes'] ?? [];
    $pdf->addSection('Catatan Operasional');
    $pdf->addParagraph('Masalah hari ini: ' . ($notes['masalah_hari_ini'] ?? '-'));
    $pdf->addParagraph('Perbaikan yang dilakukan: ' . ($notes['perbaikan'] ?? '-'));

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
