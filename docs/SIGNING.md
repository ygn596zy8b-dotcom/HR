# Content signing

Every published article on Headline Recorder, plus the site feed and the podcast feed, carries a post-quantum
digital signature (ML-DSA-87 together with Ed448). Anyone can check at `/verify/` that an article is exactly what
we published. This page explains how signing works on the server, how to switch it on, and what to do when
something goes wrong.

## How it works

- **When an article is published or edited**, WordPress signs it in the same moment, before the editor's screen
  finishes saving. Signing one article takes a fraction of a second.
- **Every 5 minutes**, a background job (WP-Cron) signs anything still pending: articles edited outside the editor,
  articles whose author name changed, older articles signed with a key that was later revoked. It works newest
  first, for about 20 seconds per run, and never runs twice at the same time.
- **Feeds**: shortly after any article or podcast episode changes, the same job fetches `/feed/` and
  `/podcast/feed/` as a reader would, and signs a fresh snapshot if the feed changed. Signed snapshots are served at
  `/signed/feed.xml` and `/signed/podcast.xml`.
- **What is signed** is the stored article (address, headline, byline, publication and update dates, and text), not
  the page around it, so adverts, widgets and caching never break a signature.
- **Every signature is checked before it is stored.** The server verifies both the ML-DSA-87 and the Ed448
  signature with the published public keys; a signature that does not verify is never saved or shown.
- Signatures made on the server are identical in format to the ones the offline toolkit makes, so the `/verify/`
  page, partner tools and the command-line verifier treat them the same way.

## Switching it on (one time)

1. In WordPress, go to **Settings > Content signing**.
2. Under **Signing key**, choose one option:
   - **Generate a server signing key (recommended).** Type your WordPress password (and your two-step code if you
     use one) and press **Generate key**. The site creates its own key and publishes its public half
     automatically.
   - **Import your existing key.** Choose your `signing.secret.json` file (or paste its contents), type your
     password and press **Import key**. The key must already be in the published keyset. The site checks that the
     file matches the published key and that a test signature verifies before it accepts it.
3. If you generated a key, add the DNS record shown on the screen (it starts with `_pqsig.`) at your DNS provider,
   so partners can pin the new key. Keep the old record too while articles signed with the old key exist.
4. Press **Sign all pending now** and leave the page open until the bar reaches 100%. The 1,300-plus older
   articles take a few minutes. You can stop and continue later.
5. Recommended: copy the `define( 'HR_SIGNING_KEK', ... );` line shown under **Key encryption** into
   `wp-config.php` (above "That's all, stop editing!"), reload the page and press **Re-wrap with HR_SIGNING_KEK**.
   Keep a copy of that line with your offline backups.

Automatic signing is on by default. The switch is in **Settings > Headline Recorder > Content signing**, which also
shows how many articles are signed and pending, when the last run happened and any problems in plain language.

From a terminal (cPanel Terminal or SSH): `wp hr sign` runs one batch, `wp hr sign --all` signs everything pending,
`wp hr sign --limit=50` signs at most 50 articles.

## Security model

**Where the key lives.** The secret key is encrypted with AES-256-GCM and stored in one file outside the website
folder: in `HR_PRIVATE_DIR` if you set that in `wp-config.php`, otherwise in a folder named `hr-private` next to
`public_html`. Only if neither can be created does it fall back to a randomly named folder inside `wp-content` with
deny-all rules, and the screen warns you. The folder is readable only by the site's own user (mode 0700, file 0600).
The key is never stored in the database, never written to a log and never shown again after setup.

**What encrypts the key.** The `HR_SIGNING_KEK` line in `wp-config.php` if present (recommended), otherwise a key
derived from the WordPress security keys (salts) in `wp-config.php`. The encryption is also tied to the site address
and the key ID, so the file cannot be moved to another site or relabelled. Be clear about what this buys: both the
`HR_SIGNING_KEK` line and the salts live in `wp-config.php`, so someone who can read `wp-config.php` and the key file
can decrypt the key. Encryption protects the key in backups, file listings and copies of the private folder taken
on their own; `HR_SIGNING_KEK` adds that changing the salts (which some security plugins do) no longer locks the key.
Keep `wp-config.php` at mode 0440 or 0400.

**Who can change it.** Only administrators, and every key action asks for the administrator's password again (plus
their two-step code when they have one). An administrator who signs in only with a passkey needs a password for
this: set one in **Users > Profile** first. Attempts are limited to 10 per 15 minutes and every action, successful or
not, is written to the security log.

**The trade-off.** A key on the server is convenient: nothing waits for a person or an outside service. The cost
is that someone who fully takes over the hosting account (files and `wp-config.php`) could sign content as
Headline Recorder until the key is revoked. That is why a **dedicated server key** is recommended over importing your
offline key:

| | Dedicated server key | Imported offline key |
|---|---|---|
| If the server is compromised | Revoke only the server key; signatures made with the offline key stay trusted | The key behind every older signature must be revoked |
| Partners must pin | Two key IDs (offline and server) | One key ID |
| Offline key | Stays offline, used only for emergencies | Now also on the server |

**Not connected to GitHub.** Nothing about signing depends on GitHub. The `Sign content` workflow in
`.github/workflows/sign-content.yml` no longer runs on a schedule; it can only be started by hand as an offline
alternative.

## Revoking a key

