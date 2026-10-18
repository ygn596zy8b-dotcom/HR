# Security

Post-quantum migration across transport, server and application layers. The aim is that traffic captured today
("harvest now, decrypt later") stays unreadable to a future quantum computer, and that every published article can be
proven unaltered.

## Cryptography by layer

| Layer | What | Algorithms | Where |
|---|---|---|---|
| Transport (readers, sources) | TLS 1.3 only, hybrid PQ key exchange, downgrade-resistant | SecP384r1MLKEM1024 (ML-KEM-1024), X25519MLKEM768; AES-256-GCM | `infra/nginx/tls-pq.conf` |
| Server admin | SSH, PQ key exchange only, keys only (FIDO2 preferred) | mlkem768x25519-sha256, sntrup761x25519; AES-256-GCM | `infra/ssh/99-hardening.conf` |
| DNS | DNSSEC, CAA lock to one CA account, signing-key pin | ECDSA P-256 / Ed25519 | `infra/dns/DNSSEC.md` |
| Browser | HSTS preload, strict CSP, no framing, no referrer leaks, COOP | — | `infra/nginx/security-headers.conf` |
| Published content | Every article and the feed are signed; anyone can verify | **ML-DSA-87** + Ed448 (both required), SHA-512 | `security/pq` |
| Source / op-ed submissions | Encrypted in the sender's browser; the server only stores ciphertext | **ML-KEM-1024** + P-384 hybrid KEM → **AES-256-GCM** | `security/pq` |

Honest limits (as of October 2026):
- Browsers negotiate **X25519MLKEM768**; none offer the ML-KEM-1024 hybrid yet. The server prefers ML-KEM-1024 for clients that do.
- TLS **certificates** stay classical (ECDSA P-384): public CAs do not issue PQ certificates yet. This does not weaken
  harvest-now-decrypt-later protection, which comes from the key exchange.
- OpenSSH's strongest PQ key exchange is ML-KEM-768-based; there is no ML-KEM-1024 option.
- No PQ DNSSEC algorithm exists yet. DNSSEC is integrity-only, so recorded DNS traffic gains nothing from future decryption.

## Signed content: how verification works

Each signature is a small JSON file. Its `protected` header (type, address, SHA-512 of the exact bytes, time, key ID) is
signed by **both** ML-DSA-87 (FIPS 204, category 5) and Ed448 (RFC 8032); both must verify. Changing one byte of the
content, the date, or the address fails verification.

- **Readers:** `/verify/` on the site (works offline in the browser; nothing is uploaded). Article pages link to
  `/verify/?sig=/signed/<slug>.sig.json`. Readers can paste a copy they were sent to see if it was changed.
- **Partners / syndication:** pin the key ID published in DNS (`_pqsig` TXT, DNSSEC-signed), then
  `pq verify <file>.sig.json --keys pqsig-keys.json --pin <kid>` (exit code 0 = verified).
- **Public keys:** `/.well-known/pqsig-keys.json`. Rotation: append the new key. Compromise: set `"revoked": "<date>"`
  on the old key; everything it signed then fails verification and is re-signed with the new key.

## Running the tools (`security/pq`)

```sh
cd security/pq && npm ci --ignore-scripts && npm test

node cli.mjs keygen sign signing          # signing.secret.json (0600, keep OFF the web server) + signing.public.json
node cli.mjs keygen seal submissions      # editors' decryption key + public key for the submission form
node cli.mjs sign feed.xml --key signing.secret.json --type feed --url /feed.xml
node cli.mjs sign article.json --key signing.secret.json --type article --url /2026/10/slug/ --attach
node cli.mjs verify feed.xml.sig.json --file feed.xml --keys signing.public.json
node cli.mjs open submission.sealed.json --key submissions.secret.json
npm run build                             # rebuilds verify/verify.js after changing pq.mjs or verify.src.mjs
```

Article payload (what `--attach` signs): UTF-8 JSON `{ "url", "title", "author", "published", "modified", "html" }`.

Secret keys never go in git (`.gitignore` blocks `*.secret.json`) or the web root (nginx also refuses to serve them).
Best practice is signing in CI or on an editor's machine, not on the public web server.

## Reporting a vulnerability

Email security@ (address to be set once the domain is confirmed). Please do not open public issues for vulnerabilities.
