# Coton in the Elms Business Register

A single-page signup form for a free local business directory for the village of
Coton in the Elms. Visitors submit their name, email, business name and a short
description; they get a branded confirmation email with a link to verify their
address (double opt-in); confirmed signups land in a MySQL table that a small
password-protected admin page can browse and export as CSV.

## Stack

- **Backend**: plain PHP 8.3 (no framework), PDO/MySQL
- **Database**: MySQL 8.4
- **Email**: Microsoft Graph API (`sendMail`, OAuth2 client-credentials) — not SMTP
- **Frontend**: a single static `index.html` (no build step, no JS framework), hand-written CSS
- **Local dev / deploy**: Docker + Docker Compose, driven by a `Makefile`

## Project layout

| File | Purpose |
|---|---|
| `index.html` | The public signup form |
| `submit.php` | Validates + stores a signup, sends the confirmation email |
| `confirm.php` | Confirms a signup via its emailed token |
| `admin.php` | Password-protected view of signups, with CSV export |
| `bootstrap.php` | Shared helpers: `cfg()`, `db()`, `send_mail()`, `graph_access_token()`, `http_post()` |
| `config.env.php` | Config sourced from environment variables; copied to `config.php` inside the Docker image |
| `config.example.php` | Template for a manual/non-Docker deployment where you hand-edit `config.php` instead of using `.env` |
| `schema.sql` | The `signups` table definition, auto-loaded into MySQL on first container start |
| `logo.svg`, `favicon.svg`, `favicon-32.png`, `apple-touch-icon.png` | Brand assets |
| `Makefile` | Day-to-day commands (see below) |
| `.htaccess` | Blocks direct web access to `config.php`, `bootstrap.php`, `composer.*`, `schema.sql` |
| `Dockerfile` / `docker-compose.yml` | Builds the PHP+Apache image, runs it alongside MySQL |

## Local development

```
make setup      # first time only: creates .env from .env.example
# edit .env — see Configuration below
make up         # starts db + web
```

Site: `http://localhost:8080` (or whatever `WEB_PORT` you set in `.env`).

Run `make help` to see every command. The main ones:

| Command | Does |
|---|---|
| `make build` | Rebuild the web image (after changing PHP/HTML/Dockerfile) |
| `make restart` | Rebuild + recreate just the web container — the normal "deploy my change" loop |
| `make up` / `make down` | Start / stop the stack (`down` keeps the database volume) |
| `make logs` | Follow the web container's logs |
| `make shell` | Shell into the web container |
| `make db-shell` | MySQL shell into the db container |
| `make admin-hash PASS=yourpassword` | Generate a bcrypt hash for `ADMIN_PASSWORD_HASH` |
| `make clean` | **Destructive** — stops the stack and deletes the database volume |

## Configuration (`.env`)

| Variable | Meaning |
|---|---|
| `WEB_PORT` | Host port the site is served on (container always listens on 80 internally) |
| `SITE_URL` | Public base URL of the site — used to build the confirmation link (`{SITE_URL}/confirm.php?t=...`). Must be a real, publicly reachable URL in production |
| `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_ROOT_PASS` | MySQL credentials |
| `IP_SALT` | Random string used to hash submitter IPs for rate-limiting (5 signups/hour/IP) without storing raw IPs |
| `ADMIN_PASSWORD_HASH` | bcrypt hash for `admin.php` — generate with `make admin-hash PASS=yourpassword`. **Escape every `$` in the value as `$$`** or Compose will mangle it |
| `MICROSOFT_TENANT_ID` / `MICROSOFT_CLIENT_ID` / `MICROSOFT_CLIENT_SECRET` / `MICROSOFT_SENDER_EMAIL` | Microsoft Graph app registration — see below |
| `CONFIRM_EMAIL_SUBJECT` | Subject line of the confirmation email |

## Email

Mail is sent via Microsoft Graph's `sendMail`, authenticated with the OAuth2
client-credentials flow (`bootstrap.php`'s `graph_access_token()` /
`send_mail()`) — there is no SMTP involved and no PHPMailer dependency.

This requires an **Azure AD app registration** with:

- **Application** (not delegated) permission `Mail.Send`, with **admin consent granted**
- A client secret (`MICROSOFT_CLIENT_SECRET`) — note its expiry date and rotate it before it lapses, or sending will start failing with an auth error
- `MICROSOFT_SENDER_EMAIL` must be a real mailbox in that tenant that the app is allowed to send as (currently `noreply@bssweb.co.uk`). If the tenant restricts `Mail.Send` to specific mailboxes via an Exchange Online **Application Access Policy**, this app/mailbox pair needs to be included in it

The confirmation email is a self-contained, table-based HTML template built in
`submit.php` (email clients like Outlook don't render modern CSS, flexbox or
inline SVG, so it deliberately doesn't reuse the site's illustration). The
header logo is embedded as an **inline CID attachment**, not a hosted image
URL — this was a deliberate fix: pointing `<img src>` at `SITE_URL` only works
once `SITE_URL` is a real public domain, and even then depends on the image
being reachable. Embedding it as an attachment means it always renders.

