<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

$filterDate = trim((string) ($_GET['tanggal'] ?? ''));
$filterSearch = trim((string) ($_GET['q'] ?? ''));
$detailCode = trim((string) ($_GET['code'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$templates = $database->listTemplates();
$publishedTemplates = array_values(array_filter($templates, static fn (array $row): bool => (int) ($row['is_active'] ?? 0) === 1));
$templateNamesById = [];
foreach ($templates as $template) {
    $templateNamesById[(int) $template['id']] = (string) ($template['name'] ?? 'Template');
}

$submissions = $database->listSubmissions($filterDate, $filterSearch);
$selected = $detailCode !== '' ? $database->findSubmissionByCode($detailCode) : null;
$selectedSchema = $selected !== null ? TemplateSchema::fromSubmission($database, $selected) : null;
$waMessage = '';

if ($selected !== null) {
    $pdfUrl = app_base_url() . '/pdf.php?code=' . urlencode((string) $selected['submission_code']);
    $waMessage = rawurlencode(
        "Daily Checklist\n" .
        "Tanggal: {$selected['tanggal']}\n" .
        "Nama: {$selected['nama']}\n" .
        "PDF: {$pdfUrl}"
    );
}

function response_completion(array $schema, array $responses): string
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

function render_admin_section_detail(array $section, array $responses, int $depth = 0): string
{
    $html = '';
    $title = trim((string) ($section['title'] ?? ''));
    $headingLevel = min(6, 4 + $depth);
    if ($title !== '') {
        $html .= '<h' . $headingLevel . render_text_style_attr($section['title_style'] ?? []) . '>' . e($title) . '</h' . $headingLevel . '>';
    }
    foreach ((array) ($section['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $value = $responses[$field['id']] ?? null;
        $html .= '<p><strong' . render_text_style_attr($field['label_style'] ?? []) . '>'
            . e((string) ($field['label'] ?? '')) . ':</strong> '
            . e(TemplateSchema::responseDisplayValue($field, $value))
            . '</p>';
    }
    foreach ((array) ($section['children'] ?? []) as $child) {
        if (is_array($child)) {
            $html .= render_admin_section_detail($child, $responses, $depth + 1);
        }
    }
    return $html;
}

$initialState = [
    'status' => $status,
    'csrf_token' => csrf_token(),
    'filters' => [
        'tanggal' => $filterDate,
        'q' => $filterSearch,
        'code' => $detailCode,
    ],
    'templates' => $templates,
    'template_names' => $templateNamesById,
    'published_count' => count($publishedTemplates),
    'submissions' => $submissions,
    'selected' => $selected,
    'selected_schema' => $selectedSchema,
    'selected_completion' => $selected !== null && $selectedSchema !== null
        ? response_completion($selectedSchema, $selected['responses'] ?? [])
        : '0/0',
    'selected_wa_url' => $waMessage !== '' ? 'https://wa.me/?text=' . $waMessage : '',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Checklist</title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/base/tokens.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/panels.css">
    <link rel="stylesheet" href="assets/css/pages/admin.css">
</head>
<body>
    <div class="page" id="admin-app">
        <header class="header">
            <div>
                <h1>Admin Checklist</h1>
            </div>
            <div class="header-actions">
                <a href="index.php">Buka form checklist</a>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-logout" type="submit">Logout</button>
                </form>
            </div>
        </header>

        <div id="admin-notice">
            <?php if ($status === 'updated'): ?>
                <div class="notice ok">Submission berhasil diperbarui.</div>
            <?php elseif ($status === 'deleted'): ?>
                <div class="notice ok">Submission berhasil dihapus.</div>
            <?php elseif ($status === 'delete_failed'): ?>
                <div class="notice bad">Gagal menghapus submission.</div>
            <?php elseif ($status === 'template_published'): ?>
                <div class="notice ok">Template berhasil dipublikasikan.</div>
            <?php elseif ($status === 'template_unpublished'): ?>
                <div class="notice ok">Template berhasil dinonaktifkan dari publikasi.</div>
            <?php elseif ($status === 'template_activate_failed'): ?>
                <div class="notice bad">Gagal mengubah status publikasi template.</div>
            <?php elseif ($status === 'csrf_error'): ?>
                <div class="notice bad">Sesi keamanan tidak valid. Silakan ulangi.</div>
            <?php elseif ($status === 'template_saved'): ?>
                <div class="notice ok">Template berhasil disimpan.</div>
            <?php elseif ($status === 'template_deleted'): ?>
                <div class="notice ok">Template berhasil dihapus.</div>
            <?php elseif ($status === 'template_delete_failed'): ?>
                <div class="notice bad">Template tidak bisa dihapus (masih dipublikasikan atau sudah dipakai submission).</div>
            <?php elseif ($status === 'template_save_failed'): ?>
                <div class="notice bad">Gagal menyimpan template. Cek data input.</div>
            <?php endif; ?>
        </div>

        <section class="panel template-panel" id="template-panel">
            <div class="template-panel-head">
                <h2>Checklist Template</h2>
                <div class="template-head-actions">
                    <?php if ($publishedTemplates !== []): ?>
                        <span class="chip">Published: <?= e((string) count($publishedTemplates)) ?> template</span>
                    <?php endif; ?>
                    <a class="btn btn-edit" href="template-form.php">Template baru</a>
                </div>
            </div>
            <div class="template-list">
                <?php foreach ($templates as $template): ?>
                    <?php $isPublished = (int) ($template['is_active'] ?? 0) === 1; ?>
                    <article class="template-item<?= $isPublished ? ' active' : '' ?>">
                        <div class="template-main">
                            <strong><?= e((string) ($template['name'] ?? '-')) ?></strong>
                            <span class="code"><?= e((string) ($template['slug'] ?? '-')) ?></span>
                            <span class="muted">Versi: v<?= e((string) ($template['current_version'] ?? '-')) ?></span>
                            <?php if ($isPublished): ?>
                                <a class="muted template-link" target="_blank" rel="noopener" href="index.php?template=<?= urlencode((string) ($template['slug'] ?? '')) ?>">Form URL: /?template=<?= e((string) ($template['slug'] ?? '')) ?></a>
                            <?php endif; ?>
                        </div>
                        <div class="template-action">
                            <div class="template-button-row">
                                <?php if ($isPublished): ?>
                                    <form action="template-activate.php" method="post">
                                        <input type="hidden" name="template_id" value="<?= e((string) ($template['id'] ?? '')) ?>">
                                        <input type="hidden" name="action" value="unpublish">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="btn btn-ghost btn-small" type="submit">Nonaktifkan</button>
                                    </form>
                                <?php else: ?>
                                    <form action="template-activate.php" method="post">
                                        <input type="hidden" name="template_id" value="<?= e((string) ($template['id'] ?? '')) ?>">
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="btn btn-edit" type="submit">Publikasikan</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$isPublished): ?>
                                    <form action="template-delete.php" method="post" onsubmit="return confirm('Hapus template ini?');">
                                        <input type="hidden" name="template_id" value="<?= e((string) ($template['id'] ?? '')) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="btn btn-delete" type="submit">Hapus</button>
                                    </form>
                                <?php endif; ?>

                                <a class="btn btn-ghost btn-small" href="template-form.php?id=<?= urlencode((string) ($template['id'] ?? '')) ?>">Edit</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="layout">
            <section class="panel">
                <form class="filters" method="get" id="admin-filters">
                    <label class="filter-field">
                        <span>Pencarian</span>
                        <input type="text" name="q" value="<?= e($filterSearch) ?>" placeholder="Cari nama ...">
                    </label>
                    <label class="filter-field">
                        <span>Tanggal</span>
                        <input type="date" name="tanggal" value="<?= e($filterDate) ?>">
                    </label>
                    <div class="filter-actions">
                        <button type="submit">Filter</button>
                        <a class="btn btn-ghost" href="admin.php">Reset</a>
                    </div>
                </form>

                <div class="list" id="submission-list">
                    <?php if ($submissions === []): ?>
                        <div class="empty">Belum ada data yang cocok dengan filter.</div>
                    <?php endif; ?>

                    <?php foreach ($submissions as $item): ?>
                        <a class="item <?= $selected !== null && $selected['submission_code'] === $item['submission_code'] ? 'active' : '' ?>" href="?<?= http_build_query(['q' => $filterSearch, 'tanggal' => $filterDate, 'code' => $item['submission_code']]) ?>">
                            <div class="row">
                                <strong><?= e($item['nama']) ?></strong>
                                <span class="code"><?= e($item['submission_code']) ?></span>
                            </div>
                            <div class="row muted">
                                <span>Tanggal checklist: <?= e($item['tanggal']) ?></span>
                                <span>Dibuat: <?= e(date('d M Y H:i', strtotime((string) $item['created_at']))) ?></span>
                            </div>
                            <div class="row muted">
                                <span>Template: <?= e($templateNamesById[(int) ($item['template_id'] ?? 0)] ?? '-') ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="panel" id="submission-detail">
                <?php if ($selected === null): ?>
                    <div class="empty">Pilih salah satu submission untuk melihat detail lengkap.</div>
                <?php else: ?>
                    <div class="detail-grid">
                        <div class="detail-card">
                            <h3>Ringkasan</h3>
                            <p><strong>Nama:</strong> <?= e($selected['nama']) ?></p>
                            <p><strong>Tanggal:</strong> <?= e($selected['tanggal']) ?></p>
                            <p><strong>Template:</strong> <?= e($templateNamesById[(int) ($selected['template_id'] ?? 0)] ?? '-') ?></p>
                            <div class="chips">
                                <span class="chip">Terisi <?= e(response_completion($selectedSchema ?? ['sections' => []], $selected['responses'] ?? [])) ?></span>
                                <span class="chip">Kode <?= e($selected['submission_code']) ?></span>
                            </div>
                            <p>
                                <a href="pdf.php?code=<?= urlencode($selected['submission_code']) ?>" target="_blank" rel="noopener">Buka PDF</a>
                            </p>
                            <div class="action-row">
                                <a class="btn btn-edit" href="edit.php?code=<?= urlencode($selected['submission_code']) ?>">Edit</a>
                                <a class="btn btn-wa" href="https://wa.me/?text=<?= $waMessage ?>" target="_blank" rel="noopener">Send to WhatsApp</a>
                                <form action="delete.php" method="post" onsubmit="return confirm('Hapus submission ini?');">
                                    <input type="hidden" name="code" value="<?= e($selected['submission_code']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="btn btn-delete" type="submit">Hapus</button>
                                </form>
                            </div>
                        </div>

                        <div class="detail-card">
                            <h3>Isi Form</h3>
                            <?php foreach (($selectedSchema['sections'] ?? []) as $section): ?>
                                <?= render_admin_section_detail($section, $selected['responses'] ?? []) ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if (trim((string) ($selected['signature_preview'] ?? '')) !== ''): ?>
                            <div class="detail-card">
                                <h3>Tanda Tangan</h3>
                                <img class="signature-preview" src="<?= e($selected['signature_preview']) ?>" alt="Signature preview">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
    <script id="admin-initial-state" type="application/json"><?= str_replace('</', '<\/', json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>
    <script src="assets/js/modules/admin-spa.js" defer></script>
</body>
</html>
