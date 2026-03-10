<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

$code = trim((string) ($_GET['code'] ?? ''));
$submission = $database->findSubmissionByCode($code);

if ($submission === null) {
    http_response_code(404);
    echo 'Submission tidak ditemukan.';
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Submission</title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/base/tokens.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/panels.css">
    <link rel="stylesheet" href="assets/css/pages/edit.css">
</head>
<body>
    <div class="container">
        <div class="topbar">
            <a href="admin.php?code=<?= urlencode($submission['submission_code']) ?>">Kembali ke admin</a>
        </div>
        <section class="hero">
            <div>
                <h1>Edit Submission</h1>
                <p>Kode: <?= e($submission['submission_code']) ?>. Ubah data yang diperlukan, lalu simpan.</p>
            </div>
        </section>

        <form action="update.php" method="post">
            <input type="hidden" name="code" value="<?= e($submission['submission_code']) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <section class="sheet">
                <h2 class="section-title">Data Utama</h2>
                <div class="grid grid-2">
                    <label class="meta">
                        <span>Tanggal</span>
                        <input type="date" name="tanggal" required value="<?= e($submission['tanggal']) ?>">
                    </label>
                    <label class="meta">
                        <span>Floor Captain</span>
                        <input type="text" name="floor_captain" required value="<?= e($submission['floor_captain']) ?>">
                    </label>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Opening Control</h2>
                <?php foreach (FormDefinition::openingSections() as $section): ?>
                    <h3 class="section-label"><?= e($section['title']) ?></h3>
                    <div class="card checklist">
                        <?php foreach ($section['items'] as $index => $item): ?>
                            <label class="check-item">
                                <input type="checkbox" name="opening_checks[<?= e($section['key']) ?>][<?= $index ?>]" value="1" <?= !empty($submission['opening_checks'][$section['key']][$index]) ? 'checked' : '' ?>>
                                <span><?= e($item) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="sheet">
                <h2 class="section-title">Team Control</h2>
                <div class="grid">
                    <?php foreach (FormDefinition::teamControlItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="team_control[<?= e($key) ?>]" value="ya" required <?= (($submission['team_control'][$key] ?? '') === 'ya') ? 'checked' : '' ?>> Ya</label>
                                <label><input type="radio" name="team_control[<?= e($key) ?>]" value="tidak" required <?= (($submission['team_control'][$key] ?? '') === 'tidak') ? 'checked' : '' ?>> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Service Control</h2>
                <div class="grid">
                    <?php foreach (FormDefinition::serviceControlItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="service_control[<?= e($key) ?>]" value="ya" required <?= (($submission['service_control'][$key] ?? '') === 'ya') ? 'checked' : '' ?>> Ya</label>
                                <label><input type="radio" name="service_control[<?= e($key) ?>]" value="tidak" required <?= (($submission['service_control'][$key] ?? '') === 'tidak') ? 'checked' : '' ?>> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Floor Awareness</h2>
                <div class="grid">
                    <?php foreach (FormDefinition::floorAwarenessItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="floor_awareness[<?= e($key) ?>]" value="ya" required <?= (($submission['floor_awareness'][$key] ?? '') === 'ya') ? 'checked' : '' ?>> Ya</label>
                                <label><input type="radio" name="floor_awareness[<?= e($key) ?>]" value="tidak" required <?= (($submission['floor_awareness'][$key] ?? '') === 'tidak') ? 'checked' : '' ?>> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Customer Experience</h2>
                <div class="grid">
                    <div class="status-row">
                        <strong>Ada komplain tamu hari ini</strong>
                        <div class="status-options">
                            <label><input type="radio" name="customer_experience[ada_komplain]" value="ada" required <?= (($submission['customer_experience']['ada_komplain'] ?? '') === 'ada') ? 'checked' : '' ?>> Ada</label>
                            <label><input type="radio" name="customer_experience[ada_komplain]" value="tidak_ada" required <?= (($submission['customer_experience']['ada_komplain'] ?? '') === 'tidak_ada') ? 'checked' : '' ?>> Tidak ada</label>
                        </div>
                    </div>
                    <label class="meta">
                        <span>Jenis komplain</span>
                        <textarea name="customer_experience[jenis_komplain]"><?= e((string) ($submission['customer_experience']['jenis_komplain'] ?? '')) ?></textarea>
                    </label>
                    <label class="meta">
                        <span>Ditangani oleh</span>
                        <textarea name="customer_experience[ditangani_oleh]"><?= e((string) ($submission['customer_experience']['ditangani_oleh'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Closing Control</h2>
                <div class="card checklist">
                    <?php foreach (FormDefinition::closingControlItems() as $key => $label): ?>
                        <label class="check-item">
                            <input type="checkbox" name="closing_control[<?= e($key) ?>]" value="1" <?= !empty($submission['closing_control'][$key]) ? 'checked' : '' ?>>
                            <span><?= e($label) ?> (Ya)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <h2 class="section-title">Catatan Operasional</h2>
                <div class="grid">
                    <label class="meta">
                        <span>Masalah hari ini</span>
                        <textarea name="operational_notes[masalah_hari_ini]"><?= e((string) ($submission['operational_notes']['masalah_hari_ini'] ?? '')) ?></textarea>
                    </label>
                    <label class="meta">
                        <span>Perbaikan</span>
                        <textarea name="operational_notes[perbaikan]"><?= e((string) ($submission['operational_notes']['perbaikan'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>

            <div class="submit-wrap">
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
            </div>
        </form>
    </div>
</body>
</html>
