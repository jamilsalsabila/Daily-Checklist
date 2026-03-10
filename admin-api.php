<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

function admin_response_completion(array $schema, array $responses): string
{
    $answered = 0;
    $total = 0;
    foreach (TemplateSchema::allFields($schema) as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }
        $total++;
        $value = $responses[$fieldId] ?? null;
        if (is_array($value)) {
            if ($value !== []) {
                $answered++;
            }
            continue;
        }
        if ($value !== null && trim((string) $value) !== '' && $value !== false) {
            $answered++;
        }
    }

    return $total > 0 ? $answered . '/' . $total : '0/0';
}

function admin_build_state(Database $database, string $filterDate, string $filterSearch, string $detailCode, string $status = ''): array
{
    $templates = $database->listTemplates();
    $submissions = $database->listSubmissions($filterDate, $filterSearch);
    $selected = $detailCode !== '' ? $database->findSubmissionByCode($detailCode) : null;
    $selectedSchema = $selected !== null ? TemplateSchema::fromSubmission($database, $selected) : null;
    $templateNamesById = [];
    foreach ($templates as $template) {
        $templateNamesById[(int) $template['id']] = (string) ($template['name'] ?? 'Template');
    }

    $waUrl = '';
    if ($selected !== null) {
        $pdfUrl = app_base_url() . '/pdf.php?code=' . urlencode((string) $selected['submission_code']);
        $waMessage = rawurlencode(
            "Floor Captain Control Sheet\n" .
            "Tanggal: {$selected['tanggal']}\n" .
            "Floor Captain: {$selected['floor_captain']}\n" .
            "PDF: {$pdfUrl}"
        );
        $waUrl = 'https://wa.me/?text=' . $waMessage;
    }

    return [
        'status' => $status,
        'csrf_token' => csrf_token(),
        'filters' => [
            'tanggal' => $filterDate,
            'q' => $filterSearch,
            'code' => $detailCode,
        ],
        'templates' => $templates,
        'template_names' => $templateNamesById,
        'published_count' => count(array_filter($templates, static fn (array $row): bool => (int) ($row['is_active'] ?? 0) === 1)),
        'submissions' => $submissions,
        'selected' => $selected,
        'selected_schema' => $selectedSchema,
        'selected_completion' => $selected !== null && $selectedSchema !== null
            ? admin_response_completion($selectedSchema, $selected['responses'] ?? [])
            : '0/0',
        'selected_wa_url' => $waUrl,
    ];
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Sesi keamanan tidak valid.']);
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $filterDate = trim((string) ($_POST['tanggal'] ?? ''));
    $filterSearch = trim((string) ($_POST['q'] ?? ''));
    $detailCode = trim((string) ($_POST['code'] ?? ''));
    $status = '';

    try {
        if ($action === 'template_publish') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $database->activateTemplate($templateId);
            $status = 'template_published';
        } elseif ($action === 'template_unpublish') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $database->deactivateTemplate($templateId);
            $status = 'template_unpublished';
        } elseif ($action === 'template_delete') {
            $templateId = (int) ($_POST['template_id'] ?? 0);
            $deleted = $database->deleteTemplate($templateId);
            $status = $deleted ? 'template_deleted' : 'template_delete_failed';
        } elseif ($action === 'submission_delete') {
            $code = trim((string) ($_POST['submission_code'] ?? ''));
            $deleted = $database->deleteSubmissionByCode($code);
            $path = pdf_file_path($code);
            if (is_file($path)) {
                @unlink($path);
            }
            $status = $deleted ? 'deleted' : 'delete_failed';
            if ($detailCode === $code) {
                $detailCode = '';
            }
        } else {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Action tidak dikenali.']);
            exit;
        }
    } catch (Throwable $exception) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Terjadi error pada proses admin.']);
        exit;
    }

    echo json_encode(['ok' => true, 'state' => admin_build_state($database, $filterDate, $filterSearch, $detailCode, $status)]);
    exit;
}

$filterDate = trim((string) ($_GET['tanggal'] ?? ''));
$filterSearch = trim((string) ($_GET['q'] ?? ''));
$detailCode = trim((string) ($_GET['code'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

echo json_encode(['ok' => true, 'state' => admin_build_state($database, $filterDate, $filterSearch, $detailCode, $status)]);