Revoke a key if you think the server (or wherever the key was kept) was compromised:

1. **Settings > Headline Recorder > Content signing > Public signing keyset**: add `"revoked": "YYYY-MM-DD"` to that
   key's entry and save. From that moment `/verify/` and partners reject its signatures.
2. **Settings > Content signing**: press **Remove key from this server**, then **Generate a server signing key**
   to make a new one. Every article signed with the revoked key is re-signed automatically (or press **Sign all
   pending now**).
3. Update the `_pqsig` DNS record: add the new key ID and remove the revoked one.
4. Change the hosting, WordPress administrator and database passwords, and replace the WordPress salts and
   `HR_SIGNING_KEK` in `wp-config.php` (the new key is then encrypted under the new values).

## DNS pinning

Partners who want certainty do not trust the website alone: they compare the key ID with the DNSSEC-signed TXT record
`_pqsig.headlinerecorder.com`, one record per active key, in the form
`v=pqsig1; alg=ML-DSA-87+Ed448; kid=<64 hex characters>`. The exact line for the installed key is shown on
**Settings > Content signing**. See `infra/dns/DNSSEC.md`.

## Recovery

| What you see | What it means | What to do |
|---|---|---|
| "No server signing key is installed" | Signing was never set up, or the key was removed | Generate or import a key (above) |
| "The signing key could not be decrypted: the WordPress security keys (salts) ... have changed" | Someone or a security plugin replaced the salts in `wp-config.php`, or the site address changed | Generate a new server key (or import the offline key again), then add `HR_SIGNING_KEK` so salts no longer matter |
| "...encrypted with HR_SIGNING_KEK, which is no longer valid" | The `HR_SIGNING_KEK` line was removed or edited | Put the original line back from your backup, or generate or import the key again |
| "The signing key file is missing" | The `hr-private` folder was deleted or not copied during a move | Generate or import the key again |
| "This key was revoked" or "does not match the published key" | The keyset was edited | Generate a new server key, or fix the keyset |
| "The site could not fetch its own feed" | A firewall or Cloudflare blocked the server's request to itself | Allow the server's own IP, or check that `/feed/` loads in a private window |
| "WP-Cron is switched off" | `DISABLE_WP_CRON` is set | In cPanel > Cron Jobs, run `wp-cron.php` every 5 minutes (or `wp hr sign`) |
| "The signer's self-test failed" | The PHP version on the host produces wrong results | Use the offline signer below and tell your developer the PHP version |

**Generating a second server key** replaces the installed one and destroys its secret. The old key stays in the
keyset as active, so what it signed stays valid; it can no longer sign anything. Mark it `"revoked"` only if you
believe it leaked (that re-signs everything it signed).

**Moving the site to a new host**: copy the `hr-private` folder (or your `HR_PRIVATE_DIR`) together with
`wp-config.php`. If the address of the site changes, the key cannot be decrypted (by design): generate a new
server key after the move.

**Offline alternative**: if the server cannot sign for any reason, the old toolkit still works. On a trusted
computer: `cd security/pq && npm ci && HR_WP_USER=<signer user> HR_WP_APP_PASSWORD='<application password>' node
wp-sign.mjs --site https://headlinerecorder.com --key signing.secret.json --pin <key id>`. It needs a WordPress user
with the "Content signer" role and an application password. Uploads go through the same checks as server signatures.

## For developers

- Code: `wp-content/plugins/hr-platform/includes/signing/` (`signer.php` automatic signing, `keystore.php` key at
  rest, `crypto.php` signature documents, `admin.php` the screen, `cli.php` WP-CLI, `store.php` the signature table and its migration, `rest.php` the queue and the
  shared store functions, `pq/` the pure-PHP ML-DSA-87 and Ed448).
- Header format, byte for byte as `security/pq/pq.mjs` writes it: `{"v":1,"alg":"ML-DSA-87+Ed448","kid":...,
  "type":"article"|"feed","url":...,"sha512":...,"signedAt":"YYYY-MM-DDTHH:MM:SS.mmmZ"}`, both algorithms signing
  the header bytes with context `pqsig-v1`.
- Storage: article signatures live in the table `{prefix}hr_pqsig` (post_id, kid, sha512, signed_at, doc), not in
  post meta, because WordPress loads all meta of every post in every archive, home page and feed loop (a document
  is about 11 KB). Only the small stale marker `_hr_pqsig_stale` stays in post meta. Older installs are migrated once,
  automatically (WP-Cron event `hr_pqsig_migrate`, in batches, safe to interrupt) or with `wp hr sign migrate`; until
  it finishes, reads fall back to the old meta. Option `hr_pqsig_migrated` records when it finished. Every stored
  signature fires `do_action( 'hr_pqsig_stored', $post_id, $kid )` plus the LiteSpeed purges the edge module follows.
- Options: `hr_pqsig_server_key` (non-secret key facts), `hr_pqsig_autosign_status`, `hr_pqsig_autosign_errors`,
  `hr_pqsig_selftest`, `hr_pqsig_autosign_lock`. Cron hooks: `hr_pqsig_autosign` (every 5 minutes) and
  `hr_pqsig_autosign_retry`. Sentry cron monitor slug: `pq-autosign`.
- Tests: `wp eval-file wp-content/plugins/hr-platform/tests/signing-test.php` and `cd security/pq && npm test`.
