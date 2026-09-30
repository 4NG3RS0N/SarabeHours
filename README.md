# دروس الدعم والتقوية — DD Support Classes

Landing page + registration form for OFPPT Développement Digital support classes
(150 MAD/mois). Frontend is a single static HTML page (the same design shown in
chat); the registration form is handled by a native PHP endpoint and stored in
MySQL. No frameworks on either side — plain PHP + PDO.

## Structure

```
php-project/
  public/
    index.html        <- the full landing page (frontend)
  api/
    config.example.php <- copy to config.php and fill in DB credentials
    config.php          <- (you create this, gitignored) real DB credentials
    db.php               <- PDO connection helper
    ratelimit.php         <- simple file-based rate limiter
    register.php           <- POST /api/register.php — validates + inserts a registration
    storage/                <- rate-limit state files (writable by PHP)
  schema.sql            <- MySQL table definition
  README.md
```

## 1. Requirements

- PHP 8.1+ with the `pdo_mysql` extension enabled
- MySQL 5.7+ / MariaDB 10.3+
- Any web server that can run PHP: Apache, Nginx+PHP-FPM, or PHP's built-in
  server for local dev

## 2. Database setup

```bash
mysql -u root -p < schema.sql
# creates the `dd_support` database and `registrations` table
```

Create a dedicated DB user rather than using root in production:

```sql
CREATE USER 'dd_support_user'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON dd_support.* TO 'dd_support_user'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Configure the API

```bash
cp api/config.example.php api/config.php
```

Edit `api/config.php` with your real DB host/name/user/password. **Never commit
`config.php` with real credentials** — add it to `.gitignore`.

Make sure `api/storage/` is writable by the PHP process (used by the rate limiter):

```bash
chmod 775 api/storage
```

## 4. Run it locally

Simplest option — PHP's built-in server serving both the static frontend and the API
from one root, so `fetch('api/register.php')` in `index.html` resolves correctly:

```bash
# from the php-project/ directory
php -S localhost:8000 -t public
```

Then symlink or copy the `api/` folder inside `public/` (or serve both from the
same document root), e.g.:

```bash
ln -s ../api public/api
php -S localhost:8000 -t public
```

Open http://localhost:8000 — submit the booking form, then check MySQL:

```sql
SELECT * FROM registrations ORDER BY created_at DESC;
```

### If the frontend and API are on different origins/ports

Set `allowed_origin` in `api/config.php` to your frontend's exact origin (e.g.
`http://localhost:5500`), and change `API_URL` at the bottom of `index.html`
to the full URL, e.g. `http://localhost:8000/api/register.php`.

## 5. Deploying

Any standard PHP hosting works (shared hosting, VPS with Apache/Nginx +
PHP-FPM). Point the document root at `public/`, upload `api/` alongside it (or
one level up, adjusting the `require` paths in `register.php` if you move
things), create the database from `schema.sql`, and fill in `api/config.php`
with production credentials. Serve over HTTPS in production.

## 6. What this does and doesn't include

This covers exactly what was asked: the public registration form, validated
and stored in MySQL via native PHP (no ORM, no framework). It does **not**
include admin login, the dashboard, CSV/Excel/XML/PDF export, or audit
logging from the original full spec — those are a separate, larger backend.
Happy to build those next (as PHP too, or Node/Express) once this piece is
confirmed working end to end.

## Security notes already in place

- All input is validated server-side (required fields, Moroccan phone format,
  email format) regardless of what client-side JS already checked.
- Queries use PDO prepared statements — no string-concatenated SQL.
- Duplicate registrations (same email or phone) are rejected with 409.
- Passwords/secrets are not applicable here (no auth in this piece), but
  `config.php` itself should never be committed to version control.
- A basic per-IP file-based rate limiter (20 requests / 15 min) guards
  `register.php`. For production at scale, prefer rate limiting at the
  reverse proxy (nginx `limit_req`, Cloudflare, etc.) instead.
- Raw DB/PHP errors are logged server-side (`error_log`) and never echoed to
  the client.
