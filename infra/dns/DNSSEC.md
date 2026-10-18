# DNS hardening

## 1. DNSSEC

Turn it on at the DNS host, then add the DS record it gives you at the registrar.

- Algorithm: **13 (ECDSA P-256/SHA-256)** — the most widely validated choice — or **15 (Ed25519)** if the host offers it.
- There is **no post-quantum DNSSEC algorithm** standardised or deployable today. DNSSEC protects *integrity at query time*,
  not confidentiality, so it is not exposed to harvest-now-decrypt-later; migrate when a PQ algorithm is standardised.
- Check: `delv example.com A` should print `; fully validated`, or use https://dnsviz.net.

## 2. Records to publish

```dns
; Only Let's Encrypt may issue, only to our ACME account, only via DNS validation; no wildcards.
example.com.         CAA 0 issue     "letsencrypt.org; accounturi=https://acme-v02.api.letsencrypt.org/acme/acct/<ACCOUNT-ID>; validationmethods=dns-01"
example.com.         CAA 0 issuewild ";"
example.com.         CAA 0 iodef     "mailto:security@example.com"

; Article-signing key ID, so partners can pin it over a DNSSEC-authenticated channel independent of the website.
_pqsig.example.com.  TXT "v=pqsig1; alg=ML-DSA-87+Ed448; kid=<64-hex key id from `pq keygen sign`>"
```

Partners check the pin with a validating resolver: `delv TXT _pqsig.example.com` (must say `fully validated`),
then `pq verify <file>.sig.json --keys pqsig-keys.json --pin <kid>`.

## 3. HSTS preload

After the site has served `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload` for a while with
every subdomain on HTTPS, submit the domain at https://hstspreload.org. Removal takes months, so do this last.
