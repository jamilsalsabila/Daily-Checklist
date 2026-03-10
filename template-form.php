<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_admin_auth();

$idRaw = trim((string) ($_GET['id'] ?? ''));
$templateId = ctype_digit($idRaw) ? (int) $idRaw : null;
$status = trim((string) ($_GET['status'] ?? ''));

$template = $templateId !== null ? $database->findTemplateById($templateId) : null;
if ($templateId !== null && $template === null) {
    http_response_code(404);
    echo 'Template tidak ditemukan.';
    exit;
}

$schema = $template !== null
    ? TemplateSchema::fromTemplate($template)
    : [
        'header' => [
            'title' => '',
            'subtitle' => '',
            'title_style' => [],
            'subtitle_style' => [],
        ],
        'sections' => [],
    ];
$schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $template !== null ? 'Edit Template' : 'Template Baru' ?></title>
    <link rel="stylesheet" href="assets/css/base/global.css">
    <link rel="stylesheet" href="assets/css/base/tokens.css">
    <link rel="stylesheet" href="assets/css/components/buttons.css">
    <link rel="stylesheet" href="assets/css/components/forms.css">
    <link rel="stylesheet" href="assets/css/components/panels.css">
    <link rel="stylesheet" href="assets/css/pages/template-form.css">
