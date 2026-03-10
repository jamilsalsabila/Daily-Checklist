<?php

declare(strict_types=1);

final class TemplateSchema
{
    private const ALLOWED_TYPES = [
        'single_line_text',
        'long_text',
        'checkbox',
        'multi_select',
        'single_select',
        'date',
        'number',
        'time',
    ];
    private const ALLOWED_FONT_FAMILIES = ['default', 'sans', 'serif', 'mono'];

    public static function defaults(): array
    {
        $sections = [];

        $sections[] = [
            'id' => 'meta',
            'title' => 'Informasi Utama',
            'fields' => [
                ['id' => 'meta_tanggal', 'type' => 'date', 'label' => 'Tanggal', 'required' => true],
                ['id' => 'meta_floor_captain', 'type' => 'single_line_text', 'label' => 'Floor Captain', 'required' => true],
            ],
        ];

        foreach (FormDefinition::openingSections() as $section) {
            $fields = [];
            foreach ($section['items'] as $index => $item) {
                $fields[] = [
                    'id' => 'opening_' . self::sanitizeKey((string) $section['key'], 'section') . '_' . ($index + 1),
                    'type' => 'checkbox',
                    'label' => (string) $item,
                    'required' => false,
                ];
            }

            $sections[] = [
                'id' => 'opening_' . self::sanitizeKey((string) $section['key'], 'section'),
                'title' => (string) $section['title'],
                'fields' => $fields,
            ];
        }

        $sections[] = self::yesNoSection('team_control', '2. Team Control', FormDefinition::teamControlItems(), 'team');
        $sections[] = self::yesNoSection('service_control', '3. Service Control', FormDefinition::serviceControlItems(), 'service');
        $sections[] = self::yesNoSection('floor_awareness', '4. Floor Awareness', FormDefinition::floorAwarenessItems(), 'awareness');

        $sections[] = [
            'id' => 'customer_experience',
            'title' => '5. Customer Experience',
            'fields' => [
                [
                    'id' => 'customer_ada_komplain',
                    'type' => 'single_select',
                    'label' => 'Ada komplain tamu hari ini',
                    'required' => true,
                    'options' => ['ada', 'tidak_ada'],
                ],
                ['id' => 'customer_jenis_komplain', 'type' => 'long_text', 'label' => 'Jika ada, jenis komplain', 'required' => false],
                ['id' => 'customer_ditangani_oleh', 'type' => 'long_text', 'label' => 'Penanganan dilakukan oleh', 'required' => false],
            ],
        ];

        $closingFields = [];
        foreach (FormDefinition::closingControlItems() as $key => $label) {
            $closingFields[] = [
                'id' => 'closing_' . self::sanitizeKey((string) $key, 'closing'),
                'type' => 'checkbox',
                'label' => (string) $label,
                'required' => false,
            ];
        }
        $sections[] = ['id' => 'closing_control', 'title' => '6. Closing Control', 'fields' => $closingFields];

        $sections[] = [
            'id' => 'operational_notes',
            'title' => 'Catatan Operasional',
            'fields' => [
                ['id' => 'notes_masalah_hari_ini', 'type' => 'long_text', 'label' => 'Masalah hari ini', 'required' => false],
                ['id' => 'notes_perbaikan', 'type' => 'long_text', 'label' => 'Perbaikan yang dilakukan', 'required' => false],
            ],
        ];

        return [
            'header' => [
                'title' => 'FLOOR CAPTAIN CONTROL SHEET',
                'subtitle' => 'Daily Operational Control',
                'title_style' => [],
                'subtitle_style' => [],
            ],
            'sections' => $sections,
        ];
    }

