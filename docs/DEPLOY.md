# Deploy: Headline Recorder (cPanel + LiteSpeed)

Maintained by MSBJ. Security background: `SECURITY.md`; leaked-archive clean-up: `docs/INCIDENT-2026-10-public-archive.md`.

## Security update, October 2026 (php2shell)

**What it is.** "php2shell" is two flaws in PHP itself (CVE-2026-109618 and CVE-2026-109619), fixed on 21 October 2026.
A specially built file upload can make PHP reuse memory it has already freed, which lets an attacker run their own code
on the server without any account. Exploits are public and were used before the fix. Every PHP version before
**8.2.35, 8.3.36, 8.4.27 and 8.5.12** is affected, and PHP 8.1 or older will never be fixed. Only a PHP update closes
both flaws.

**Check the version.** wp-admin > **Tools > Site Health > Info > Server > PHP version**. The bundled must-use plugin
`hr-php-guard.php` also shows a red notice to administrators and a Site Health check ("PHP is patched against
php2shell") that says plainly whether this server is safe, with no outside requests.

**Update in cPanel.** cPanel > **Select PHP Version** (CloudLinux) or **MultiPHP Manager**: choose PHP 8.4 and make sure
the build is **8.4.27 or later**. If the newest build offered is older, open a ticket with the host asking for "the PHP
release that fixes CVE-2026-109618 and CVE-2026-109619 for our lsphp / ea-php / alt-php 8.4". Hosts that backport fixes
without raising the version number should confirm that in writing; then the notice can be dismissed.

**What the bundle already does.**
- `bundled/webroot/.user.ini` (source `deploy/webroot/.user.ini`) sets `session.upload_progress.enabled = Off`, the
  interim mitigation (WordPress does not use that feature), plus safe session and error-display settings.
  wp-admin > **Headline Recorder > Install** (or **Apply PHP settings** on the same page) merges it into
  `public_html/.user.ini`, keeps every other line and saves the original once as `.user.ini.hr-backup`. The page lists
  the live values; PHP rereads `.user.ini` every 5 minutes (`user_ini.cache_ttl`), so allow that long.
  To undo it, open cPanel > File Manager (Settings > Show Hidden Files), delete `public_html/.user.ini` and rename
  `.user.ini.hr-backup` to `.user.ini` (if there was no backup, the file did not exist before: just delete it).
  Saving PHP options later in cPanel's **MultiPHP INI Editor** rewrites `.user.ini`: press **Apply PHP settings**
  again afterwards.
- `bundled/htaccess-hardening.conf` repeats the setting with `php_flag` only inside `<IfModule LiteSpeed>` and
  `<IfModule mod_php.c>` / `<IfModule php_module>` (outside those, Apache with PHP-FPM answers HTTP 500). If pasting it
  ever causes HTTP 500 on LiteSpeed, remove the LiteSpeed block and rely on `.user.ini`.
- The mitigation only blocks the upload-progress path of CVE-2026-109618; the multipart parser flaw (CVE-2026-109619)
  needs the PHP update.

**WordPress itself.** "wp2shell" (CVE-2026-63030, REST API batch endpoint) affected WordPress 6.9.0 to 6.9.4 and 7.0.0
to 7.0.1 and was fixed in 6.9.5 and 7.0.2. This site runs **WordPress 7.1.3: not affected**. The same Site Health
section checks the core version on every visit and says when WordPress has an update waiting.

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
7. Cloudflare in front for post-quantum TLS and edge caching: `infra/cloudflare/README.md` and
   `docs/PERFORMANCE.md` ("Cloudflare setup"). For automatic cache purges add `HR_CF_ZONE_ID` and `HR_CF_API_TOKEN`
   (token permission Zone > Cache Purge only) to `wp-config.php`.

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
