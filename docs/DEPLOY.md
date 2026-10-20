# Deploy — Headline Recorder (cPanel + LiteSpeed)

Maintained by MSBJ. Security background: `SECURITY.md`; leaked-archive clean-up: `docs/INCIDENT-2026-10-public-archive.md`.

## 1. Build the bundle

```sh
scripts/package.sh            # → dist/headline-recorder-deploy.zip (theme, hr-platform with vendor/, mu-plugins, infra, docs)
```

The bundle never contains `*.secret.json`, `node_modules`, tests, `assets/src` or source maps.

## 2. Install

1. Back up the live site (cPanel → Backup) and keep the copy **off** the web root.
2. Create `HR_PRIVATE_DIR` outside the web root: `mkdir -m 0750 /home/esofkogo/hr-private`.
3. Fill `wp-config.php` from `deploy/wp-config.sample.php`. Every `CHANGE_ME` gets a new value; set the file to `0440`.
   Check the host's PHP has Argon2id: `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'` (otherwise WordPress keeps bcrypt).
4. Upload the zip contents over `public_html/`. Paste `infra/litespeed/htaccess-hardening.conf` at the top of
   `public_html/.htaccess` (above `# BEGIN WordPress`), then deactivate "Headers Security Advanced & HSTS WP".
5. wp-admin → Plugins → activate **Headline Recorder Platform** (creates `wp6m_hr_passkeys`, `wp6m_hr_reads`), then
   Settings → Permalinks → Save.
6. Signer account (application password only, never a browser login):
   `wp user create pqsigner signer@headlinerecorder.com --role=hr_signer --user_pass="$(openssl rand -base64 33)"` and
   `wp user application-password create pqsigner signer-ci --porcelain` → store it as the CI secret `HR_WP_APP_PASSWORD`.
7. Cloudflare in front for post-quantum TLS: `infra/cloudflare/README.md`.

## 3. Database: least privilege

MariaDB has no row-level security. Per-user isolation is enforced in PHP: every per-user query carries
`user_id = %d` / `post_author` from the signed-in session (see `SECURITY.md`). The database account itself should only
hold what WordPress needs, bound to `localhost`:

| Account | Used by | Privileges on `<db>.*` only |
|---|---|---|
| `<db>_app` | `wp-config.php`, day to day | `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, CREATE TEMPORARY TABLES, LOCK TABLES` |

```sql
CREATE USER '<db>_app'@'localhost' IDENTIFIED BY '<new random password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, CREATE TEMPORARY TABLES, LOCK TABLES ON `<db>`.* TO '<db>_app'@'localhost';
```

- Never grant `ALL PRIVILEGES`, `FILE`, `PROCESS`, `SUPER`, `GRANT OPTION` or any privilege `ON *.*`, and never a `'%'` host.
  On cPanel: MySQL Databases → add user to database → tick only the privileges above. Remote MySQL: no hosts.
- `CREATE/ALTER/INDEX/DROP` are needed because core, Yoast and this plugin migrate their own tables during updates.
  Stricter option: give the day-to-day account DML only (`SELECT, INSERT, UPDATE, DELETE`) and switch `DB_USER` to a
  second account with the DDL privileges only while running updates.

## 4. Remove what the site does not use

- Delete inactive plugins: **Duplicator** and its `wp-content/duplicator-backups/`, **All-in-One WP Migration** + Unlimited
  Extension and `wp-content/ai1wm-backups/`, Headers Security Advanced & HSTS WP (replaced by the hardening mu-plugin),
  Easy Login WooCommerce (no WooCommerce), IndexNow, and any of Google Site Kit / WP Mail SMTP you will not reconnect
  (deleting them also drops their stored, leaked credentials). Delete the inactive theme **NewSquare**.
- All-in-One WP Security is inactive but its firewall loader still runs from `mu-plugins/aios-firewall-loader.php` and
  `wp-config.php`: either reactivate AIOS or remove both includes.
- Code Snippets: delete snippet #8 "SBP data fetch" (it still holds the old SBP API key in its disabled code).
- Then update everything: `wp core update && wp plugin update --all && wp theme update --all` (or Dashboard → Updates).

## 5. Credentials

- No account may be called `admin`/`administrator`; the administrator login is `headlinerecorder`. Every staff password
  is new (12+ characters, checked against Have I Been Pwned) and staff must enrol a passkey or two-step code before the
  dashboard opens (`HR_ENFORCE_STAFF_MFA`).
- No default passwords ship anywhere: `deploy/wp-config.sample.php` holds `CHANGE_ME` placeholders only.

## 6. After deploying, check

```sh
curl -sI https://headlinerecorder.com/ | grep -iE 'strict-transport|content-security|x-frame|permissions'
curl -s -o /dev/null -w '%{http_code}\n' https://headlinerecorder.com/wp-json/wp/v2/users          # 404
curl -s -o /dev/null -w '%{http_code}\n' https://headlinerecorder.com/xmlrpc.php                   # 403
curl -s -o /dev/null -w '%{http_code}\n' https://headlinerecorder.com/wp-content/debug.log         # 403
curl -s -o /dev/null -w '%{http_code}\n' https://headlinerecorder.com/wp-content/uploads/hr-sealed/ # 403
curl -sI -H 'Origin: https://evil.example' https://headlinerecorder.com/wp-json/ | grep -i access-control-allow-origin  # nothing
```
