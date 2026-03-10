<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$code = (string) ($_GET['code'] ?? '');
$templateSlug = trim((string) ($_GET['template'] ?? ''));
$submission = $database->findSubmissionByCode($code);

if ($submission === null) {
    http_response_code(404);
    echo 'Data submission tidak ditemukan.';
    exit;
}

$pdfUrl = app_base_url() . '/pdf.php?code=' . urlencode($code);
$waMessage = rawurlencode(
    "Floor Captain Control Sheet\n" .
    "Tanggal: {$submission['tanggal']}\n" .
    "Floor Captain: {$submission['floor_captain']}\n" .
    "PDF: {$pdfUrl}"
);
$emailStatus = (string) ($_GET['email_status'] ?? '');
$emailError = trim((string) ($_GET['email_error'] ?? ''));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terima Kasih</title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/base/tokens.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/pages/thank-you.css">
</head>
<body>
    <main class="panel">
        <h1>Terima kasih</h1>
        <p>Checklist sudah tersimpan ke database dan PDF sudah dibuat.</p>
        <p class="meta">Kode submission: <strong><?= e($submission['submission_code']) ?></strong></p>
        <p class="meta">Tanggal: <?= e($submission['tanggal']) ?> | Floor Captain: <?= e($submission['floor_captain']) ?></p>

        <?php if ($emailStatus === 'sent'): ?>
            <div class="notice">Email berhasil dikirim.</div>
        <?php elseif ($emailStatus === 'not_configured'): ?>
            <div class="notice error">
                SMTP belum dikonfigurasi. Set di file `.env` atau environment server:
                `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`.
            </div>
        <?php elseif ($emailStatus === 'failed'): ?>
            <div class="notice error">Email gagal dikirim. Pastikan konfigurasi mail server di PHP/XAMPP sudah aktif.</div>
            <?php if ($emailError !== ''): ?>
                <div class="notice error">Detail: <?= e($emailError) ?></div>
            <?php endif; ?>
        <?php elseif ($emailStatus === 'invalid'): ?>
            <div class="notice error">Alamat email tidak valid.</div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn btn-primary" href="pdf.php?code=<?= urlencode($submission['submission_code']) ?>" target="_blank" rel="noopener">Lihat / Download PDF</a>
            <a class="btn btn-secondary" href="https://wa.me/?text=<?= $waMessage ?>" target="_blank" rel="noopener">Bagikan ke WhatsApp</a>
            <a class="btn btn-secondary" href="index.php<?= $templateSlug !== '' ? '?template=' . urlencode($templateSlug) : '' ?>">Isi form baru</a>
        </div>

        <form action="email.php" method="post">
            <input type="hidden" name="code" value="<?= e($submission['submission_code']) ?>">
            <input type="hidden" name="template" value="<?= e($templateSlug) ?>">
            <label>
                <span>Kirim PDF ke email</span>
                <input type="email" name="recipient_email" required placeholder="nama@email.com">
            </label>
            <button class="btn btn-primary" type="submit">Kirim email PDF</button>
        </form>
    </main>
</body>
</html>
