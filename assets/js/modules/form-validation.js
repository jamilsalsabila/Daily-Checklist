(() => {
    const form = document.getElementById('checklist-form');
    if (!form) return;

    const firstVisibleText = (nodeList) => {
        for (const node of nodeList) {
            const text = String(node?.textContent || '').trim();
            if (text) return text;
        }
        return '';
    };

    const fieldLabel = (input) => {
        const meta = input.closest('.meta');
        if (meta) {
            const text = firstVisibleText(meta.querySelectorAll(':scope > span'));
            if (text) return text;
        }
        const check = input.closest('.check-item');
        if (check) {
            const text = firstVisibleText(check.querySelectorAll('span'));
            if (text) return text;
        }
        return input.getAttribute('name') || 'Field wajib';
    };

    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        const missing = [];

        const requiredTextual = Array.from(form.querySelectorAll('input[required], textarea[required], select[required]'))
            .filter((el) => !(el instanceof HTMLInputElement && (el.type === 'radio' || el.type === 'checkbox' || el.type === 'hidden')));

        for (const field of requiredTextual) {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
                continue;
            }
            if (String(field.value || '').trim() === '') {
                missing.push(fieldLabel(field));
            }
        }

        const radioNames = new Set();
        Array.from(form.querySelectorAll('input[type="radio"][required]')).forEach((radio) => {
            if (radio instanceof HTMLInputElement && radio.name) {
                radioNames.add(radio.name);
            }
        });
        for (const name of radioNames) {
            const options = Array.from(form.querySelectorAll(`input[type="radio"][name="${CSS.escape(name)}"]`));
            const checked = options.some((el) => el instanceof HTMLInputElement && el.checked);
            if (!checked) {
                const ref = options.find((el) => el instanceof HTMLInputElement);
                if (ref instanceof HTMLInputElement) missing.push(fieldLabel(ref));
            }
        }

        const requiredGroups = Array.from(form.querySelectorAll('[data-required-group="1"]'));
        for (const group of requiredGroups) {
            const checked = Array.from(group.querySelectorAll('input[type="checkbox"]'))
                .some((el) => el instanceof HTMLInputElement && el.checked);
            if (!checked) {
                const groupLabel = group.getAttribute('data-required-label') || 'Field wajib';
                missing.push(groupLabel);
            }
        }

        const signatureResponse = form.querySelector('#signature-response[required]');
        if (signatureResponse instanceof HTMLInputElement && String(signatureResponse.value || '').trim() === '') {
            missing.push(fieldLabel(signatureResponse));
        }

        if (missing.length > 0) {
            event.preventDefault();
            const unique = Array.from(new Set(missing)).slice(0, 8);
            window.alert(`Masih ada field wajib yang belum diisi:\n- ${unique.join('\n- ')}`);
            return;
        }

        form.dataset.submitting = '1';
        const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitButtons.forEach((button) => {
            if (button instanceof HTMLButtonElement || button instanceof HTMLInputElement) {
                button.disabled = true;
            }
        });
    });
})();
