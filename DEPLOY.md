# Deploying to production

Step-by-step runbook for the Docker path (the one this repo's `Makefile` is
built for). See [README.md](README.md) for local development, configuration
reference, and the untested Plesk/shared-hosting alternative.

## 1. Prerequisites on the server

- A Linux server (VPS or similar) with SSH access
- Docker Engine + the Docker Compose plugin
- `git` and `make`
- A domain (or a path under an existing domain) you can point at this server

Install Docker (Ubuntu/Debian):
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER   # log out and back in for this to take effect
```
`git` and `make` are usually already installed; if not: `sudo apt install -y git make`.

## 2. Get the code onto the server

```bash
git clone git@github.com:tombrooksdev/CotonInTheElmsRegistry.git
cd CotonInTheElmsRegistry
```
This needs the server's own SSH key added as a deploy key on the GitHub repo
(Settings → Deploy keys), or clone over HTTPS with a personal access token
instead.

## 3. Configure `.env`

```bash
make setup
```
This creates `.env` from the template. Edit it (`nano .env`) and fill in:

- `SITE_URL` — the real public URL, e.g. `https://cotonintheelms.co.uk/register`
- `DB_PASS`, `DB_ROOT_PASS` — strong, unique passwords
- `IP_SALT` — any long random string, e.g. generate one with `openssl rand -hex 32`
- `ADMIN_PASSWORD_HASH` — run `make admin-hash PASS='yourrealpassword'`, then paste the output in, **doubling every `$`** (a hash like `$2y$10$...` becomes `$$2y$$10$$...`) or Docker Compose will mangle it
- `MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`, `MICROSOFT_SENDER_EMAIL` — from the Azure AD app registration (see the README's "Email" section for the required `Mail.Send` application permission)
- `CONFIRM_EMAIL_SUBJECT` — leave as-is or customise

## 4. Build and start

```bash
make build
make up
make ps      # both containers should show "healthy"
```
The site now listens on `127.0.0.1:${WEB_PORT}` (default 8080) — **not yet
reachable from the internet**. That's the next step.

## 5. Reverse proxy + TLS

Nothing in this repo terminates HTTPS — a reverse proxy on the host handles
that. Two common options; pick one.

### Option 1: Caddy (simplest — automatic HTTPS)

```bash
sudo apt install -y caddy
```

If the register lives at a path under an existing domain (matches the
`SITE_URL` example above), add to `/etc/caddy/Caddyfile`:
```
cotonintheelms.co.uk {
    handle_path /register/* {
        reverse_proxy 127.0.0.1:8080
    }
    # ...rest of the domain's existing config...
}
```
Or, for its own (sub)domain instead:
```
register.cotonintheelms.co.uk {
    reverse_proxy 127.0.0.1:8080
}
```
```bash
sudo systemctl reload caddy
```
Caddy fetches and renews the Let's Encrypt certificate automatically — no
further TLS setup needed.

### Option 2: nginx + certbot

```nginx
server {
    listen 80;
    server_name cotonintheelms.co.uk;

    location /register/ {
        proxy_pass http://127.0.0.1:8080/;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
```bash
sudo certbot --nginx -d cotonintheelms.co.uk
```
`X-Forwarded-Proto` matters here specifically: `admin.php`'s session cookie is
only marked `secure` when that header (or a direct HTTPS connection) says so.

## 6. DNS

New (sub)domain: add an A record (and AAAA if you have IPv6) pointing it at
the server's IP, and wait for it to propagate before requesting a
certificate. Existing already-live domain with this app mounted at a path:
DNS is already correct — you only needed the reverse-proxy route above.

## 7. Verify

Visit the real URL, submit a test signup, confirm the email arrives with the
logo showing (it's an inline attachment, so it doesn't depend on `SITE_URL`
being reachable from Microsoft's mail servers), and log into `/admin.php`
with the password you hashed in step 3.

## 8. Shipping future changes

On the server:
```bash
make deploy
```
This is just `git pull && make restart` — rebuilds and recreates only the
`web` container; `db` and its data are untouched.

## 9. Backups

Signups live entirely in the `db_data` Docker volume.

```bash
make backup                                               # -> backups/coton-register-<timestamp>.sql
make restore FILE=backups/coton-register-20260101-120000.sql   # restore from a dump - overwrites current data
```

`make clean` (or manually removing the `db_data` volume) deletes all signups
permanently — always run `make backup` first if you're ever about to do that.

For unattended daily backups, add to the server's crontab (`crontab -e`):
```
0 2 * * * cd /path/to/CotonInTheElmsRegistry && make backup >> backup.log 2>&1
```
`backups/` is gitignored — dumps contain real personal data and must not be committed.
