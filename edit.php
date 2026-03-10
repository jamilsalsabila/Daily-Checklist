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

$schema = TemplateSchema::fromSubmission($database, $submission);
$responses = is_array($submission['responses'] ?? null) ? $submission['responses'] : [];

function render_edit_field_input(array $field, mixed $value = null): string
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
    return '<input type="' . $inputType . '" name="' . e($name) . '" value="' . e((string) $value) . '" placeholder="' . $placeholder . '"' . $required . '>';
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
                <p>Kode: <?= e($submission['submission_code']) ?>.</p>
            </div>
        </section>

        <form action="update.php" method="post">
            <input type="hidden" name="code" value="<?= e($submission['submission_code']) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <?php foreach ($schema['sections'] as $section): ?>
                <?php $sectionTitle = trim((string) ($section['title'] ?? '')); ?>
                <section class="sheet">
                    <?php if ($sectionTitle !== ''): ?>
                        <h2 class="section-title"<?= render_text_style_attr($section['title_style'] ?? []) ?>><?= e($sectionTitle) ?></h2>
                    <?php endif; ?>
                    <div class="grid">
                        <?php foreach ($section['fields'] as $field): ?>
                            <?php $type = (string) ($field['type'] ?? 'single_line_text'); ?>
                            <?php $value = $responses[$field['id']] ?? null; ?>
                            <?php if ($type === 'checkbox'): ?>
                                <label class="check-item">
                                    <?= render_edit_field_input($field, $value) ?>
                                    <span<?= render_text_style_attr($field['label_style'] ?? []) ?>><?= e((string) ($field['label'] ?? '')) ?></span>
                                </label>
                            <?php else: ?>
                                <label class="meta">
                                    <span<?= render_text_style_attr($field['label_style'] ?? []) ?>><?= e((string) ($field['label'] ?? '')) ?></span>
                                    <?= render_edit_field_input($field, $value) ?>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="submit-wrap">
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
            </div>
        </form>
    </div>
</body>
</html>
