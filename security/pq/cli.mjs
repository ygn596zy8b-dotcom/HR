#!/usr/bin/env node
import { readFileSync, writeFileSync } from 'node:fs';
import { parseArgs } from 'node:util';
import { generateSigningKey, generateSealingKey, sign, verify, seal, open } from './pq.mjs';

const USAGE = `usage:
  pq keygen sign|seal <prefix>          writes <prefix>.secret.json (mode 0600) and <prefix>.public.json
  pq sign <file> --key K [--type article|feed|file] [--url U] [--attach]   writes <file>.sig.json
  pq verify <file.sig.json> --keys keyset.json [--file F] [--pin KID]
  pq seal <file> --key K.public.json    writes <file>.sealed.json
  pq open <file.sealed.json> --key K.secret.json   plaintext to stdout`;

const { values: o, positionals: [cmd, a, b] } = parseArgs({
  allowPositionals: true,
  options: { key: { type: 'string' }, keys: { type: 'string' }, file: { type: 'string' }, type: { type: 'string' }, url: { type: 'string' }, pin: { type: 'string' }, attach: { type: 'boolean' } },
});
const json = (p) => JSON.parse(readFileSync(p, 'utf8'));
const need = (v, name) => v ?? (console.error(`missing ${name}\n${USAGE}`), process.exit(2));
// Keys are never overwritten (flag wx); signatures are, since feeds are re-signed on every publish.
const write = (p, obj, { mode = 0o644, flag = 'w' } = {}) => { writeFileSync(p, JSON.stringify(obj, null, 2) + '\n', { mode, flag }); console.error(`wrote ${p}`); };

switch (cmd) {
  case 'keygen': {
    const gen = { sign: generateSigningKey, seal: generateSealingKey }[a] ?? need(undefined, 'sign|seal');
    const { publicKey, secretKey } = gen();
    const prefix = need(b, '<prefix>');
    write(`${prefix}.secret.json`, secretKey, { mode: 0o600, flag: 'wx' });
    write(`${prefix}.public.json`, a === 'sign' ? { keys: [publicKey] } : publicKey, { flag: 'wx' });
    console.log(`key id: ${publicKey.kid}`);
    break;
  }
  case 'sign':
    write(`${need(a, '<file>')}.sig.json`, sign(readFileSync(a), json(need(o.key, '--key')), { type: o.type, url: o.url, attach: o.attach }));
    break;
  case 'verify': {
    const r = verify(json(need(a, '<sig file>')), o.file ? readFileSync(o.file) : undefined, json(need(o.keys, '--keys')));
    if (r.ok && o.pin && r.key.kid !== o.pin) Object.assign(r, { ok: false, reason: 'Signed by a key other than the pinned one.' });
    if (!r.ok) { console.error(`NOT VERIFIED: ${r.reason}`); process.exit(1); }
    const { type, url, signedAt, kid, sha512 } = r.header;
    console.log(`VERIFIED ${type}${url ? ` ${url}` : ''}\n  signed  ${signedAt}\n  key     ${kid}\n  sha512  ${sha512}`);
    break;
  }
  case 'seal':
    write(`${need(a, '<file>')}.sealed.json`, await seal(readFileSync(a), json(need(o.key, '--key'))));
    break;
  case 'open':
    process.stdout.write(await open(json(need(a, '<sealed file>')), json(need(o.key, '--key'))));
    break;
  default:
    console.error(USAGE);
    process.exit(cmd ? 2 : 0);
}
