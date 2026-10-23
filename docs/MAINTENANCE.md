# Maintenance mode

Headline Recorder has one maintenance page (black background, the HR logo, a short note to readers and the newsroom
email). It is shown in three different ways, so it keeps working whatever is being fixed.

| Layer | Use it when | How it is switched | Needs PHP? |
|---|---|---|---|
| 1. WordPress switch | Planned work while the site itself runs: redesigns, imports, plugin clean-up | wp-admin > **Maintenance** | yes |
| 2. Update page | Automatic. WordPress shows it for a minute or two while it updates core, plugins or themes | nothing to do | yes (very little) |
| 3. Server-level page | PHP update day, or PHP / WordPress is broken and wp-admin will not open | wp-admin > Maintenance > "Server-level page", or one file in cPanel File Manager | **no** |

Every layer answers **HTTP 503 with Retry-After**. That tells Google and other search engines "temporarily closed,
come back later", so rankings are kept. The page also carries `noindex` and is never stored in any cache
(LiteSpeed, Cloudflare or the browser), so nobody keeps seeing it after the site is back.

## 1. The WordPress switch (wp-admin > Maintenance)

The **Maintenance** item sits in the left-hand menu of wp-admin, just under Headline Recorder. It shows "ON" in red
while maintenance is active.

1. Open **Maintenance**.
2. Optional: change the headline, the message and the contact email. Keep the message plain and non-technical.
3. Pick **Expected to take** (default 1 hour). It is only a hint for search engines and is not shown to readers.
4. Press **Preview the page** to see exactly what readers will get (nothing is switched on by previewing).
5. Turn on **Show the maintenance page to visitors** and press **Save**.
6. To finish: turn the switch off and press **Save**. The site is open again at once.

Saving the switch also empties LiteSpeed Cache and queues a Cloudflare purge, so readers do not keep getting the
old pages (or, afterwards, the maintenance page).

While it is on:

- Signed-in **editors and administrators** see the whole site as usual, with a red "Maintenance mode is ON" label in
  the top toolbar. Everybody else, including signed-in readers, sees the maintenance page.
- wp-admin, the sign-in page (`/wp-login.php`), scheduled tasks and WP-CLI keep working.
- The article signer keeps working (its application password, `/wp-json/hr/v1/signing/*`), and so do the public
  verification files: `/.well-known/` (keys, security.txt, certificate renewal), `/signed/` and `robots.txt`.
- Pages, articles, feeds, the public REST API and reader features answer 503.

From a terminal (SSH or cPanel Terminal) the same switch is:

```
wp hr-maintenance on
wp hr-maintenance off
wp hr-maintenance status
```

## 2. The update page (automatic)

When WordPress updates itself or a plugin, it briefly puts the site in "maintenance" and looks for the file
`wp-content/maintenance.php`. The theme installer places ours there, so readers see the same designed page (with a
10-minute Retry-After) instead of WordPress's plain grey message. If another plugin already put its own
`maintenance.php` there, the installer leaves it alone and the status table says so.

If an update is interrupted and the site stays on this page, delete the file `.maintenance` in the site folder
(cPanel File Manager, "Show Hidden Files" on). That is standard WordPress behaviour.

## 3. The server-level page (works without PHP)

`maintenance.html` in the site folder is a fully self-contained page: no PHP, no database, no other files. A small
block in `.htaccess` lets the web server (LiteSpeed or Apache) show it with 503 to every visitor while a switch
file named `hr-maintenance.on` exists. The block does nothing while that file is missing.

Who still gets through: your own IP address, wp-admin and the sign-in page (useful when PHP works), `/.well-known/`,
`/signed/`, the signing API, `wp-cron.php` and `robots.txt`. The IP check also trusts Cloudflare's `CF-Connecting-IP`
header; if the server can be reached without Cloudflare, someone who knows your IP could forge it and see the normal
site, nothing more. The page is not a security shield: wp-login.php and wp-admin still run PHP, so patch PHP first.

### With wp-admin (easiest)

