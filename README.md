# NexaHCMS — Human Capital Management System

Laravel 13 + Blade + Bootstrap 5.3 + jQuery AJAX. Requirement: [rancangan.md](rancangan.md). Desain UI: [template_mockup/](template_mockup/) (tema *Slate & Emerald*).

## Menjalankan

```bash
composer install
cp .env.example .env && php artisan key:generate
# buat database MySQL `hcms_ziah` (dan `hcms_ziah_test` untuk tes)
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve          # http://localhost:8000
php artisan queue:work     # wajib untuk pengiriman email payslip
```

Aset front-end (Bootstrap, jQuery, Inter, Material Symbols) dimuat dari CDN, jadi tidak perlu `npm`. CSS/JS aplikasi ada di `public/css/app.css` dan `public/js/app.js`.

### Akun demo (password: `password`)

| Email | Role | Data scope |
|---|---|---|
| superadmin@hcms.test | Super Admin | all |
| hr@hcms.test | HR Admin | all |
| payroll@hcms.test | Payroll Officer | all |
| manager@hcms.test | Manager (IT) | subordinate |
| karyawan@hcms.test | Karyawan | self |

## Struktur penting

| Bagian | Lokasi |
|---|---|
| RBAC: middleware `permission:modul.aksi`, Gate untuk `@can` | `app/Http/Middleware/EnsurePermission.php`, `app/Providers/AppServiceProvider.php` |
| Data scope (all/department/subordinate/self) | `Employee::scopeVisibleTo()` |
| Menu sidebar + permission per item | `config/menu.php` |
| Perhitungan gaji (BPJS, PPh 21 TER, lembur, potongan) | `app/Services/PayrollCalculator.php`, `config/payroll.php` |
| Alur periode: proses → approve → final/lock → reopen | `app/Services/PayrollService.php` |
| Report (13 jenis, preview + XLSX/PDF) | `app/Services/ReportService.php` |
| Helper AJAX (tabel, form, toast, konfirmasi) | `public/js/app.js` (`HCMS.*`) |
| Audit log | `AuditLog::record()` |

Pola AJAX: endpoint `index` mengembalikan view untuk request biasa dan JSON paginator bila `Accept: application/json`. Validasi 422 ditampilkan per field oleh `HCMS.submitForm()`.

## Tes

```bash
php artisan test   # memakai DB MySQL hcms_ziah_test
```

## Catatan

- Tarif TER PPh 21 dan parameter BPJS di `config/payroll.php` adalah nilai awal. Verifikasi dulu terhadap regulasi terbaru sebelum dipakai di produksi.
- Proses gaji saat ini berjalan sinkron. Untuk jumlah karyawan besar, pindahkan ke job queue.
- `MAIL_MAILER=log`: email payslip ditulis ke `storage/logs/laravel.log`.
