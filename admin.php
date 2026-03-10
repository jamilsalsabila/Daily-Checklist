<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

$filterDate = trim((string) ($_GET['tanggal'] ?? ''));
$filterSearch = trim((string) ($_GET['q'] ?? ''));
$detailCode = trim((string) ($_GET['code'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$submissions = $database->listSubmissions($filterDate, $filterSearch);
$selected = $detailCode !== '' ? $database->findSubmissionByCode($detailCode) : null;
$waMessage = '';

if ($selected !== null) {
    $pdfUrl = app_base_url() . '/pdf.php?code=' . urlencode((string) $selected['submission_code']);
    $waMessage = rawurlencode(
        "Floor Captain Control Sheet\n" .
        "Tanggal: {$selected['tanggal']}\n" .
        "Floor Captain: {$selected['floor_captain']}\n" .
        "PDF: {$pdfUrl}"
    );
}

function opening_completion(array $submission): string
{
    $checked = 0;
    $total = 0;
    foreach ($submission['opening_checks'] ?? [] as $items) {
        foreach ($items as $value) {
            $total++;
            if (!empty($value)) {
                $checked++;
            }
        }
    }

    return $total > 0 ? $checked . '/' . $total : '0/0';
}

function section_completion(array $values): string
{
    $total = count($values);
    $checked = 0;
    foreach ($values as $value) {
        if (!empty($value)) {
            $checked++;
        }
    }

    return $checked . '/' . $total;
}
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
    <div class="page">
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

        <?php if ($status === 'updated'): ?>
            <div class="notice ok">Submission berhasil diperbarui.</div>
        <?php elseif ($status === 'deleted'): ?>
            <div class="notice ok">Submission berhasil dihapus.</div>
        <?php elseif ($status === 'delete_failed'): ?>
            <div class="notice bad">Gagal menghapus submission.</div>
        <?php endif; ?>

        <div class="layout">
            <section class="panel">
                <form class="filters" method="get">
                    <label class="filter-field">
                        <span>Pencarian</span>
                        <input type="text" name="q" value="<?= e($filterSearch) ?>" placeholder="Cari nama floor captain atau kode">
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

                <div class="list">
                    <?php if ($submissions === []): ?>
                        <div class="empty">Belum ada data yang cocok dengan filter.</div>
                    <?php endif; ?>

                    <?php foreach ($submissions as $item): ?>
                        <a class="item <?= $selected !== null && $selected['submission_code'] === $item['submission_code'] ? 'active' : '' ?>" href="?<?= http_build_query(['q' => $filterSearch, 'tanggal' => $filterDate, 'code' => $item['submission_code']]) ?>">
                            <div class="row">
                                <strong><?= e($item['floor_captain']) ?></strong>
                                <span class="code"><?= e($item['submission_code']) ?></span>
                            </div>
                            <div class="row muted">
                                <span>Tanggal checklist: <?= e($item['tanggal']) ?></span>
                                <span>Dibuat: <?= e(date('d M Y H:i', strtotime((string) $item['created_at']))) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="panel">
                <?php if ($selected === null): ?>
                    <div class="empty">Pilih salah satu submission untuk melihat detail lengkap.</div>
                <?php else: ?>
                    <div class="detail-grid">
                        <div class="detail-card">
                            <h3>Ringkasan</h3>
                            <p><strong>Floor Captain:</strong> <?= e($selected['floor_captain']) ?></p>
                            <p><strong>Tanggal:</strong> <?= e($selected['tanggal']) ?></p>
                            <div class="chips">
                                <span class="chip">Opening <?= e(opening_completion($selected)) ?></span>
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
                            <h3>Team Control</h3>
                            <?php foreach (FormDefinition::teamControlItems() as $key => $label): ?>
                                <p><strong><?= e($label) ?>:</strong> <?= e(strtoupper((string) ($selected['team_control'][$key] ?? '-'))) ?></p>
                            <?php endforeach; ?>
                        </div>

                        <div class="detail-card">
                            <h3>Opening Control</h3>
                            <ul>
                                <?php foreach (FormDefinition::openingSections() as $section): ?>
                                    <li><?= e($section['title']) ?>: <?= e(section_completion($selected['opening_checks'][$section['key']] ?? [])) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="detail-card">
                            <h3>Service & Floor Awareness</h3>
                            <?php foreach (FormDefinition::serviceControlItems() as $key => $label): ?>
                                <p><strong><?= e($label) ?>:</strong> <?= e(strtoupper((string) ($selected['service_control'][$key] ?? '-'))) ?></p>
                            <?php endforeach; ?>
                            <?php foreach (FormDefinition::floorAwarenessItems() as $key => $label): ?>
                                <p><strong><?= e($label) ?>:</strong> <?= e(strtoupper((string) ($selected['floor_awareness'][$key] ?? '-'))) ?></p>
                            <?php endforeach; ?>
                        </div>

                        <div class="detail-card">
                            <h3>Customer Experience</h3>
                            <p><strong>Status komplain:</strong> <?= e(strtoupper((string) ($selected['customer_experience']['ada_komplain'] ?? '-'))) ?></p>
                            <p><strong>Jenis komplain:</strong> <?= e($selected['customer_experience']['jenis_komplain'] ?? '-') ?></p>
                            <p><strong>Ditangani oleh:</strong> <?= e($selected['customer_experience']['ditangani_oleh'] ?? '-') ?></p>
                        </div>

                        <div class="detail-card">
                            <h3>Closing Control</h3>
                            <ul>
                                <?php foreach (FormDefinition::closingControlItems() as $key => $label): ?>
                                    <li><?= e($label) ?>: <?= !empty($selected['closing_control'][$key]) ? 'Ya' : 'Belum' ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="detail-card">
                            <h3>Catatan Operasional</h3>
                            <p><strong>Masalah hari ini:</strong> <?= e($selected['operational_notes']['masalah_hari_ini'] ?? '-') ?></p>
                            <p><strong>Perbaikan:</strong> <?= e($selected['operational_notes']['perbaikan'] ?? '-') ?></p>
                        </div>

                        <div class="detail-card">
                            <h3>Tanda Tangan</h3>
                            <img class="signature-preview" src="<?= e($selected['signature_preview']) ?>" alt="Signature preview">
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</body>
</html>
