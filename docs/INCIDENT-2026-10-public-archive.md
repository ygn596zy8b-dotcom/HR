# Incident: full site archive published on Zenodo (18 Oct 2026)

**What happened.** The complete website was uploaded as a *public, open-access* record:
https://doi.org/10.5281/zenodo.23458635 (`well-known.zip`, `esofkogo_wp325.sql`). Anyone can download it, and
Zenodo records are permanent and indexed.

**What it exposes.**

| Item | Where in the archive | Risk |
|---|---|---|
| Database name, user, **password**, host | `wp-config.php`, `wp-content/aiowps_backups/backup.wp-config.php` | Direct DB access if the host allows remote MySQL; credential reuse |
| WordPress **auth keys and salts** (8) | `wp-config.php` | Forging login cookies for any user, including the administrator |
| `DUPLICATOR_AUTH_KEY` | `wp-config.php` | Duplicator package access |
| All **user accounts**: logins, emails, password hashes | SQL dump `wp6m_users` | Offline cracking of staff passwords; phishing of staff |
| **SBP EasyData API key** | SQL dump, Code Snippet #8 | Abuse of the State Bank API under your name |
| **SMTP password** and **Gmail OAuth client secret** (WP Mail SMTP), **Mailchimp API key**, **Google Site Kit credentials** — all confirmed present | SQL dump `wp6m_options` (`wp_mail_smtp`, `mc4wp`, `googlesitekit_credentials`) | Sending mail as you; mailing-list access and export; analytics access. Plugin-side encryption does not help: its keys/salts are in the same archive |
| A full **Duplicator backup** (`*.daf`, `installer.php.bak`) | `wp-content/duplicator-backups/` | Same data again; installer endpoints if the files are still on the server |
| Error/debug logs | `error_log`, `wp-content/debug.log` | Paths, plugin versions, possibly request data |

## Status

- **2026-10-19 — owner confirmed all exposed credentials have been rotated** (DB password, salts, staff passwords,
  API keys). Still worth doing: ask Zenodo to delete the record (it also holds readers' emails and password hashes,
  which rotation does not un-publish), and remove backups/logs from the server (steps 6–8 below).

## Do now (in this order)

1. **Ask Zenodo to remove the record.** Zenodo → the record → "Request deletion" (or support@zenodo.org) citing
   personal data and credentials. Until it is gone, assume everything below is public.
2. **Rotate WordPress salts.** Generate new ones (https://api.wordpress.org/secret-key/1.1/salt/) and replace the 8
   `define()`s in `wp-config.php`. This signs everyone out and invalidates any forged cookie.
3. **Change the database password** (cPanel → MySQL Databases → change password for the DB user) and update
   `DB_PASSWORD` in `wp-config.php`. Disable remote MySQL access in cPanel if it is enabled.
4. **Reset every staff password** (administrator, editor, authors) to new, unique passwords (12+ characters, ideally
   from a password manager). The new platform then upgrades them to Argon2id at next login and requires a passkey or
   two-step code for staff (`HR_ENFORCE_STAFF_MFA`).
5. **Revoke and reissue API keys:** SBP EasyData key; the SMTP mailbox password used by WP Mail SMTP; the Gmail
   OAuth client secret (Google Cloud Console → Credentials → reset secret); Mailchimp API key (Account → Extras →
   API keys → revoke + create); Google Site Kit (Site Kit → Settings → Reset, then reconnect). Then delete Code
   Snippet #8 (it still contains the old SBP key).
6. **Change `DUPLICATOR_AUTH_KEY`**, delete `wp-content/duplicator-backups/*`, `wp-content/ai1wm-backups/*`,
   `wp-content/aiowps_backups/backup.wp-config.php`, `error_log`, `wp-content/debug.log` from the server.
7. **Turn off debug output in production:** in `wp-config.php` set `WP_DEBUG` false, `WP_DEBUG_DISPLAY` false and
   remove `@ini_set( 'display_errors', 1 );` (the live config currently shows PHP errors to visitors). Errors go to
   Sentry instead.
8. **Check for intrusion:** review AIOS audit log and users list for unknown administrators, recently modified PHP
   files (cPanel File Manager → sort by date, or `find public_html -name '*.php' -newermt 2026-10-18`), and unknown
   scheduled tasks.
9. **Tell your users if needed.** The dump contains staff and subscriber emails. If you have readers in the EU/UK,
   a personal-data breach may need to be reported (GDPR Art. 33, 72 hours) — take legal advice.

## Prevent a repeat

- Never publish backups. Share code only (this repository contains no secrets: `.gitignore` blocks key files and
  `wp-config.php`; CI fails if a secret-looking file is committed).
- Keep backups encrypted and off the web root (Duplicator storage outside `public_html`, or the host's backups).
- `infra/litespeed/htaccess-hardening.conf` now blocks backup folders, dumps, logs and installers at the web server.
