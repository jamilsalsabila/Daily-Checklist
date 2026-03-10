<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$isAdminSession = admin_is_authenticated();
$requestedSlug = trim((string) ($_GET['template'] ?? ''));
$publishedTemplates = $database->listPublishedTemplates();
$selectedTemplate = null;

if ($requestedSlug !== '') {
    $selectedTemplate = $database->findTemplateBySlug($requestedSlug, true);
    if ($selectedTemplate === null) {
        http_response_code(404);
        echo 'Template checklist tidak ditemukan atau belum dipublikasikan.';
        exit;
    }
} elseif (count($publishedTemplates) === 1) {
    $selectedTemplate = $publishedTemplates[0];
}

$schema = $selectedTemplate !== null ? TemplateSchema::fromTemplate($selectedTemplate) : null;

function render_field_input(array $field, mixed $value = null): string
{
    $fieldId = (string) ($field['id'] ?? '');
    $type = (string) ($field['type'] ?? 'single_line_text');
    $required = !empty($field['required']) ? ' required' : '';
    $placeholder = e((string) ($field['placeholder'] ?? ''));
    $name = 'responses[' . $fieldId . ']';

    if ($type === 'long_text') {
        return '<textarea name="' . e($name) . '" placeholder="' . $placeholder . '"' . $required . '>' . e((string) $value) . '</textarea>';
    }

    if ($type === 'checkbox') {
        $checked = !empty($value) ? ' checked' : '';
        return '<input type="checkbox" name="' . e($name) . '" value="1"' . $checked . $required . '>';
    }

    if ($type === 'single_select') {
        $html = '<div class="status-options">';
        foreach ((array) ($field['options'] ?? []) as $option) {
            $optionText = (string) $option;
            $checked = (string) $value === $optionText ? ' checked' : '';
            $html .= '<label><input type="radio" name="' . e($name) . '" value="' . e($optionText) . '"' . $checked . $required . '> ' . e(ucwords(str_replace('_', ' ', $optionText))) . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    if ($type === 'multi_select') {
        $values = is_array($value) ? array_map('strval', $value) : [];
        $html = '<div class="status-options">';
        foreach ((array) ($field['options'] ?? []) as $option) {
            $optionText = (string) $option;
            $checked = in_array($optionText, $values, true) ? ' checked' : '';
            $html .= '<label><input type="checkbox" name="' . e($name) . '[]" value="' . e($optionText) . '"' . $checked . '> ' . e(ucwords(str_replace('_', ' ', $optionText))) . '</label>';
        }
        $html .= '</div>';
        return $html;
    }

    $inputType = match ($type) {
        'date' => 'date',
        'number' => 'number',
        'time' => 'time',
        default => 'text',
    };
    $defaultValue = $value;
    if ($defaultValue === null && $type === 'date' && str_contains($fieldId, 'tanggal')) {
        $defaultValue = date('Y-m-d');
    }
    return '<input type="' . $inputType . '" name="' . e($name) . '" value="' . e((string) $defaultValue) . '" placeholder="' . $placeholder . '"' . $required . '>';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) ($schema['header']['title'] ?? 'Checklist Harian')) ?></title>
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
            <?php if ($schema === null): ?>
                <h1>Pilih Checklist</h1>
                <p>Pilih checklist sesuai divisi kerja.</p>
            <?php else: ?>
                <h1<?= render_text_style_attr($schema['header']['title_style'] ?? []) ?>><?= e((string) ($schema['header']['title'] ?? 'Checklist Harian')) ?></h1>
                <p<?= render_text_style_attr($schema['header']['subtitle_style'] ?? []) ?>><?= e((string) ($schema['header']['subtitle'] ?? '')) ?></p>
            <?php endif; ?>
        </section>

        <?php if ($schema === null): ?>
            <section class="sheet">
                <?php if ($publishedTemplates === []): ?>
                    <p class="help">Belum ada template checklist yang dipublikasikan.</p>
                <?php else: ?>
                    <div class="template-picker">
                        <?php foreach ($publishedTemplates as $template): ?>
                            <a class="template-pick" href="?template=<?= urlencode((string) ($template['slug'] ?? '')) ?>">
                                <strong><?= e((string) ($template['name'] ?? '-')) ?></strong>
                                <span><?= e((string) ($template['description'] ?? '')) ?></span>
                                <em>/ ?template=<?= e((string) ($template['slug'] ?? '-')) ?></em>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <form action="submit.php" method="post" id="checklist-form">
                <input type="hidden" name="template_id" value="<?= e((string) ($selectedTemplate['id'] ?? '')) ?>">
                <input type="hidden" name="template_version_id" value="<?= e((string) ($selectedTemplate['current_version_id'] ?? '')) ?>">
                <input type="hidden" name="template_slug" value="<?= e((string) ($selectedTemplate['slug'] ?? '')) ?>">
                <?php foreach ($schema['sections'] as $section): ?>
                    <section class="sheet">
                        <div class="section-head">
                            <div>
                                <h2 class="section-title"<?= render_text_style_attr($section['title_style'] ?? []) ?>><?= e((string) ($section['title'] ?? 'Section')) ?></h2>
                            </div>
                        </div>
                        <div class="grid">
                            <?php foreach ($section['fields'] as $field): ?>
                                <?php $type = (string) ($field['type'] ?? 'single_line_text'); ?>
                                <?php if ($type === 'checkbox'): ?>
                                    <label class="check-item">
                                        <?= render_field_input($field) ?>
                                        <span<?= render_text_style_attr($field['label_style'] ?? []) ?>><?= e((string) ($field['label'] ?? '')) ?></span>
                                    </label>
                                <?php else: ?>
                                    <label class="meta">
                                        <span<?= render_text_style_attr($field['label_style'] ?? []) ?>><?= e((string) ($field['label'] ?? '')) ?></span>
                                        <?= render_field_input($field) ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>

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
        <?php endif; ?>
    </div>

    <?php if ($schema !== null): ?>
        <script src="assets/js/modules/signature-pad.js" defer></script>
    <?php endif; ?>
</body>
</html>