    public static function normalize(array $schema): array
    {
        $schema = self::supportsSections($schema) ? $schema : self::legacyToGenericSchema($schema);
        $defaults = self::defaults();
        $header = is_array($schema['header'] ?? null) ? $schema['header'] : [];
        $title = trim((string) ($header['title'] ?? $defaults['header']['title']));
        $subtitle = trim((string) ($header['subtitle'] ?? $defaults['header']['subtitle']));

        return [
            'header' => [
                'title' => $title !== '' ? $title : $defaults['header']['title'],
                'subtitle' => $subtitle !== '' ? $subtitle : $defaults['header']['subtitle'],
                'title_style' => self::normalizeTextStyle($header['title_style'] ?? []),
                'subtitle_style' => self::normalizeTextStyle($header['subtitle_style'] ?? []),
            ],
            'sections' => self::normalizeSections($schema['sections'] ?? $defaults['sections']),
        ];
    }

    public static function fromTemplate(?array $template): array
    {
        if ($template === null) {
            return self::defaults();
        }

        $schema = $template['schema'] ?? null;
        if (!is_array($schema)) {
            $decoded = json_decode((string) ($template['schema_json'] ?? ''), true);
            $schema = is_array($decoded) ? $decoded : [];
        }

        return self::normalize($schema);
    }

    public static function fromSubmission(Database $database, array $submission): array
    {
        $versionId = (int) ($submission['template_version_id'] ?? 0);
        if ($versionId > 0) {
            $version = $database->findTemplateVersionById($versionId);
            if ($version !== null) {
                return self::fromTemplate($version);
            }
        }

        return self::defaults();
    }

