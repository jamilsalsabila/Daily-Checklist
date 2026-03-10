(() => {
    const form = document.querySelector('form[action="template-save.php"]');
    const schemaField = document.getElementById('schema-json');
    const initialSchemaEl = document.getElementById('initial-schema');
    const titleInput = document.getElementById('builder-title');
    const subtitleInput = document.getElementById('builder-subtitle');
    const sectionsList = document.getElementById('sections-list');
    const previewTitleEl = document.getElementById('preview-title');
    const previewSubtitleEl = document.getElementById('preview-subtitle');
    const previewBodyEl = document.getElementById('preview-body');
    const templateNameInput = document.getElementById('template-name');
    const templateSlugInput = document.getElementById('template-slug');

    if (!form || !schemaField || !initialSchemaEl || !sectionsList || !titleInput || !subtitleInput) {
        return;
    }

    const headerTitleStyleControls = {
        font: document.getElementById('header-title-font'),
        size: document.getElementById('header-title-size'),
        bold: document.getElementById('header-title-bold'),
        italic: document.getElementById('header-title-italic'),
        underline: document.getElementById('header-title-underline'),
        color: document.getElementById('header-title-color'),
    };
    const headerSubtitleStyleControls = {
        font: document.getElementById('header-subtitle-font'),
        size: document.getElementById('header-subtitle-size'),
        bold: document.getElementById('header-subtitle-bold'),
        italic: document.getElementById('header-subtitle-italic'),
        underline: document.getElementById('header-subtitle-underline'),
        color: document.getElementById('header-subtitle-color'),
    };

    const fieldTypes = [
        { value: 'single_line_text', label: 'Single Line Text' },
        { value: 'long_text', label: 'Long Text' },
        { value: 'checkbox', label: 'Checkbox' },
        { value: 'multi_select', label: 'Multiple Select' },
        { value: 'single_select', label: 'Single Select' },
        { value: 'date', label: 'Date' },
        { value: 'number', label: 'Number' },
        { value: 'time', label: 'Time' },
    ];
    const fontOptions = [
        { value: '', label: 'Default' },
        { value: 'sans', label: 'Sans' },
        { value: 'serif', label: 'Serif' },
        { value: 'mono', label: 'Mono' },
    ];

    const slugify = (value, fallback = 'item') => {
        const key = String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
        return key || fallback;
    };

    const hyphenSlug = (value, fallback = 'template') => {
        const slug = String(value || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        return slug || fallback;
    };

    const btn = (text, className = 'btn btn-ghost btn-small') => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = className;
        el.textContent = text;
        return el;
    };

    const textInput = (value, placeholder, className = '') => {
        const el = document.createElement('input');
        el.type = 'text';
        el.value = value || '';
        el.placeholder = placeholder || '';
        if (className) el.className = className;
        return el;
    };

    const optionsInputToArray = (value) =>
        String(value || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean);

    const cleanStyle = (style = {}) => {
        const output = {};
        const font = String(style.font_family || '').trim();
        if (['sans', 'serif', 'mono', 'default'].includes(font)) {
            output.font_family = font;
        }
        const size = Number(style.font_size || 0);
        if (size >= 10 && size <= 56) {
            output.font_size = size;
        }
        if (style.bold) output.bold = true;
        if (style.italic) output.italic = true;
        if (style.underline) output.underline = true;
        if (/^#[0-9A-Fa-f]{6}$/.test(String(style.color || ''))) {
            output.color = String(style.color).toLowerCase();
        }
        return output;
    };

    const readStyleControls = (controls) => cleanStyle({
        font_family: controls.font?.value || '',
        font_size: controls.size?.value || '',
        bold: controls.bold?.checked || false,
        italic: controls.italic?.checked || false,
        underline: controls.underline?.checked || false,
        color: controls.color?.value || '',
    });

    const writeStyleControls = (controls, style = {}, defaultColor = '#1c2430') => {
        if (!controls.font || !controls.size || !controls.bold || !controls.italic || !controls.underline || !controls.color) {
            return;
        }
        controls.font.value = style.font_family || '';
        controls.size.value = style.font_size || '';
        controls.bold.checked = Boolean(style.bold);
        controls.italic.checked = Boolean(style.italic);
        controls.underline.checked = Boolean(style.underline);
        controls.color.value = style.color || defaultColor;
    };

    const styleToCss = (style = {}) => {
        const normalized = cleanStyle(style);
        const fontMap = {
            sans: '"Avenir Next", Avenir, "Segoe UI", sans-serif',
            serif: '"Iowan Old Style", Georgia, serif',
            mono: '"Courier New", ui-monospace, monospace',
            default: 'inherit',
        };
        const css = [];
        if (normalized.font_family && fontMap[normalized.font_family]) {
            css.push(`font-family:${fontMap[normalized.font_family]}`);
        }
        if (normalized.font_size) css.push(`font-size:${normalized.font_size}px`);
        if (normalized.bold) css.push('font-weight:700');
        if (normalized.italic) css.push('font-style:italic');
        if (normalized.underline) css.push('text-decoration:underline');
        if (normalized.color) css.push(`color:${normalized.color}`);
        return css.join(';');
    };

    const createStyleEditor = (style = {}) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'style-inline';
        wrapper.dataset.field = 'style';

        const fontSelect = document.createElement('select');
        fontSelect.dataset.style = 'font_family';
        fontOptions.forEach((font) => {
            const option = document.createElement('option');
            option.value = font.value;
            option.textContent = font.label;
            if ((style.font_family || '') === font.value) {
                option.selected = true;
            }
            fontSelect.appendChild(option);
        });

        const sizeInput = document.createElement('input');
        sizeInput.type = 'number';
        sizeInput.min = '10';
        sizeInput.max = '56';
        sizeInput.value = style.font_size || '';
        sizeInput.placeholder = 'px';
        sizeInput.dataset.style = 'font_size';

        const mkToggle = (key, text, checked) => {
            const label = document.createElement('label');
            label.className = 'toggle-row';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = Boolean(checked);
            input.dataset.style = key;
            const span = document.createElement('span');
            span.textContent = text;
            label.append(input, span);
            return label;
        };

        const colorInput = document.createElement('input');
        colorInput.type = 'color';
        colorInput.value = style.color || '#1c2430';
        colorInput.dataset.style = 'color';

        wrapper.append(
            fontSelect,
            sizeInput,
            mkToggle('bold', 'B', style.bold),
            mkToggle('italic', 'I', style.italic),
            mkToggle('underline', 'U', style.underline),
            colorInput
        );
        return wrapper;
    };

    const readStyleFromContainer = (container) => {
        if (!(container instanceof HTMLElement)) {
            return {};
        }
        return cleanStyle({
            font_family: container.querySelector('[data-style="font_family"]')?.value || '',
            font_size: container.querySelector('[data-style="font_size"]')?.value || '',
            bold: container.querySelector('[data-style="bold"]')?.checked || false,
            italic: container.querySelector('[data-style="italic"]')?.checked || false,
            underline: container.querySelector('[data-style="underline"]')?.checked || false,
            color: container.querySelector('[data-style="color"]')?.value || '',
        });
    };

    const createFieldCard = (field = {}) => {
        const card = document.createElement('div');
        card.className = 'field-card';

        const row1 = document.createElement('div');
        row1.className = 'builder-row';

        const labelInput = textInput(field.label || '', 'Label field');
        labelInput.dataset.field = 'label';

        const idInput = textInput(field.id || '', 'field_id', 'builder-key');
        idInput.dataset.field = 'id';

        const removeBtn = btn('Hapus Field', 'btn btn-delete btn-small');
        removeBtn.addEventListener('click', () => card.remove());

        row1.append(labelInput, idInput, removeBtn);

        const labelStyleEditor = createStyleEditor(field.label_style || {});

        const row2 = document.createElement('div');
        row2.className = 'builder-row field-config';

        const typeSelect = document.createElement('select');
        typeSelect.dataset.field = 'type';
        fieldTypes.forEach((type) => {
            const option = document.createElement('option');
            option.value = type.value;
            option.textContent = type.label;
            if ((field.type || 'single_line_text') === type.value) {
                option.selected = true;
            }
            typeSelect.appendChild(option);
        });

        const placeholderInput = textInput(field.placeholder || '', 'Placeholder (opsional)');
        placeholderInput.dataset.field = 'placeholder';

        const requiredWrap = document.createElement('label');
        requiredWrap.className = 'toggle-row';
        const requiredInput = document.createElement('input');
        requiredInput.type = 'checkbox';
        requiredInput.checked = Boolean(field.required);
        requiredInput.dataset.field = 'required';
        const requiredText = document.createElement('span');
        requiredText.textContent = 'Required';
        requiredWrap.append(requiredInput, requiredText);

        row2.append(typeSelect, placeholderInput, requiredWrap);

        const optionsRow = document.createElement('div');
        optionsRow.className = 'builder-row';
        optionsRow.dataset.field = 'options-row';
        const optionsInput = textInput(Array.isArray(field.options) ? field.options.join(', ') : '', 'Opsi dipisah koma');
        optionsInput.dataset.field = 'options';
        const optionsHint = document.createElement('small');
        optionsHint.textContent = 'Gunakan untuk single/multiple select.';
        optionsRow.append(optionsInput, optionsHint);

        const toggleOptionsVisibility = () => {
            const type = typeSelect.value;
            optionsRow.style.display = (type === 'single_select' || type === 'multi_select') ? 'grid' : 'none';
        };
        typeSelect.addEventListener('change', toggleOptionsVisibility);
        toggleOptionsVisibility();

        card.append(row1, labelStyleEditor, row2, optionsRow);
        return card;
    };

    const createSectionCard = (section = {}) => {
        const card = document.createElement('article');
        card.className = 'opening-card';

        const head = document.createElement('div');
        head.className = 'opening-card-head';

        const sectionTitleInput = textInput(section.title || '', 'Nama section');
        sectionTitleInput.dataset.field = 'title';

        const idInput = textInput(section.id || '', 'section_id', 'builder-key');
        idInput.dataset.field = 'id';

        const removeBtn = btn('Hapus Section', 'btn btn-delete btn-small');
        removeBtn.addEventListener('click', () => card.remove());

        head.append(sectionTitleInput, idInput, removeBtn);

        const sectionStyleEditor = createStyleEditor(section.title_style || {});

        const descInput = textInput(section.description || '', 'Deskripsi (opsional)');
        descInput.dataset.field = 'description';

        const fieldsList = document.createElement('div');
        fieldsList.className = 'builder-list nested-list';
        fieldsList.dataset.field = 'fields-list';

        const addFieldBtn = btn('Tambah Field');
        addFieldBtn.addEventListener('click', () => {
            fieldsList.appendChild(createFieldCard());
        });

        const fields = Array.isArray(section.fields) ? section.fields : [];
        if (fields.length === 0) {
            fieldsList.appendChild(createFieldCard());
        } else {
            fields.forEach((field) => fieldsList.appendChild(createFieldCard(field)));
        }

        card.append(head, sectionStyleEditor, descInput, fieldsList, addFieldBtn);
        return card;
    };

    const parseBuilder = () => {
        const sections = Array.from(sectionsList.querySelectorAll('.opening-card')).map((sectionEl, sectionIndex) => {
            const sectionTitleInput = sectionEl.querySelector('[data-field="title"]');
            const idInput = sectionEl.querySelector('[data-field="id"]');
            const descriptionInput = sectionEl.querySelector('[data-field="description"]');
            const fieldsEls = Array.from(sectionEl.querySelectorAll('.field-card'));

            const sectionId = slugify(idInput?.value || '', `section_${sectionIndex + 1}`);
            if (idInput instanceof HTMLInputElement) {
                idInput.value = sectionId;
            }

            const fields = fieldsEls.map((fieldEl, fieldIndex) => {
                const labelInput = fieldEl.querySelector('[data-field="label"]');
                const fieldIdInput = fieldEl.querySelector('[data-field="id"]');
                const typeSelect = fieldEl.querySelector('[data-field="type"]');
                const placeholderInput = fieldEl.querySelector('[data-field="placeholder"]');
                const requiredInput = fieldEl.querySelector('[data-field="required"]');
                const optionsInput = fieldEl.querySelector('[data-field="options"]');

                const label = String(labelInput?.value || '').trim();
                if (!label) {
                    return null;
                }

                const fieldId = slugify(fieldIdInput?.value || '', `${sectionId}_field_${fieldIndex + 1}`);
                if (fieldIdInput instanceof HTMLInputElement) {
                    fieldIdInput.value = fieldId;
                }
                const type = String(typeSelect?.value || 'single_line_text');

                const field = {
                    id: fieldId,
                    type,
                    label,
                    required: Boolean(requiredInput?.checked),
                    label_style: readStyleFromContainer(fieldEl.querySelector('[data-field="style"]')),
                };

                const placeholder = String(placeholderInput?.value || '').trim();
                if (placeholder) field.placeholder = placeholder;

                if (type === 'single_select' || type === 'multi_select') {
                    field.options = optionsInputToArray(optionsInput?.value);
                }

                return field;
            }).filter(Boolean);

            if (fields.length === 0) {
                return null;
            }

            return {
                id: sectionId,
                title: String(sectionTitleInput?.value || '').trim() || `Section ${sectionIndex + 1}`,
                description: String(descriptionInput?.value || '').trim(),
                title_style: readStyleFromContainer(sectionEl.querySelector('[data-field="style"]')),
                fields,
            };
        }).filter(Boolean);

        return {
            header: {
                title: String(titleInput.value || '').trim() || 'CHECKLIST',
                subtitle: String(subtitleInput.value || '').trim() || 'Daily Operational Control',
                title_style: readStyleControls(headerTitleStyleControls),
                subtitle_style: readStyleControls(headerSubtitleStyleControls),
            },
            sections,
        };
    };

    const renderBuilder = (schema) => {
        titleInput.value = String(schema?.header?.title || 'CHECKLIST');
        subtitleInput.value = String(schema?.header?.subtitle || 'Daily Operational Control');
        writeStyleControls(headerTitleStyleControls, schema?.header?.title_style || {}, '#1c2430');
        writeStyleControls(headerSubtitleStyleControls, schema?.header?.subtitle_style || {}, '#667085');

        sectionsList.innerHTML = '';
        const sections = Array.isArray(schema?.sections) ? schema.sections : [];
        if (sections.length === 0) {
            sectionsList.appendChild(createSectionCard());
            return;
        }
        sections.forEach((section) => sectionsList.appendChild(createSectionCard(section)));
    };

    const renderPreviewField = (field) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'preview-field';

        const label = document.createElement('span');
        label.textContent = `${field.label || field.id}${field.required ? ' *' : ''}`;
        label.style.cssText = styleToCss(field.label_style || {});
        wrapper.appendChild(label);

        const type = String(field.type || 'single_line_text');
        if (type === 'checkbox') {
            const item = document.createElement('label');
            item.className = 'check-item';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.disabled = true;
            const text = document.createElement('span');
            text.textContent = field.label || 'Checkbox';
            text.style.cssText = styleToCss(field.label_style || {});
            item.append(input, text);
            wrapper.appendChild(item);
            return wrapper;
        }

        if (type === 'single_select' || type === 'multi_select') {
            const choices = document.createElement('div');
            choices.className = 'preview-choices';
            const options = Array.isArray(field.options) ? field.options : [];
            options.forEach((option) => {
                const optionWrap = document.createElement('label');
                const input = document.createElement('input');
                input.type = type === 'single_select' ? 'radio' : 'checkbox';
                input.disabled = true;
                const text = document.createElement('span');
                text.textContent = option;
                optionWrap.append(input, text);
                choices.appendChild(optionWrap);
            });
            if (options.length === 0) {
                const hint = document.createElement('small');
                hint.textContent = 'Belum ada opsi.';
                choices.appendChild(hint);
            }
            wrapper.appendChild(choices);
            return wrapper;
        }

        if (type === 'long_text') {
            const input = document.createElement('textarea');
            input.disabled = true;
            input.placeholder = field.placeholder || '';
            wrapper.appendChild(input);
            return wrapper;
        }

        const input = document.createElement('input');
        input.disabled = true;
        input.placeholder = field.placeholder || '';
        input.type = (type === 'date' || type === 'number' || type === 'time') ? type : 'text';
        wrapper.appendChild(input);
        return wrapper;
    };

    const renderPreview = (schema) => {
        if (!previewBodyEl || !previewTitleEl || !previewSubtitleEl) {
            return;
        }

        previewTitleEl.textContent = String(schema?.header?.title || 'CHECKLIST');
        previewTitleEl.style.cssText = styleToCss(schema?.header?.title_style || {});
        previewSubtitleEl.textContent = String(schema?.header?.subtitle || 'Daily Operational Control');
        previewSubtitleEl.style.cssText = styleToCss(schema?.header?.subtitle_style || {});
        previewBodyEl.innerHTML = '';

        const sections = Array.isArray(schema?.sections) ? schema.sections : [];
        if (sections.length === 0) {
            const empty = document.createElement('p');
            empty.textContent = 'Belum ada section.';
            previewBodyEl.appendChild(empty);
            return;
        }

        sections.forEach((section, index) => {
            const sectionEl = document.createElement('section');
            sectionEl.className = 'preview-section';

            const titleEl = document.createElement('h5');
            titleEl.textContent = section.title || `Section ${index + 1}`;
            titleEl.style.cssText = styleToCss(section.title_style || {});
            sectionEl.appendChild(titleEl);

            if (section.description) {
                const descEl = document.createElement('p');
                descEl.textContent = section.description;
                sectionEl.appendChild(descEl);
            }

            const fields = Array.isArray(section.fields) ? section.fields : [];
            fields.forEach((field) => sectionEl.appendChild(renderPreviewField(field)));

            previewBodyEl.appendChild(sectionEl);
        });
    };

    const syncJson = () => {
        const parsed = parseBuilder();
        schemaField.value = JSON.stringify(parsed, null, 2);
        renderPreview(parsed);
    };

    const parseInitial = () => {
        try {
            const raw = (initialSchemaEl.textContent || '').trim();
            if (raw !== '') {
                return JSON.parse(raw);
            }
        } catch (error) {
            // fallback to textarea value
        }

        try {
            const fallbackRaw = (schemaField.value || '').trim();
            if (fallbackRaw !== '') {
                return JSON.parse(fallbackRaw);
            }
        } catch (error) {
            // ignore
        }

        return {};
    };

    document.getElementById('add-section')?.addEventListener('click', () => {
        sectionsList.appendChild(createSectionCard());
        syncJson();
    });

    document.getElementById('apply-json-to-builder')?.addEventListener('click', () => {
        try {
            const parsed = JSON.parse(schemaField.value || '{}');
            renderBuilder(parsed);
            syncJson();
        } catch (error) {
            window.alert('Schema JSON tidak valid.');
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target !== schemaField) {
            syncJson();
        }
    });

    form.addEventListener('submit', () => {
        syncJson();
    });

    let slugTouched = Boolean(templateSlugInput?.value?.trim());
    templateSlugInput?.addEventListener('input', () => {
        slugTouched = true;
    });
    templateNameInput?.addEventListener('input', () => {
        if (templateSlugInput && !slugTouched) {
            templateSlugInput.value = hyphenSlug(templateNameInput.value, 'template-baru');
        }
    });

    renderBuilder(parseInitial());
    syncJson();
})();