`send_mail()` signature: `send_mail(string $to, string $subject, string $html, array $inlineImages = [])`,
where `$inlineImages` maps a `cid:` name (e.g. `'logo'`) to a local file path.

## Admin panel

`/admin.php` — session-based login against `ADMIN_PASSWORD_HASH`. Lists all
signups with filters (all / confirmed / confirmed + marketing opt-in) and a
CSV export (with spreadsheet-formula-injection protection on exported cells).
`noindex` is set; there's no additional IP restriction, so keep the password
strong.

## Design notes

- Palette: forest green / warm paper / bark brown / muted brick-red / slate-teal water — chosen to avoid generic "AI SaaS" defaults (no purple gradients, no bright terracotta-on-cream).
- Typefaces: Fraunces (serif, headings) + Karla (sans, body/UI).
- The footer illustration (brook, brick bridge, elms, distant roofs) is grounded in the real **Gilwiskaw Brook**, a tributary of the River Mease that runs through this part of South Derbyshire — not generic clip art.
- `logo.svg` / `favicon.svg` are simplified versions of the same bridge-arch-and-brook motif; the PNG favicon variants were rendered from the SVG via a headless browser screenshot (no SVG rasterizer was available in this environment) and are not hand-edited.
- Motion is a single on-load entrance (header, then card) plus direct-feedback motion on focus/hover/submit — no ambient/looping animation, and everything is skipped under `prefers-reduced-motion: reduce`.

## Security notes

- Honeypot field (`website`) in the form — bots that fill it in get a fake success response.
- Rate limiting: 5 signups per hour per IP (IP is only ever stored as a salted hash, via `IP_SALT`).
- Double opt-in: a row is only ever counted as a real signup once the emailed token is confirmed.
- `.htaccess` denies direct HTTP access to `config.php`, `bootstrap.php`, `composer.json`/`composer.lock`, and `schema.sql`.
- `.env` and `config.php` are gitignored — **never commit them**; they contain real DB and Microsoft Graph credentials.

## Deployment

This repo supports two paths. **Docker is the one actually built and tested
in this project** (it's what the `Makefile` drives); the Plesk/shared-hosting
path exists via `config.example.php` but hasn't been exercised here — check
with whoever manages the target server before relying on it as-is.

### Option A — Docker (recommended)

On a server with Docker and Docker Compose installed:

1. Get the code onto the server. This project isn't a git repository yet — see "First time: version control" below before you push it anywhere.
2. `make setup`, then edit `.env` with **real production values**:
   - `SITE_URL` — your real public URL, e.g. `https://cotonintheelms.co.uk/register`
   - Strong, unique `DB_PASS`, `DB_ROOT_PASS`, `IP_SALT`
   - `ADMIN_PASSWORD_HASH` via `make admin-hash PASS=...`
   - Real Microsoft Graph credentials (see "Email" above)
3. `make build && make up` — builds the image, starts `web` + `db`.
4. Put a reverse proxy (nginx, Caddy, or Traefik) in front of the container to terminate HTTPS and forward to `127.0.0.1:${WEB_PORT}`. **The container itself only serves plain HTTP** — there's no TLS termination built into this repo, and a form collecting personal data should not be served over plain HTTP in production.
5. Point DNS at the server; once the reverse proxy has a valid certificate (e.g. via Let's Encrypt/Certbot, or Caddy's automatic HTTPS), the site is live.
6. `make ps` / `make logs` to confirm both containers are up and healthy.
7. To ship a future change: pull the new code, then `make restart` — rebuilds and recreates only the `web` container, the `db` container and its data are untouched.

**Data persistence**: signups live in the `db_data` Docker volume. Back it up
regularly (e.g. a scheduled `mysqldump` run through `make db-shell` or a cron
job) — `make clean` or removing that volume deletes all signups permanently.

### Option B — Traditional / Plesk hosting (no Docker)

1. Upload all files to the web root (or the subdirectory matching `SITE_URL`, e.g. `/register`).
2. Run `composer install --no-dev` once (via SSH or Plesk's Composer tool). There are no real dependencies left after the switch to Microsoft Graph, but `bootstrap.php` still expects `vendor/autoload.php` to exist.
3. Copy `config.example.php` to `config.php` — ideally outside the web root, per that file's own comment — and fill in real values (db, `site_url`, `ip_salt`, `admin_password_hash`, the `graph` block, `confirm_email_subject`).
4. Import `schema.sql` into the MySQL database.
5. Make sure `AllowOverride All` is enabled for this directory so `.htaccess` can actually block direct access to `config.php` etc.
6. PHP 8.1+ required (the code uses the `never` return type).

### First time: version control

There's no git repository here yet, and `.env` currently holds real secrets
(DB passwords, the Microsoft client secret). A `.gitignore` excluding `.env`,
`config.php` and `vendor/` has been added. Before pushing anywhere:

```
git init
git add .
git status   # double-check .env and config.php are NOT listed
git commit -m "Initial commit"
```
