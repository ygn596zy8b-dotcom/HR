import { test } from 'node:test';
import assert from 'node:assert/strict';
import { generateSigningKey, sign, verify, generateSealingKey, seal, open, b64u } from './pq.mjs';

const te = new TextEncoder();
const signer = generateSigningKey();
const keyset = { keys: [signer.publicKey] };
const feed = te.encode('<rss><channel><title>Feed</title></channel></rss>');

test('detached signature verifies', () => {
  const r = verify(sign(feed, signer.secretKey, { type: 'feed', url: '/feed/' }), feed, keyset);
  assert.equal(r.ok, true, r.reason);
  assert.equal(r.header.type, 'feed');
});

test('attached article verifies and returns the signed bytes', () => {
  const article = te.encode(JSON.stringify({ title: 'Op-ed', html: '<p>Hi</p>' }));
  const r = verify(sign(article, signer.secretKey, { type: 'article', attach: true }), undefined, keyset);
  assert.equal(r.ok, true, r.reason);
  assert.deepEqual(r.bytes, article);
});

test('one changed byte of content is detected', () => {
  const doc = sign(feed, signer.secretKey);
  const altered = feed.slice(); altered[5] ^= 1;
  assert.match(verify(doc, altered, keyset).reason, /altered/);
});

test('editing the signed header (e.g. backdating) breaks the signature', () => {
  const doc = sign(feed, signer.secretKey);
  const h = JSON.parse(new TextDecoder().decode(b64u.dec(doc.protected)));
  h.signedAt = '2000-01-01T00:00:00.000Z';
  assert.match(verify({ ...doc, protected: b64u.enc(te.encode(JSON.stringify(h))) }, feed, keyset).reason, /invalid/);
});

test('both halves of the hybrid are required', () => {
  const a = sign(feed, signer.secretKey), b = sign(te.encode('other'), signer.secretKey);
  assert.equal(verify({ ...a, ed448: b.ed448 }, feed, keyset).ok, false);
  assert.equal(verify({ ...a, mldsa87: b.mldsa87 }, feed, keyset).ok, false);
  assert.equal(verify({ ...a, mldsa87: undefined }, feed, keyset).ok, false);
});

test('unknown, mislabelled and revoked keys are rejected', () => {
  const other = generateSigningKey();
  const doc = sign(feed, other.secretKey);
  assert.match(verify(doc, feed, keyset).reason, /not in the publisher keyset/);
  // An attacker-controlled keyset entry claiming the publisher kid but carrying different keys.
  const forged = { keys: [{ ...other.publicKey, kid: signer.publicKey.kid }] };
  assert.match(verify(sign(feed, signer.secretKey), feed, forged).reason, /does not match/);
  const revoked = { keys: [{ ...signer.publicKey, revoked: '2026-10-01' }] };
  assert.match(verify(sign(feed, signer.secretKey), feed, revoked).reason, /revoked/);
});

test('garbage input never throws', () => {
  for (const junk of [null, {}, { protected: '!!' }, { protected: b64u.enc(te.encode('{')) }, { protected: b64u.enc(te.encode('{"v":1,"alg":"ML-DSA-87+Ed448","kid":"x"}')) }]) {
    assert.equal(verify(junk, feed, keyset).ok, false);
  }
  const detached = sign(feed, signer.secretKey);
  assert.match(verify(detached, undefined, keyset).reason, /detached/);
});

test('seal/open round-trips and rejects tampering or the wrong key', async () => {
  const box = generateSealingKey();
  const msg = te.encode('source: meet at 9');
  const env = await seal(msg, box.publicKey);
  assert.deepEqual(await open(env, box.secretKey), msg);

  const ct = b64u.dec(env.ct); ct[0] ^= 1;
  await assert.rejects(open({ ...env, ct: b64u.enc(ct) }, box.secretKey));
  const kem = b64u.dec(env.kem); kem[0] ^= 1;
  await assert.rejects(open({ ...env, kem: b64u.enc(kem) }, box.secretKey));

  const other = generateSealingKey();
  await assert.rejects(open(env, other.secretKey), /different key/);
  await assert.rejects(open(env, { ...other.secretKey, kid: box.secretKey.kid }));
});