    public static function collectResponsesFromPost(array $schema, array $posted): array
    {
        $responses = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $fieldId = (string) $field['id'];
                $type = (string) $field['type'];
                $value = $posted[$fieldId] ?? null;

                switch ($type) {
                    case 'checkbox':
                        $responses[$fieldId] = $value !== null && $value !== '';
                        break;
                    case 'multi_select':
                        $items = is_array($value) ? $value : [];
                        $allowed = array_map('strval', (array) ($field['options'] ?? []));
                        $responses[$fieldId] = array_values(array_values(array_filter(
                            array_map('strval', $items),
                            static fn (string $item): bool => in_array($item, $allowed, true)
                        )));
                        break;
                    case 'number':
                        $raw = trim((string) $value);
                        $responses[$fieldId] = $raw === '' ? '' : $raw;
                        break;
                    default:
                        $responses[$fieldId] = trim((string) $value);
                        break;
                }
            }
        }

        return $responses;
    }

    public static function deriveMeta(array $schema, array $responses): array
    {
        $tanggal = '';
        $floorCaptain = '';

        foreach (self::flattenFields($schema) as $field) {
            $fieldId = (string) $field['id'];
            $type = (string) $field['type'];
            $label = strtolower((string) ($field['label'] ?? ''));
            $value = $responses[$fieldId] ?? null;

            if ($tanggal === '' && $type === 'date' && is_string($value) && $value !== '') {
                $tanggal = $value;
            }

            if (
                $floorCaptain === '' &&
                is_string($value) &&
                $value !== '' &&
                in_array($type, ['single_line_text', 'long_text'], true) &&
                (str_contains($label, 'floor captain') || str_contains($fieldId, 'floor_captain'))
            ) {
                $floorCaptain = $value;
            }
        }

        return [
            'tanggal' => $tanggal !== '' ? $tanggal : date('Y-m-d'),
            'floor_captain' => $floorCaptain !== '' ? $floorCaptain : '-',
        ];
    }

    public static function missingRequiredFields(array $schema, array $responses): array
    {
        $missing = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (empty($field['required'])) {
                    continue;
                }
                $fieldId = (string) ($field['id'] ?? '');
                if ($fieldId === '') {
                    continue;
                }
                $value = $responses[$fieldId] ?? null;
                $type = (string) ($field['type'] ?? 'single_line_text');
                if ($type === 'checkbox') {
                    if (!empty($value)) {
                        continue;
                    }
                    $missing[] = $fieldId;
                    continue;
                }
                if ($type === 'multi_select') {
                    if (is_array($value) && $value !== []) {
                        continue;
                    }
                    $missing[] = $fieldId;
                    continue;
                }
                if (trim((string) $value) === '') {
                    $missing[] = $fieldId;
                }
            }
        }

        return $missing;
    }

    public static function responsesFromLegacy(array $schema, array $submission): array
    {
        $responses = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $fieldId = (string) $field['id'];
                $responses[$fieldId] = self::legacyValueForField($submission, $field);
            }
        }
        return $responses;
    }

    public static function responseDisplayValue(array $field, mixed $value): string
    {
        $type = (string) ($field['type'] ?? 'single_line_text');
        if ($type === 'checkbox') {
            return !empty($value) ? 'Ya' : 'Tidak';
        }

        if ($type === 'multi_select') {
            if (!is_array($value) || $value === []) {
                return '-';
            }
            return implode(', ', array_map('strval', $value));
        }

        $text = trim((string) $value);
        return $text !== '' ? $text : '-';
    }

    public static function supportsSections(array $schema): bool
    {
        return isset($schema['sections']) && is_array($schema['sections']);
    }

    private static function yesNoSection(string $id, string $title, array $items, string $prefix): array
    {
        $fields = [];
        foreach ($items as $key => $label) {
            $fields[] = [
                'id' => $prefix . '_' . self::sanitizeKey((string) $key, $prefix),
                'type' => 'single_select',
                'label' => (string) $label,
                'required' => true,
                'options' => ['ya', 'tidak'],
                'label_style' => [],
            ];
        }
        return ['id' => $id, 'title' => $title, 'title_style' => [], 'fields' => $fields];
    }

    private static function normalizeSections(mixed $value): array
    {
        if (!is_array($value)) {
            return self::defaults()['sections'];
        }

        $sections = [];
        $seenIds = [];
        foreach ($value as $index => $sectionRaw) {
            if (!is_array($sectionRaw)) {
                continue;
            }

            $sectionId = self::sanitizeKey((string) ($sectionRaw['id'] ?? ''), 'section_' . ($index + 1));
            while (isset($seenIds[$sectionId])) {
                $sectionId .= '_' . ($index + 1);
            }
            $seenIds[$sectionId] = true;

            $title = trim((string) ($sectionRaw['title'] ?? ''));
            $description = trim((string) ($sectionRaw['description'] ?? ''));
            $fields = self::normalizeFields($sectionRaw['fields'] ?? [], $sectionId);
            if ($fields === []) {
                continue;
            }

            $sections[] = [
                'id' => $sectionId,
                'title' => $title !== '' ? $title : 'Section ' . ($index + 1),
                'description' => $description,
                'title_style' => self::normalizeTextStyle($sectionRaw['title_style'] ?? []),
                'fields' => $fields,
            ];
        }

        return $sections !== [] ? $sections : self::defaults()['sections'];
    }

    private static function normalizeFields(mixed $value, string $sectionId): array
    {
        if (!is_array($value)) {
            return [];
        }

        $fields = [];
        $seenIds = [];
        foreach ($value as $index => $fieldRaw) {
            if (!is_array($fieldRaw)) {
                continue;
            }

            $type = strtolower(trim((string) ($fieldRaw['type'] ?? 'single_line_text')));
            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'single_line_text';
            }

            $fieldId = self::sanitizeKey((string) ($fieldRaw['id'] ?? ''), $sectionId . '_field_' . ($index + 1));
            while (isset($seenIds[$fieldId])) {
                $fieldId .= '_' . ($index + 1);
            }
            $seenIds[$fieldId] = true;

            $label = trim((string) ($fieldRaw['label'] ?? ''));
            if ($label === '') {
                $label = 'Field ' . ($index + 1);
            }

            $field = [
                'id' => $fieldId,
                'type' => $type,
                'label' => $label,
                'required' => !empty($fieldRaw['required']),
                'placeholder' => trim((string) ($fieldRaw['placeholder'] ?? '')),
                'help' => trim((string) ($fieldRaw['help'] ?? '')),
                'label_style' => self::normalizeTextStyle($fieldRaw['label_style'] ?? []),
            ];

            if (in_array($type, ['single_select', 'multi_select'], true)) {
                $options = [];
                foreach ((array) ($fieldRaw['options'] ?? []) as $option) {
                    $text = trim((string) $option);
                    if ($text !== '') {
                        $options[] = $text;
                    }
                }
                if ($options === []) {
                    $options = ['opsi_1', 'opsi_2'];
                }
                $field['options'] = array_values(array_unique($options));
            }

            $fields[] = $field;
        }

        return $fields;
    }

    private static function flattenFields(array $schema): array
    {
        $fields = [];
        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    private static function legacyToGenericSchema(array $legacy): array
    {
        $defaults = self::defaults();
        $header = is_array($legacy['header'] ?? null) ? $legacy['header'] : $defaults['header'];

        $opening = is_array($legacy['opening_sections'] ?? null) ? $legacy['opening_sections'] : FormDefinition::openingSections();
        $team = is_array($legacy['team_control_items'] ?? null) ? $legacy['team_control_items'] : FormDefinition::teamControlItems();
        $service = is_array($legacy['service_control_items'] ?? null) ? $legacy['service_control_items'] : FormDefinition::serviceControlItems();
        $awareness = is_array($legacy['floor_awareness_items'] ?? null) ? $legacy['floor_awareness_items'] : FormDefinition::floorAwarenessItems();
        $closing = is_array($legacy['closing_control_items'] ?? null) ? $legacy['closing_control_items'] : FormDefinition::closingControlItems();

        $sections = [
            $defaults['sections'][0],
        ];

        foreach ($opening as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = self::sanitizeKey((string) ($section['key'] ?? ''), 'opening');
            $fields = [];
            foreach ((array) ($section['items'] ?? []) as $index => $item) {
                $fields[] = [
                    'id' => 'opening_' . $key . '_' . ($index + 1),
                    'type' => 'checkbox',
                    'label' => trim((string) $item),
                    'required' => false,
                ];
            }
            if ($fields === []) {
                continue;
            }
            $sections[] = [
                'id' => 'opening_' . $key,
                'title' => trim((string) ($section['title'] ?? 'Opening')),
                'title_style' => self::normalizeTextStyle($section['title_style'] ?? []),
                'fields' => $fields,
            ];
        }

        $sections[] = self::yesNoSection('team_control', '2. Team Control', $team, 'team');
        $sections[] = self::yesNoSection('service_control', '3. Service Control', $service, 'service');
        $sections[] = self::yesNoSection('floor_awareness', '4. Floor Awareness', $awareness, 'awareness');

        $sections[] = [
            'id' => 'customer_experience',
            'title' => '5. Customer Experience',
            'fields' => [
                ['id' => 'customer_ada_komplain', 'type' => 'single_select', 'label' => 'Ada komplain tamu hari ini', 'required' => true, 'options' => ['ada', 'tidak_ada']],
                ['id' => 'customer_jenis_komplain', 'type' => 'long_text', 'label' => 'Jika ada, jenis komplain', 'required' => false],
                ['id' => 'customer_ditangani_oleh', 'type' => 'long_text', 'label' => 'Penanganan dilakukan oleh', 'required' => false],
            ],
        ];

        $closingFields = [];
        foreach ($closing as $key => $label) {
            $closingFields[] = [
                'id' => 'closing_' . self::sanitizeKey((string) $key, 'closing'),
                'type' => 'checkbox',
                'label' => (string) $label,
                'required' => false,
            ];
        }
        $sections[] = ['id' => 'closing_control', 'title' => '6. Closing Control', 'fields' => $closingFields];

        $sections[] = [
            'id' => 'operational_notes',
            'title' => 'Catatan Operasional',
            'fields' => [
                ['id' => 'notes_masalah_hari_ini', 'type' => 'long_text', 'label' => 'Masalah hari ini', 'required' => false],
                ['id' => 'notes_perbaikan', 'type' => 'long_text', 'label' => 'Perbaikan yang dilakukan', 'required' => false],
            ],
        ];

        return [
            'header' => [
                'title' => trim((string) ($header['title'] ?? $defaults['header']['title'])),
                'subtitle' => trim((string) ($header['subtitle'] ?? $defaults['header']['subtitle'])),
                'title_style' => self::normalizeTextStyle($header['title_style'] ?? []),
                'subtitle_style' => self::normalizeTextStyle($header['subtitle_style'] ?? []),
            ],
            'sections' => $sections,
        ];
    }

    private static function normalizeTextStyle(mixed $style): array
    {
        if (!is_array($style)) {
            return [];
        }

        $normalized = [];
        $fontFamily = strtolower(trim((string) ($style['font_family'] ?? '')));
        if (in_array($fontFamily, self::ALLOWED_FONT_FAMILIES, true)) {
            $normalized['font_family'] = $fontFamily;
        }

        $fontSize = (int) ($style['font_size'] ?? 0);
        if ($fontSize >= 10 && $fontSize <= 56) {
            $normalized['font_size'] = $fontSize;
        }

        if (!empty($style['bold'])) {
            $normalized['bold'] = true;
        }
        if (!empty($style['italic'])) {
            $normalized['italic'] = true;
        }
        if (!empty($style['underline'])) {
            $normalized['underline'] = true;
        }

        $color = trim((string) ($style['color'] ?? ''));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
            $normalized['color'] = strtolower($color);
        }

        return $normalized;
    }

    private static function legacyValueForField(array $submission, array $field): mixed
    {
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            return '';
        }

        if ($fieldId === 'meta_tanggal') {
            return (string) ($submission['tanggal'] ?? '');
        }
        if ($fieldId === 'meta_floor_captain') {
            return (string) ($submission['floor_captain'] ?? '');
        }

        if (str_starts_with($fieldId, 'opening_')) {
            $parts = explode('_', $fieldId);
            $index = (int) array_pop($parts);
            $sectionKey = implode('_', array_slice($parts, 1));
            return !empty($submission['opening_checks'][$sectionKey][$index - 1]);
        }

        if (str_starts_with($fieldId, 'team_')) {
            $key = substr($fieldId, 5);
            return (string) ($submission['team_control'][$key] ?? '');
        }
        if (str_starts_with($fieldId, 'service_')) {
            $key = substr($fieldId, 8);
            return (string) ($submission['service_control'][$key] ?? '');
        }
        if (str_starts_with($fieldId, 'awareness_')) {
            $key = substr($fieldId, 10);
            return (string) ($submission['floor_awareness'][$key] ?? '');
        }
        if (str_starts_with($fieldId, 'closing_')) {
            $key = substr($fieldId, 8);
            return !empty($submission['closing_control'][$key]);
        }
        if ($fieldId === 'customer_ada_komplain') {
            return (string) ($submission['customer_experience']['ada_komplain'] ?? '');
        }
        if ($fieldId === 'customer_jenis_komplain') {
            return (string) ($submission['customer_experience']['jenis_komplain'] ?? '');
        }
        if ($fieldId === 'customer_ditangani_oleh') {
            return (string) ($submission['customer_experience']['ditangani_oleh'] ?? '');
        }
        if ($fieldId === 'notes_masalah_hari_ini') {
            return (string) ($submission['operational_notes']['masalah_hari_ini'] ?? '');
        }
        if ($fieldId === 'notes_perbaikan') {
            return (string) ($submission['operational_notes']['perbaikan'] ?? '');
        }

        return '';
    }

    private static function sanitizeKey(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        $value = trim($value, '_');
        return $value !== '' ? $value : $fallback;
    }
}
