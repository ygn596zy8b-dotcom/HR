# Post-quantum TLS for readers on shared hosting (Cloudflare in front of LiteSpeed)

headlinerecorder.com runs on cPanel + LiteSpeed shared hosting. You cannot change the server's TLS library there,
so the quickest way to give every reader post-quantum key exchange (protection against "harvest now, decrypt later")
is to put Cloudflare in front of the site. On a VPS, use `infra/nginx/` instead.

What you get:

- **Reader ↔ Cloudflare:** hybrid **X25519MLKEM768** key exchange (ML-KEM, FIPS 203) for every browser that offers it
  (current Chrome, Edge, Firefox, Safari). Older clients fall back to classical X25519.
- **Cloudflare ↔ origin:** post-quantum key exchange to the origin is used when the origin's TLS stack supports it;
  otherwise classical TLS 1.3 over the "Full (strict)" connection below. Ask the host whether their LiteSpeed build
  (OpenSSL ≥ 3.5 / BoringSSL) offers X25519MLKEM768; if not, the origin hop is classical but authenticated.
- DNSSEC with one click, CAA, WAF and rate limiting in front of WordPress.

Honest limit: browsers negotiate ML-KEM-768 hybrids today; no browser offers ML-KEM-1024 yet. Article signatures
(ML-DSA-87) and submission sealing (ML-KEM-1024) are category 5 and do not depend on TLS at all.

## Setup checklist

1. **Add the site** to Cloudflare (Free plan is enough), import DNS, switch the nameservers at the registrar.
2. **SSL/TLS → Overview:** `Full (strict)`. Keep a valid certificate on the origin (cPanel AutoSSL is fine).
3. **SSL/TLS → Edge Certificates:**
   - Minimum TLS version: **TLS 1.2** (1.3 if your audience allows; check analytics for old Android).
   - TLS 1.3: **On**. Always Use HTTPS: **On**. Automatic HTTPS Rewrites: **On**.
   - HSTS: max-age 2 years, include subdomains, preload, only after every subdomain serves HTTPS
     (the origin already sends the same header from `infra/litespeed/htaccess-hardening.conf`).
4. **SSL/TLS → Origin Server:** enable **Authenticated Origin Pulls** and, on the origin, only accept Cloudflare IPs
   for HTTP(S) if the host allows it (cPanel → IP Blocker / ModSecurity, or ask support).
5. **DNS → Settings:** enable **DNSSEC**, then add the DS record Cloudflare shows at the registrar.
   Add CAA records from `infra/dns/DNSSEC.md` (Cloudflare's own CAs need `letsencrypt.org`, `pki.goog`, `ssl.com`
   or `digicert.com` entries if you use Universal SSL; Cloudflare lists the exact ones on the CAA page).
6. **Security → WAF → Custom rules:**
   - Block `http.request.uri.path eq "/xmlrpc.php"`.
   - Managed challenge on `/wp-login.php` and `/account/login/` when `cf.threat_score gt 10`.
   - Block `http.request.uri.path contains "/wp-content/duplicator-backups"` (defence in depth).
7. **Security → WAF → Rate limiting rules:** `/account/login/`, `/account/register/`, `/account/forgot/`,
   `/wp-login.php`: 10 requests / 1 minute per IP → block 10 minutes. (The application also rate-limits.)
8. **Caching:** follow `docs/PERFORMANCE.md`, "Cloudflare setup (exact clicks)": three Cache Rules (static files,
   anonymous HTML that respects the site's own `Cache-Control`, and a last rule that bypasses the cache for the
   `wordpress_logged_in_`, `wordpress_sec_`, `wp-postpass_`, `comment_author_` and `hr_li` cookies and for
   `/account/`, `/dashboard/`, `/thank-you/`, `/reading/`, `/wp-admin/`, `/wp-json/`, `/wp-login.php`), Smart Tiered
   Cache, HTTP/3, Early Hints, and the Cache Purge API token (`HR_CF_ZONE_ID`, `HR_CF_API_TOKEN` in `wp-config.php`).
   Keep **Bot Fight Mode off**: it would challenge feed readers, podcast apps and the content signer.
9. **Real visitor IPs for rate limits:** in `wp-config.php` add

   ```php
   define( 'HR_TRUSTED_PROXY_HEADER', 'HTTP_CF_CONNECTING_IP' );
   // Current list: https://www.cloudflare.com/ips/ (update when Cloudflare changes it).
   define( 'HR_TRUSTED_PROXIES', '173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,104.24.0.0/14,172.64.0.0/13,131.0.72.0/22,2400:cb00::/32,2606:4700::/32,2803:f800::/32,2405:b500::/32,2405:8100::/32,2a06:98c0::/29,2c0f:f248::/32' );
   ```

   The plugin only trusts `CF-Connecting-IP` when the request really comes from one of those ranges, so the header
   cannot be spoofed by visitors who bypass Cloudflare.

## Verify post-quantum key exchange

- Chrome: open the site → DevTools → Security → "Connection … key exchange **X25519MLKEM768**".
- Command line (OpenSSL ≥ 3.5): `openssl s_client -connect headlinerecorder.com:443 -groups X25519MLKEM768 </dev/null 2>/dev/null | grep -i "negotiated\|group"`
- https://pq.cloudflareresearch.com shows whether your browser connection is post-quantum.