</head>
<body>
    <div class="container">
        <div class="topbar">
            <a href="admin.php">Kembali ke admin</a>
        </div>

        <section class="hero">
            <h1><?= $template !== null ? 'Edit Template' : 'Template Baru' ?></h1>
        </section>

        <?php if ($status === 'invalid_json'): ?>
            <div class="notice bad">Schema JSON tidak valid.</div>
        <?php elseif ($status === 'invalid_input'): ?>
            <div class="notice bad">Nama, slug, dan schema wajib diisi.</div>
        <?php elseif ($status === 'slug_exists'): ?>
            <div class="notice bad">Slug sudah digunakan template lain.</div>
        <?php elseif ($status === 'save_failed'): ?>
            <div class="notice bad">Gagal menyimpan template.</div>
        <?php endif; ?>

        <form action="template-save.php" method="post" class="panel form-panel">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($template !== null): ?>
                <input type="hidden" name="template_id" value="<?= e((string) $template['id']) ?>">
            <?php endif; ?>

            <label class="meta">
                <span>Nama Template</span>
                <input type="text" id="template-name" name="name" required value="<?= e((string) ($template['name'] ?? '')) ?>">
            </label>

            <label class="meta">
                <span>Slug</span>
                <input type="text" id="template-slug" name="slug" required value="<?= e((string) ($template['slug'] ?? '')) ?>" placeholder="contoh: floor-captain-v2">
            </label>

            <label class="meta">
                <span>Deskripsi</span>
                <textarea name="description" rows="3"><?= e((string) ($template['description'] ?? '')) ?></textarea>
            </label>

            <section class="builder-card">
                <h2>Template Builder</h2>

                <div class="grid grid-2">
                    <label class="meta">
                        <span>Judul Form</span>
                        <input type="text" id="builder-title" value="<?= e((string) ($schema['header']['title'] ?? '')) ?>">
                    </label>
                    <label class="meta">
                        <span>Subjudul Form</span>
                        <input type="text" id="builder-subtitle" value="<?= e((string) ($schema['header']['subtitle'] ?? '')) ?>">
                    </label>
                </div>

                <div class="grid grid-2 style-grid">
                    <fieldset class="style-box">
                        <legend>Style Judul</legend>
                        <select id="header-title-font">
                            <option value="">Default</option>
                            <option value="sans" <?= (($schema['header']['title_style']['font_family'] ?? '') === 'sans') ? 'selected' : '' ?>>Sans</option>
                            <option value="serif" <?= (($schema['header']['title_style']['font_family'] ?? '') === 'serif') ? 'selected' : '' ?>>Serif</option>
                            <option value="mono" <?= (($schema['header']['title_style']['font_family'] ?? '') === 'mono') ? 'selected' : '' ?>>Mono</option>
                        </select>
                        <input type="number" id="header-title-size" min="10" max="56" value="<?= e((string) ($schema['header']['title_style']['font_size'] ?? '')) ?>" placeholder="Ukuran px">
                        <label class="toggle-row"><input type="checkbox" id="header-title-bold" <?= !empty($schema['header']['title_style']['bold']) ? 'checked' : '' ?>><span>Bold</span></label>
                        <label class="toggle-row"><input type="checkbox" id="header-title-italic" <?= !empty($schema['header']['title_style']['italic']) ? 'checked' : '' ?>><span>Italic</span></label>
                        <label class="toggle-row"><input type="checkbox" id="header-title-underline" <?= !empty($schema['header']['title_style']['underline']) ? 'checked' : '' ?>><span>Underline</span></label>
                        <input type="color" id="header-title-color" value="<?= e((string) ($schema['header']['title_style']['color'] ?? '#1c2430')) ?>">
                    </fieldset>
                    <fieldset class="style-box">
                        <legend>Style Subjudul</legend>
                        <select id="header-subtitle-font">
                            <option value="">Default</option>
                            <option value="sans" <?= (($schema['header']['subtitle_style']['font_family'] ?? '') === 'sans') ? 'selected' : '' ?>>Sans</option>
                            <option value="serif" <?= (($schema['header']['subtitle_style']['font_family'] ?? '') === 'serif') ? 'selected' : '' ?>>Serif</option>
                            <option value="mono" <?= (($schema['header']['subtitle_style']['font_family'] ?? '') === 'mono') ? 'selected' : '' ?>>Mono</option>
                        </select>
                        <input type="number" id="header-subtitle-size" min="10" max="56" value="<?= e((string) ($schema['header']['subtitle_style']['font_size'] ?? '')) ?>" placeholder="Ukuran px">
                        <label class="toggle-row"><input type="checkbox" id="header-subtitle-bold" <?= !empty($schema['header']['subtitle_style']['bold']) ? 'checked' : '' ?>><span>Bold</span></label>
                        <label class="toggle-row"><input type="checkbox" id="header-subtitle-italic" <?= !empty($schema['header']['subtitle_style']['italic']) ? 'checked' : '' ?>><span>Italic</span></label>
                        <label class="toggle-row"><input type="checkbox" id="header-subtitle-underline" <?= !empty($schema['header']['subtitle_style']['underline']) ? 'checked' : '' ?>><span>Underline</span></label>
                        <input type="color" id="header-subtitle-color" value="<?= e((string) ($schema['header']['subtitle_style']['color'] ?? '#667085')) ?>">
                    </fieldset>
                </div>

                <div class="builder-section">
                    <div class="builder-head">
                        <h3>Sections & Fields</h3>
                        <button class="btn btn-ghost btn-small" type="button" id="add-section">Tambah Section</button>
                    </div>
                    <div id="sections-list" class="builder-list"></div>
                </div>

                <div class="builder-section preview-panel">
                    <div class="builder-head">
                        <h3>Live Preview</h3>
                    </div>
                    <article class="preview-card">
                        <header class="preview-header">
                            <h4 id="preview-title">CHECKLIST</h4>
                            <p id="preview-subtitle">Daily Operational Control</p>
                        </header>
                        <div id="preview-body" class="preview-body"></div>
                    </article>
                </div>
            </section>

            <details class="advanced-json">
                <summary>Schema JSON (Advanced)</summary>
                <label class="meta">
                    <span>Schema JSON</span>
                    <textarea name="schema_json" id="schema-json" rows="24" class="code-area" required><?= e((string) $schemaJson) ?></textarea>
                </label>
                <button class="btn btn-ghost btn-small" type="button" id="apply-json-to-builder">Apply JSON ke Builder</button>
            </details>

            <label class="toggle-row">
                <input type="checkbox" name="publish_now" value="1" <?= $template === null ? 'checked' : '' ?>>
                <span>Publikasikan template setelah disimpan</span>
            </label>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Simpan Template</button>
                <a class="btn btn-ghost" href="admin.php">Batal</a>
            </div>
        </form>
    </div>
    <script id="initial-schema" type="application/json"><?= str_replace('</', '<\/', (string) $schemaJson) ?></script>
    <script src="assets/js/modules/template-builder.js" defer></script>
</body>
</html>
