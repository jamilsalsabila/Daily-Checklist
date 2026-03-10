<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$today = date('Y-m-d');
$isAdminSession = admin_is_authenticated();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Floor Captain Control Sheet</title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/panels.css">
    <link rel="stylesheet" href="assets/css/pages/index.css">
</head>
<body>
    <div class="container">
        <?php if ($isAdminSession): ?>
            <div class="topbar">
                <a href="admin.php">Kembali ke admin</a>
            </div>
        <?php endif; ?>
        <section class="hero">
            <h1>Floor Captain Control Sheet</h1>
        </section>

        <form action="submit.php" method="post" id="checklist-form">
            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Daily Operational Control</h2>
                    </div>
                </div>
                <div class="grid grid-2">
                    <label class="meta">
                        <span>Tanggal</span>
                        <input type="date" name="tanggal" required value="<?= e($today) ?>">
                    </label>
                    <label class="meta">
                        <span>Floor Captain</span>
                        <input type="text" name="floor_captain" required placeholder="Nama floor captain">
                    </label>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">1. Opening Control</h2>
                    </div>
                </div>
                <?php foreach (FormDefinition::openingSections() as $section): ?>
                    <h3 class="section-label"><?= e($section['title']) ?></h3>
                    <div class="card checklist">
                        <?php foreach ($section['items'] as $index => $item): ?>
                            <label class="check-item">
                                <input type="checkbox" name="opening_checks[<?= e($section['key']) ?>][<?= $index ?>]" value="1">
                                <span><?= e($item) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">2. Team Control</h2>
                    </div>
                </div>
                <div class="grid">
                    <?php foreach (FormDefinition::teamControlItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="team_control[<?= e($key) ?>]" value="ya" required> Ya</label>
                                <label><input type="radio" name="team_control[<?= e($key) ?>]" value="tidak" required> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">3. Service Control</h2>
                    </div>
                </div>
                <div class="grid">
                    <?php foreach (FormDefinition::serviceControlItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="service_control[<?= e($key) ?>]" value="ya" required> Ya</label>
                                <label><input type="radio" name="service_control[<?= e($key) ?>]" value="tidak" required> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">4. Floor Awareness</h2>
                    </div>
                </div>
                <div class="grid">
                    <?php foreach (FormDefinition::floorAwarenessItems() as $key => $label): ?>
                        <div class="status-row">
                            <strong><?= e($label) ?></strong>
                            <div class="status-options">
                                <label><input type="radio" name="floor_awareness[<?= e($key) ?>]" value="ya" required> Ya</label>
                                <label><input type="radio" name="floor_awareness[<?= e($key) ?>]" value="tidak" required> Tidak</label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">5. Customer Experience</h2>
                    </div>
                </div>
                <div class="grid">
                    <div class="status-row">
                        <strong>Ada komplain tamu hari ini</strong>
                        <div class="status-options">
                            <label><input type="radio" name="customer_experience[ada_komplain]" value="ada" required> Ada</label>
                            <label><input type="radio" name="customer_experience[ada_komplain]" value="tidak_ada" required> Tidak ada</label>
                        </div>
                    </div>
                    <label class="meta">
                        <span>Jika ada, jenis komplain</span>
                        <textarea name="customer_experience[jenis_komplain]" placeholder="Tulis jenis komplain"></textarea>
                    </label>
                    <label class="meta">
                        <span>Penanganan dilakukan oleh</span>
                        <textarea name="customer_experience[ditangani_oleh]" placeholder="Nama PIC atau tindakan"></textarea>
                    </label>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">6. Closing Control</h2>
                    </div>
                </div>
                <div class="card checklist">
                    <?php foreach (FormDefinition::closingControlItems() as $key => $label): ?>
                        <label class="check-item">
                            <input type="checkbox" name="closing_control[<?= e($key) ?>]" value="1">
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Catatan Operasional</h2>
                    </div>
                </div>
                <div class="grid">
                    <label class="meta">
                        <span>Masalah hari ini</span>
                        <textarea name="operational_notes[masalah_hari_ini]" placeholder="Tulis masalah hari ini"></textarea>
                    </label>
                    <label class="meta">
                        <span>Perbaikan yang dilakukan</span>
                        <textarea name="operational_notes[perbaikan]" placeholder="Tulis perbaikan yang dilakukan"></textarea>
                    </label>
                </div>
            </section>

            <section class="sheet">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Tanda Tangan</h2>
                    </div>
                </div>
                <div class="signature-box">
                    <canvas id="signature-pad" width="640" height="320"></canvas>
                    <div class="signature-actions">
                        <span class="help">Tanda tangan langsung di area ini. Wajib diisi sebelum submit.</span>
                        <button type="button" class="btn btn-secondary" id="clear-signature">Hapus tanda tangan</button>
                    </div>
                </div>
                <input type="hidden" name="signature_strokes" id="signature-strokes" required>
                <input type="hidden" name="signature_preview" id="signature-preview" required>
            </section>

            <div class="submit-wrap">
                <button class="btn btn-primary" type="submit">Submit checklist</button>
            </div>
        </form>
    </div>

    <script src="assets/js/modules/signature-pad.js" defer></script>
</body>
</html>
