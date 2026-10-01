# Detailing Devils website (Laravel)

The complete Detailing Devils home page: scroll banner, all sections and animations, plus a working enquiry form that saves every enquiry and emails it to you.

**Needs:** PHP 8.3 or newer and Composer (Laravel 13).

---

## 1. Run it on your computer

The easiest way on Windows is **Laragon** (laragon.org). It includes PHP and Composer.

1. Unzip this folder into `C:\laragon\www\detailing-devils`.
2. Open Laragon, click **Terminal**, and run these one by one:

```
cd detailing-devils
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

(On Mac or Linux, use `cp .env.example .env` instead of `copy`.)

3. Open **http://localhost:8000** in your browser.

If `php artisan migrate` asks to create the SQLite database, type `yes`.

---

## 2. Edit the website

| What you want to change | Where |
|---|---|
| Phone, email, address, social links | `config/site.php` |
| Banner text (red line, heading, bottom line) | `config/site.php` → `hero` |
| Numbers row (145+, 3, 10H, 6) | `config/site.php` → `numbers` |
| Services (title, text, details, photo) | `config/site.php` → `services` |
| Franchise steps | `config/site.php` → `franchise_steps` |
| Any other text, headings, layout | `resources/views/home.blade.php` (search for the text and change it) |
| Photos | `public/img` (replace a file and keep the same name) |
| Banner scroll frames | `public/frames` (240 files: `ezgif-frame-001.jpg` …). If the number of frames changes, update `frames` in `config/site.php` → `hero`. |
| Colours and fonts | top of `resources/views/home.blade.php`, inside `<style>` → `:root` |

On a live server, run `php artisan config:clear` and `php artisan view:clear` after editing.

---

## 3. Enquiry form

- Every form submission is saved in the database.
- See them all at **/admin/enquiries** (for example `https://yourdomain.com/admin/enquiries`).
- The login is set in `.env`:

```
ADMIN_USER=admin
ADMIN_PASSWORD=change-this-password
```

**Email alerts:** every enquiry is emailed to the address in `config/site.php` (`email`). To make sending work, put your email account's SMTP details in `.env`. Your hosting provider gives you these.

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourhost.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=info@detailingdevils.com
MAIL_PASSWORD=your-email-password
MAIL_FROM_ADDRESS="info@detailingdevils.com"
```

Until you add these, enquiries are still saved and visible at /admin/enquiries.

---

## 4. Put it online (hosting)

**Before uploading,** set these in `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
```

### Option A: Hosting with Laravel support or SSH (recommended)
Examples: Hostinger (Business plan and above), Cloudways, DigitalOcean with Laravel Forge.

1. Upload the folder.
2. Run `composer install --no-dev --optimize-autoloader`, `php artisan key:generate` and `php artisan migrate --force`.
3. Point your domain's document root to the **`public`** folder.

### Option B: Shared cPanel hosting (no SSH)
1. On your computer, run `composer install --no-dev` first, so the `vendor` folder exists.
2. Upload the whole project into a folder next to `public_html`, for example `/home/USER/detailing-devils`.
3. In cPanel → **Domains**, set the domain's document root to `/home/USER/detailing-devils/public`.
4. Make the `storage` and `bootstrap/cache` folders writable (permission 775).
5. For the database, either keep SQLite (`database/database.sqlite`, nothing to set up) or create a MySQL database in cPanel and set these in `.env`:

```
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Then run `php artisan migrate` once, from cPanel Terminal if available, or ask your host to run it.

---

## Notes
- The animations (GSAP, Lenis) and fonts load from public CDNs. The site needs internet access, which every live website has.
- On phones, banner frames are kept in memory only around the current scroll position, so finger scrolling stays smooth.
