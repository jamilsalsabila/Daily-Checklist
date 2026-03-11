# Daily Checklist

Web app checklist operasional berbasis PHP + SQLite, dengan template dinamis per divisi (floor, kitchen, bar, kasir, cleaning service, dll).

## Fitur Utama

- Form checklist dinamis dari template (`sections + fields`).
- Tanda tangan digital (canvas), simpan ke database, generate PDF.
- Share ke WhatsApp dan kirim PDF via email SMTP.
- Login admin + proteksi CSRF + rate limit login.
- Manajemen template (buat, edit, publish/unpublish, hapus).
- Multi-template published sekaligus, akses form per slug:
  - `index.php?template=floor-captain-control-sheet`
  - `index.php?template=kitchen`
- Halaman admin sudah SPA ringan (fetch + update DOM tanpa full reload).

## Struktur Singkat

- `index.php` form checklist (berdasarkan template slug/published).
- `admin.php` dashboard admin (SPA ringan).
- `admin-api.php` endpoint JSON untuk aksi admin SPA.
- `template-form.php` builder template + live preview.
- `submit.php`, `update.php`, `delete.php` proses data submission.
- `pdf.php`, `email.php` output PDF dan kirim email.
- `app/TemplateSchema.php` engine schema template + validasi.
- `app/Database.php` akses SQLite + migrasi otomatis.

## Requirement

- PHP 8.1+ (disarankan).
- Extension: `pdo_sqlite`.
- Folder writable:
  - `storage/database`
  - `storage/pdf`

## Setup Lokal (XAMPP)

1. Clone project ke `htdocs`.
2. Copy `.env.example` jadi `.env`, lalu isi:
   - `ADMIN_EMAIL`
   - `ADMIN_PASSWORD_HASH` (atau `ADMIN_PASSWORD` untuk testing lokal)
   - SMTP (`SMTP_HOST`, `SMTP_PORT`, dst) jika mau fitur email.
3. Pastikan folder `storage/` writable oleh web server.
4. Buka:
   - Form: `http://localhost/Daily-Checklist/index.php`
   - Admin: `http://localhost/Daily-Checklist/login.php`

## Routing Form Multi Divisi

- Jika buka `index.php` tanpa parameter:
  - bila template published cuma 1 -> langsung form itu.
  - bila lebih dari 1 -> halaman pilih template.
- Akses langsung per divisi:
  - `index.php?template=<slug-template>`

## Catatan Version Log

- `2026-03-11` - Rename submission identity column from `floor_captain` to `nama` with safe SQLite migration fallback and update all related UI/messages.
- `2026-03-11` - Add recursive nested sections (section -> subsection), including builder, preview, form render, admin detail, required-field checks, and PDF output.
- `2026-03-11` - Tighten template builder spacing and allow empty section title (no fallback "Section X" in form/admin/PDF/preview).
- `03b19db` - Add multi-template checklist management.
- `d1c3771` - Convert admin page to lightweight SPA.

## Catatan Maintenance

- Saat simpan versi (commit/checkpoint), update bagian **Version Log** di README ini agar histori perubahan tetap jelas.
