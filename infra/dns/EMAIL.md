# Email domain hardening (anti-spoofing)

A news brand is a phishing target: anyone can send mail "from" `editorial@headlinerecorder.com` unless the domain
says otherwise. These records make receivers reject forgeries and require TLS for mail to us. All of them are
plain DNS records at the DNS host (Cloudflare or cPanel Zone Editor).

Find the real sending services first: cPanel mail (the host), WP Mail SMTP's configured provider, Mailchimp, and
Google Workspace/Microsoft 365 if used. Every service that sends as `@headlinerecorder.com` must appear in SPF and
sign with DKIM, or DMARC will (correctly) reject its mail.

## 1. SPF — who may send

```dns
headlinerecorder.com.  TXT  "v=spf1 include:<host-or-provider-spf> include:servers.mcsv.net -all"
```

`-all` (hard fail) once the list is complete. Keep the total DNS lookups under 10 (check with an SPF validator).

## 2. DKIM — signatures on outgoing mail

Enable DKIM in cPanel (Email → Email Deliverability) and in each provider; publish the TXT/CNAME records they give.
Use 2048-bit keys. (Post-quantum DKIM does not exist yet; DKIM protects integrity at delivery time, so harvest-now
attacks do not apply.)

## 3. DMARC — what receivers do with forgeries

Start in monitoring mode for two weeks, then enforce:

```dns
_dmarc.headlinerecorder.com.  TXT  "v=DMARC1; p=none; rua=mailto:dmarc@headlinerecorder.com; fo=1; adkim=s; aspf=s"
; after reports show only legitimate senders passing:
_dmarc.headlinerecorder.com.  TXT  "v=DMARC1; p=reject; pct=100; rua=mailto:dmarc@headlinerecorder.com; fo=1; adkim=s; aspf=s; sp=reject"
```

## 4. MTA-STS + TLS-RPT — encrypted, authenticated delivery to our mailboxes

```dns
_mta-sts.headlinerecorder.com.   TXT  "v=STSv1; id=20261018"
_smtp._tls.headlinerecorder.com. TXT  "v=TLSRPTv1; rua=mailto:tls-reports@headlinerecorder.com"
```

Serve `https://mta-sts.headlinerecorder.com/.well-known/mta-sts.txt`:

```text
version: STSv1
mode: enforce
mx: <your MX host, e.g. mail.headlinerecorder.com>
max_age: 1209600
```

Start with `mode: testing`, switch to `enforce` after a week of clean TLS reports. Bump the `id` whenever the file changes.

## 5. Domains that never send mail

Any parked domain or subdomain that does not send mail:

```dns
<domain>.          TXT  "v=spf1 -all"
_dmarc.<domain>.   TXT  "v=DMARC1; p=reject"
*._domainkey.<domain>. TXT "v=DKIM1; p="
```

## Check

- `dig +short TXT headlinerecorder.com` / `_dmarc.headlinerecorder.com`
- Send a message to a Gmail account → "Show original" → SPF, DKIM and DMARC all `PASS`.