wp-admin > **Maintenance** > **Server-level page** > **Turn server-level page on**. This writes `maintenance.html`
with your current headline and message, adds the block at the top of `.htaccess` (the file as it was before is saved as
`.htaccess.hr-maintenance-backup`), adds your current IP address to the allowlist and creates the switch file.
Right after writing, WordPress asks the server for a static file (straight from the server, and through the public
address with Cloudflare's cache bypassed). Unless the server provably still answers, the block is taken out again at
once and nothing changes: an HTTP 500 or no answer at all both count as failure. It then switches the page on and
checks the home page again; on a 500, or if the server ignores the rules, the switch file is deleted at once. The
block never closes wp-admin or wp-login.php, so you cannot be locked out. `.htaccess` is edited in place under a
file lock (as WordPress, LiteSpeed Cache and AIOS do), every other block stays byte for byte, and the file's line
endings and byte order mark are kept. On hosts that do not let a site request itself, the button refuses and you use
the cPanel steps below. Press
**Turn server-level page off** to reopen the site; the block stays but does nothing.

### With cPanel only (when wp-admin does not open)

1. cPanel > **File Manager** > `public_html`. Click **Settings** and tick **Show Hidden Files**.
2. If `.htaccess` does not yet contain `# BEGIN Headline Recorder maintenance`: open `bundled/maintenance-mode.conf`
   inside the theme folder (`wp-content/themes/headlinerec-child/bundled/`), copy it all, and paste it at the
   **very top** of `.htaccess`. Replace `203.0.113.10` with your own IP address (search "what is my IP") or delete
   those two lines. Save.
3. Check that `public_html/maintenance.html` exists. If not, upload it from the theme's `bundled/webroot/`.
4. **Turn on:** in `public_html` click **+ File** and create an empty file named `hr-maintenance.on`.
5. **Turn off:** delete (or rename) `hr-maintenance.on`.

To remove the block for good, delete everything from `# BEGIN Headline Recorder maintenance` to
`# END Headline Recorder maintenance` in `.htaccess`.

Notes:
- Cloudflare may keep showing articles it already has stored while the server is in maintenance. That is fine:
  readers get real pages instead of an error.
- While the block is in `.htaccess`, any 503 the server gives shows this page, and carries the Retry-After value
  written in the block (wp-admin writes your "Expected to take" choice; the manual block says 1 hour).
- If the site shows "500 Internal Server Error" right after pasting the block by hand, remove the block again: the
  server does not accept one of its lines. Then tell the developer which server software the host runs.

## PHP update day (step by step)

PHP must be at **8.4.27 or later** (fixes php2shell, CVE-2026-109618 and CVE-2026-109619). Allow 30 minutes.

1. **Tell readers:** wp-admin > **Maintenance**, set "Expected to take" to 1 hour, switch on, **Save**.
2. **Make it PHP-proof:** on the same screen press **Turn server-level page on**. From now on readers see the page
   even if PHP stops working. You (your IP address) and wp-admin still get through.
   If wp-admin is already down, use "With cPanel only" above instead.
3. **Update PHP:** cPanel > **Select PHP Version** (CloudLinux) or **MultiPHP Manager**. Choose PHP 8.4, newest
   build (8.4.27 or later), and apply it to headlinerecorder.com. If no patched build is listed, ask the host:
   "Please install the PHP release that fixes CVE-2026-109618 and CVE-2026-109619 for our PHP 8.4 (8.4.27 or later)."
4. **Check:** open the home page and two articles (you still see the real site), then wp-admin > **Tools > Site
   Health**: it must no longer warn about php2shell. Also open wp-admin > **Headline Recorder** and press
   **Apply PHP settings** if any row says "waiting".
5. **If something is broken:** stay in maintenance, switch back to the previous PHP version in cPanel, and contact
   the developer with what you saw. Readers keep seeing the maintenance page meanwhile.
6. **Reopen:** wp-admin > **Maintenance** > **Turn server-level page off**, then switch maintenance off and **Save**.
   If wp-admin is not reachable, delete `hr-maintenance.on` in File Manager instead.
7. Open the site in a private browser window to confirm readers see the normal home page.

## Installing and updating

The theme zip (`scripts/build-theme-zip.sh`) carries everything:

| In the zip (theme folder) | Installed to | By |
|---|---|---|
| `bundled/mu-plugins/hr-maintenance.php` | `wp-content/mu-plugins/` | Headline Recorder > Install (or Maintenance > Install maintenance controls) |
| `bundled/wp-content/maintenance.php` | `wp-content/maintenance.php` | Install, only if missing or ours |
| `bundled/webroot/maintenance.html` | site folder (`public_html/`) | Install, only if missing or ours |
| `bundled/maintenance-mode.conf` | top of `.htaccess` | Maintenance screen button, or by hand |

Right after the theme is uploaded, the **Maintenance** menu item is already there; until the must-use plugin is
installed it offers a one-click **Install maintenance controls**.

## For developers

- Source files: `wp-content/mu-plugins/hr-maintenance.php` (switch, gate, screen, server-level writer, WP-CLI),
  `deploy/wp-content/maintenance.php` (update drop-in), `deploy/webroot/maintenance.html` (static page),
  `infra/litespeed/maintenance-mode.conf` (manual `.htaccess` block), `wp-content/themes/headlinerec-child/inc/installer.php`.
- The design lives in one function, `hr_maintenance_document()`. The drop-in reuses it (falling back to
  `maintenance.html`, then a minimal page) and `maintenance.html` is its output with the default copy. After
  changing the design or the default copy, regenerate the static page:

  ```
  php -r 'define("ABSPATH", "/"); define("HR_MAINTENANCE_TEMPLATE_ONLY", true); require "wp-content/mu-plugins/hr-maintenance.php"; echo hr_maintenance_document();' > deploy/webroot/maintenance.html
  ```

  `scripts/build-theme-zip.sh` refuses to build while the two differ.
- Filters: `hr_maintenance_allowed_paths` (front-end path prefixes), `hr_maintenance_allowed_rest` (REST route
  prefixes for guests), `hr_maintenance_allowed_ajax` (admin-ajax actions for guests).
- Every change is logged as an `hr-security` line (`maintenance_on`, `maintenance_off`, `maintenance_updated`,
  `maintenance_server_on|off`, `maintenance_server_rules_removed|reverted`). State changes require
  `manage_options`, a nonce and a same-origin request.
