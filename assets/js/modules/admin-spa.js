(() => {
    const app = document.getElementById('admin-app');
    const initialStateEl = document.getElementById('admin-initial-state');
    const noticeEl = document.getElementById('admin-notice');
    const templatePanelEl = document.getElementById('template-panel');
    const listEl = document.getElementById('submission-list');
    const detailEl = document.getElementById('submission-detail');
    const filterForm = document.getElementById('admin-filters');

    if (!app || !initialStateEl || !noticeEl || !templatePanelEl || !listEl || !detailEl || !filterForm) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const attr = (style = {}) => {
        const fontMap = {
            sans: '"Avenir Next", Avenir, "Segoe UI", sans-serif',
            serif: '"Iowan Old Style", Georgia, serif',
            mono: '"Courier New", ui-monospace, monospace',
            default: 'inherit',
        };
        const css = [];
        if (style.font_family && fontMap[style.font_family]) css.push(`font-family:${fontMap[style.font_family]}`);
        if (style.font_size) css.push(`font-size:${Number(style.font_size)}px`);
        if (style.bold) css.push('font-weight:700');
        if (style.italic) css.push('font-style:italic');
        if (style.underline) css.push('text-decoration:underline');
        if (style.color) css.push(`color:${style.color}`);
        return css.length ? ` style="${escapeHtml(css.join(';'))}"` : '';
    };

    const readInitialState = () => {
        try {
            return JSON.parse(initialStateEl.textContent || '{}');
        } catch (error) {
            return {};
        }
    };

    let state = readInitialState();
    let isBusy = false;

    const buildQuery = (filters = {}) => {
        const params = new URLSearchParams();
        if (filters.q) params.set('q', filters.q);
        if (filters.tanggal) params.set('tanggal', filters.tanggal);
        if (filters.code) params.set('code', filters.code);
        return params.toString();
    };

    const noticeMarkup = (status) => {
        if (!status) return '';
        const map = {
            updated: ['ok', 'Submission berhasil diperbarui.'],
            deleted: ['ok', 'Submission berhasil dihapus.'],
            delete_failed: ['bad', 'Gagal menghapus submission.'],
            template_published: ['ok', 'Template berhasil dipublikasikan.'],
            template_unpublished: ['ok', 'Template berhasil dinonaktifkan dari publikasi.'],
            template_activate_failed: ['bad', 'Gagal mengubah status publikasi template.'],
            csrf_error: ['bad', 'Sesi keamanan tidak valid. Silakan ulangi.'],
            template_saved: ['ok', 'Template berhasil disimpan.'],
            template_deleted: ['ok', 'Template berhasil dihapus.'],
            template_delete_failed: ['bad', 'Template tidak bisa dihapus (masih dipublikasikan atau sudah dipakai submission).'],
            template_save_failed: ['bad', 'Gagal menyimpan template. Cek data input.'],
        };
        if (!map[status]) return '';
        return `<div class="notice ${map[status][0]}">${escapeHtml(map[status][1])}</div>`;
    };

    const renderTemplates = () => {
        const templates = Array.isArray(state.templates) ? state.templates : [];
        const publishedCount = Number(state.published_count || 0);
        templatePanelEl.innerHTML = `
            <div class="template-panel-head">
                <h2>Checklist Template</h2>
                <div class="template-head-actions">
                    ${publishedCount > 0 ? `<span class="chip">Published: ${publishedCount} template</span>` : ''}
                    <a class="btn btn-edit" href="template-form.php">Template baru</a>
                </div>
            </div>
            <div class="template-list">
                ${templates.map((template) => {
                    const isPublished = Number(template.is_active || 0) === 1;
                    return `
                        <article class="template-item${isPublished ? ' active' : ''}">
                            <div class="template-main">
                                <strong>${escapeHtml(template.name || '-')}</strong>
                                <span class="code">${escapeHtml(template.slug || '-')}</span>
                                <span class="muted">Versi: v${escapeHtml(template.current_version || '-')}</span>
                                ${isPublished ? `<a class="muted template-link" target="_blank" rel="noopener" href="index.php?template=${encodeURIComponent(template.slug || '')}">Form URL: /?template=${escapeHtml(template.slug || '')}</a>` : ''}
                            </div>
                            <div class="template-action">
                                <div class="template-button-row">
                                    ${isPublished
                                        ? `<button class="btn btn-ghost btn-small" type="button" data-template-action="template_unpublish" data-template-id="${escapeHtml(template.id)}">Nonaktifkan</button>`
                                        : `<button class="btn btn-edit" type="button" data-template-action="template_publish" data-template-id="${escapeHtml(template.id)}">Publikasikan</button>`}
                                    ${!isPublished
                                        ? `<button class="btn btn-delete" type="button" data-template-action="template_delete" data-template-id="${escapeHtml(template.id)}">Hapus</button>`
                                        : ''}
                                    <a class="btn btn-ghost btn-small" href="template-form.php?id=${encodeURIComponent(template.id)}">Edit</a>
                                </div>
                            </div>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    };

    const renderSectionDetail = (section, responses, depth = 0) => {
        const title = String(section?.title || '').trim();
        const headingLevel = Math.min(6, 4 + depth);
        const heading = title !== '' ? `<h${headingLevel}${attr(section?.title_style || {})}>${escapeHtml(title)}</h${headingLevel}>` : '';
        const fieldsHtml = (section?.fields || []).map((field) => {
            const value = responses?.[field.id];
            let display = '-';
            if (field.type === 'checkbox') {
                display = value ? 'Ya' : 'Tidak';
            } else if (Array.isArray(value)) {
                display = value.length ? value.join(', ') : '-';
            } else if (value !== null && value !== undefined && String(value).trim() !== '') {
                display = String(value);
            }
            return `<p><strong${attr(field.label_style || {})}>${escapeHtml(field.label || '')}:</strong> ${escapeHtml(display)}</p>`;
        }).join('');
        const childrenHtml = (section?.children || []).map((child) => renderSectionDetail(child, responses, depth + 1)).join('');
        return `${heading}${fieldsHtml}${childrenHtml}`;
    };

    const renderList = () => {
        const submissions = Array.isArray(state.submissions) ? state.submissions : [];
        const selectedCode = state.filters?.code || '';
        listEl.innerHTML = submissions.length === 0
            ? '<div class="empty">Belum ada data yang cocok dengan filter.</div>'
            : submissions.map((item) => {
                const params = new URLSearchParams();
                if (state.filters?.q) params.set('q', state.filters.q);
                if (state.filters?.tanggal) params.set('tanggal', state.filters.tanggal);
                params.set('code', item.submission_code || '');
                return `
                    <a class="item${selectedCode === item.submission_code ? ' active' : ''}" href="?${params.toString()}" data-code="${escapeHtml(item.submission_code || '')}">
                        <div class="row">
                            <strong>${escapeHtml(item.floor_captain || '-')}</strong>
                            <span class="code">${escapeHtml(item.submission_code || '-')}</span>
                        </div>
                        <div class="row muted">
                            <span>Tanggal checklist: ${escapeHtml(item.tanggal || '-')}</span>
                            <span>Dibuat: ${escapeHtml(item.created_at ? new Date(item.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-')}</span>
                        </div>
                        <div class="row muted">
                            <span>Template: ${escapeHtml((state.template_names || {})[String(item.template_id)] || (state.template_names || {})[Number(item.template_id)] || '-')}</span>
                        </div>
                    </a>
                `;
            }).join('');
    };

    const renderDetail = () => {
        const selected = state.selected;
        const schema = state.selected_schema;
        if (!selected || !schema) {
            detailEl.innerHTML = '<div class="empty">Pilih salah satu submission untuk melihat detail lengkap.</div>';
            return;
        }

        detailEl.innerHTML = `
            <div class="detail-grid">
                <div class="detail-card">
                    <h3>Ringkasan</h3>
                    <p><strong>Floor Captain:</strong> ${escapeHtml(selected.floor_captain || '-')}</p>
                    <p><strong>Tanggal:</strong> ${escapeHtml(selected.tanggal || '-')}</p>
                    <p><strong>Template:</strong> ${escapeHtml((state.template_names || {})[String(selected.template_id)] || (state.template_names || {})[Number(selected.template_id)] || '-')}</p>
                    <div class="chips">
                        <span class="chip">Terisi ${escapeHtml(state.selected_completion || '0/0')}</span>
                        <span class="chip">Kode ${escapeHtml(selected.submission_code || '-')}</span>
                    </div>
                    <p>
                        <a href="pdf.php?code=${encodeURIComponent(selected.submission_code || '')}" target="_blank" rel="noopener">Buka PDF</a>
                    </p>
                    <div class="action-row">
                        <a class="btn btn-edit" href="edit.php?code=${encodeURIComponent(selected.submission_code || '')}">Edit</a>
                        <a class="btn btn-wa" href="${escapeHtml(state.selected_wa_url || '#')}" target="_blank" rel="noopener">Send to WhatsApp</a>
                        <button class="btn btn-delete" type="button" data-submission-action="submission_delete" data-submission-code="${escapeHtml(selected.submission_code || '')}">Hapus</button>
                    </div>
                </div>
                <div class="detail-card">
                    <h3>Isi Form</h3>
                    ${(schema.sections || []).map((section) => `
                        ${renderSectionDetail(section, selected.responses || {}, 0)}
                    `).join('')}
                </div>
                <div class="detail-card">
                    <h3>Tanda Tangan</h3>
                    <img class="signature-preview" src="${escapeHtml(selected.signature_preview || '')}" alt="Signature preview">
                </div>
            </div>
        `;
    };

    const renderNotice = () => {
        noticeEl.innerHTML = noticeMarkup(state.status);
    };

    const syncFilters = () => {
        const q = filterForm.querySelector('input[name="q"]');
        const tanggal = filterForm.querySelector('input[name="tanggal"]');
        if (q) q.value = state.filters?.q || '';
        if (tanggal) tanggal.value = state.filters?.tanggal || '';
    };

    const renderAll = () => {
        renderNotice();
        renderTemplates();
        renderList();
        renderDetail();
        syncFilters();
    };

    const fetchState = async (url, push = true) => {
        if (isBusy) return;
        isBusy = true;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Request gagal.');
            }
            state = payload.state;
            renderAll();
            if (push) {
                const query = buildQuery(state.filters || {});
                history.pushState({ filters: state.filters }, '', query ? `admin.php?${query}` : 'admin.php');
            }
        } catch (error) {
            noticeEl.innerHTML = `<div class="notice bad">${escapeHtml(error.message || 'Terjadi error.')}</div>`;
        } finally {
            isBusy = false;
        }
    };

    const postAction = async (data) => {
        if (isBusy) return;
        isBusy = true;
        try {
            const body = new URLSearchParams();
            Object.entries(data).forEach(([key, value]) => body.set(key, String(value ?? '')));
            body.set('csrf_token', state.csrf_token || '');
            body.set('q', state.filters?.q || '');
            body.set('tanggal', state.filters?.tanggal || '');
            body.set('code', state.filters?.code || '');

            const response = await fetch('admin-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Action gagal.');
            }
            state = payload.state;
            renderAll();
            const query = buildQuery(state.filters || {});
            history.pushState({ filters: state.filters }, '', query ? `admin.php?${query}` : 'admin.php');
        } catch (error) {
            noticeEl.innerHTML = `<div class="notice bad">${escapeHtml(error.message || 'Terjadi error.')}</div>`;
        } finally {
            isBusy = false;
        }
    };

    filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(filterForm);
        const filters = {
            q: String(formData.get('q') || '').trim(),
            tanggal: String(formData.get('tanggal') || '').trim(),
            code: '',
        };
        const query = buildQuery(filters);
        fetchState(query ? `admin-api.php?${query}` : 'admin-api.php');
    });

    app.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const resetLink = target.closest('a[href="admin.php"]');
        if (resetLink && resetLink.closest('#admin-filters')) {
            event.preventDefault();
            fetchState('admin-api.php');
            return;
        }

        const submissionLink = target.closest('.item[data-code]');
        if (submissionLink instanceof HTMLAnchorElement) {
            event.preventDefault();
            const code = submissionLink.dataset.code || '';
            const filters = {
                q: state.filters?.q || '',
                tanggal: state.filters?.tanggal || '',
                code,
            };
            const query = buildQuery(filters);
            fetchState(`admin-api.php?${query}`);
            return;
        }

        const templateButton = target.closest('[data-template-action]');
        if (templateButton instanceof HTMLButtonElement) {
            event.preventDefault();
            const action = templateButton.dataset.templateAction || '';
            const templateId = templateButton.dataset.templateId || '';
            if (action === 'template_delete' && !window.confirm('Hapus template ini?')) {
                return;
            }
            postAction({ action, template_id: templateId });
            return;
        }

        const submissionButton = target.closest('[data-submission-action="submission_delete"]');
        if (submissionButton instanceof HTMLButtonElement) {
            event.preventDefault();
            if (!window.confirm('Hapus submission ini?')) {
                return;
            }
            postAction({
                action: 'submission_delete',
                submission_code: submissionButton.dataset.submissionCode || '',
            });
        }
    });

    window.addEventListener('popstate', () => {
        const query = window.location.search.replace(/^\?/, '');
        fetchState(query ? `admin-api.php?${query}` : 'admin-api.php', false);
    });

    renderAll();
})();
