document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const targetId = button.getAttribute('data-target');
    if (!targetId) return;

    const input = document.getElementById(targetId);
    if (!(input instanceof HTMLInputElement)) return;

    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        button.setAttribute('aria-label', reveal ? 'Sembunyikan password' : 'Tampilkan password');
    });
});
