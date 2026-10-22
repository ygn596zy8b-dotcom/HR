// Interop tests (MSBJ): the pure-PHP signer in wp-content/plugins/hr-platform/includes/signing/pq/ against noble.
// Run: cd security/pq && node --test php-interop.test.mjs   (needs a 64-bit `php` on PATH, or PHP_BIN=/path/to/php)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { randomBytes as nodeRandom } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { ml_dsa87 } from '@noble/post-quantum/ml-dsa.js';
import { ed448 } from '@noble/curves/ed448.js';
import { shake128, shake256 } from '@noble/hashes/sha3.js';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const HARNESS = path.resolve(HERE, '../../wp-content/plugins/hr-platform/tests/pq/harness.php');
const PHP = process.env.PHP_BIN || 'php';
const havePhp = spawnSync(PHP, ['-r', 'echo PHP_INT_SIZE;'], { encoding: 'utf8' }).stdout === '8';
const skip = havePhp ? false : '64-bit php not found on PATH (set PHP_BIN)';

const te = new TextEncoder();
const CTX = te.encode('pqsig-v1');
const N = 30; // random cases per property
const hex = (u8) => Buffer.from(u8).toString('hex');
const unhex = (s) => new Uint8Array(Buffer.from(s, 'hex'));
const rnd = (n) => new Uint8Array(nodeRandom(n));
const randInt = (n) => nodeRandom(4).readUInt32LE(0) % n;
const flipBit = (u8, bit = randInt(u8.length * 8)) => { const c = u8.slice(); c[bit >> 3] ^= 1 << (bit & 7); return c; };
const concat = (...a) => { const r = new Uint8Array(a.reduce((s, x) => s + x.length, 0)); let o = 0; for (const x of a) { r.set(x, o); o += x.length; } return r; };
// noble signals rejection either by returning false or by throwing (wrong lengths, bad encodings).
const rejects = (fn) => { try { return fn() === false; } catch { return true; } };

/** Runs a batch of ops in one PHP process; asserts that PHP raised no warnings or notices. */
function php(ops) {
  const r = spawnSync(PHP, [HARNESS], { input: JSON.stringify({ ops }), encoding: 'utf8', maxBuffer: 512 << 20 });
  assert.equal(r.status, 0, `php exited ${r.status}: ${r.stderr}`);
  assert.equal(r.stderr, '', `php wrote to stderr: ${r.stderr}`);
  const out = JSON.parse(r.stdout);
  assert.equal(out.length, ops.length);
  out.forEach((o, i) => assert.deepEqual(o.notices, [], `PHP notices during ${ops[i].op} #${i}`));
  return out;
}

test('PHP side: 64-bit build, supported() and self_test()', { skip }, () => {
  const [info, sup, st] = php([{ op: 'info' }, { op: 'supported' }, { op: 'self_test' }]);
  assert.equal(info.int, 8);
  assert.equal(info.context, 'pqsig-v1');
  assert.equal(sup.ok, true);
  assert.equal(st.ok, true);
});

test('SHAKE128 / SHAKE256 match @noble/hashes for inputs and outputs spanning several blocks', { skip }, () => {
  const edges = [0, 1, 135, 136, 137, 167, 168, 169, 271, 272, 273, 335, 336, 337, 504, 505];
  const cases = [];
  for (let i = 0; i < Math.max(N, edges.length); i++) {
    const inLen = i < edges.length ? edges[i] : randInt(900);
    const outLen = i < edges.length ? edges[(i * 7 + 3) % edges.length] : randInt(1400);
    cases.push({ input: rnd(inLen), len: outLen });
  }
  const ops = cases.flatMap((c) => [
    { op: 'shake128', in: hex(c.input), len: c.len },
    { op: 'shake256', in: hex(c.input), len: c.len },
  ]);
  const out = php(ops);
  cases.forEach((c, i) => {
    assert.equal(out[2 * i].out, hex(shake128(c.input, { dkLen: c.len })), `shake128 in=${c.input.length} out=${c.len}`);
    assert.equal(out[2 * i + 1].out, hex(shake256(c.input, { dkLen: c.len })), `shake256 in=${c.input.length} out=${c.len}`);
  });
});

// Shared ML-DSA-87 fixtures: N seeds, the noble key pairs, random messages and contexts (including the edges).
const mlSeeds = Array.from({ length: N }, () => rnd(32));
const mlKeys = mlSeeds.map((s) => ml_dsa87.keygen(s));
const mlMsgs = Array.from({ length: N }, (_, i) => (i === 0 ? new Uint8Array() : rnd(randInt(1500))));
const mlCtxs = Array.from({ length: N }, (_, i) => (i === 0 ? rnd(255) : i === 1 ? new Uint8Array() : i === 2 ? CTX : rnd(randInt(256))));

test(`ML-DSA-87 keygen(seed) equals noble byte for byte (${N} seeds)`, { skip }, () => {
  const out = php(mlSeeds.map((s) => ({ op: 'mldsa_keygen', seed: hex(s) })));
  out.forEach((o, i) => {
    assert.equal(o.pk.length / 2, 2592);
    assert.equal(o.sk.length / 2, 4896);
    assert.equal(o.pk, hex(mlKeys[i].publicKey), `pk for seed ${hex(mlSeeds[i])}`);
    assert.equal(o.sk, hex(mlKeys[i].secretKey), `sk for seed ${hex(mlSeeds[i])}`);
  });
});

test(`ML-DSA-87 deterministic and explicit-rnd signatures equal noble byte for byte (${N} + 10 cases)`, { skip }, () => {
  const zero = new Uint8Array(32);
  const rnds = Array.from({ length: 10 }, () => rnd(32));
  const ops = [
    ...mlKeys.map((k, i) => ({ op: 'mldsa_sign', sk: hex(k.secretKey), msg: hex(mlMsgs[i]), ctx: hex(mlCtxs[i]), rnd: hex(zero) })),
    ...rnds.map((r, i) => ({ op: 'mldsa_sign', sk: hex(mlKeys[i].secretKey), msg: hex(mlMsgs[i]), ctx: hex(mlCtxs[i]), rnd: hex(r) })),
  ];
  const out = php(ops);
  mlKeys.forEach((k, i) => {
    const want = ml_dsa87.sign(mlMsgs[i], k.secretKey, { context: mlCtxs[i], extraEntropy: false });
    assert.equal(out[i].sig.length / 2, 4627);
    assert.equal(out[i].sig, hex(want), `deterministic sig #${i} (msg ${mlMsgs[i].length} B, ctx ${mlCtxs[i].length} B)`);
  });
  rnds.forEach((r, i) => {
    const want = ml_dsa87.sign(mlMsgs[i], mlKeys[i].secretKey, { context: mlCtxs[i], extraEntropy: r });
    assert.equal(out[N + i].sig, hex(want), `explicit-rnd sig #${i}`);
  });
});

test(`ML-DSA-87 hedged PHP signatures verify in noble, noble signatures verify in PHP (${N} each)`, { skip }, () => {
  const signs = php(mlKeys.flatMap((k, i) => [
    { op: 'mldsa_sign', sk: hex(k.secretKey), msg: hex(mlMsgs[i]), ctx: hex(mlCtxs[i]) },
    { op: 'mldsa_sign', sk: hex(k.secretKey), msg: hex(mlMsgs[i]), ctx: hex(mlCtxs[i]) },
  ]));
  const nobleSigs = mlKeys.map((k, i) => ml_dsa87.sign(mlMsgs[i], k.secretKey, { context: mlCtxs[i] }));
  mlKeys.forEach((k, i) => {
    const a = unhex(signs[2 * i].sig), b = unhex(signs[2 * i + 1].sig);
    assert.notEqual(hex(a), hex(b), 'two hedged signatures of the same message differ');
    assert.equal(ml_dsa87.verify(a, mlMsgs[i], k.publicKey, { context: mlCtxs[i] }), true, `noble verifies PHP sig #${i}`);
    assert.equal(ml_dsa87.verify(b, mlMsgs[i], k.publicKey, { context: mlCtxs[i] }), true);
  });
  const ver = php(mlKeys.map((k, i) => ({ op: 'mldsa_verify', pk: hex(k.publicKey), msg: hex(mlMsgs[i]), sig: hex(nobleSigs[i]), ctx: hex(mlCtxs[i]) })));
  ver.forEach((o, i) => assert.equal(o.ok, true, `PHP verifies noble sig #${i}`));
});

test(`ML-DSA-87 tampered signature, message, context and key fail in both (${N} cases x 5)`, { skip }, () => {
  const cases = [];
  mlKeys.forEach((k, i) => {
    const sig = ml_dsa87.sign(mlMsgs[i], k.secretKey, { context: mlCtxs[i] });
    const other = mlKeys[(i + 1) % N].publicKey;
    const ctx2 = mlCtxs[i].length ? flipBit(mlCtxs[i]) : new Uint8Array([0]);
    const msg2 = mlMsgs[i].length ? flipBit(mlMsgs[i]) : new Uint8Array([0]);
    cases.push(
      { what: 'sig bit', pk: k.publicKey, msg: mlMsgs[i], sig: flipBit(sig), ctx: mlCtxs[i] },
      { what: 'message', pk: k.publicKey, msg: msg2, sig, ctx: mlCtxs[i] },
      { what: 'context', pk: k.publicKey, msg: mlMsgs[i], sig, ctx: ctx2 },
      { what: 'other key', pk: other, msg: mlMsgs[i], sig, ctx: mlCtxs[i] },
      { what: 'pk bit', pk: flipBit(k.publicKey), msg: mlMsgs[i], sig, ctx: mlCtxs[i] },
    );
  });
  const out = php(cases.map((c) => ({ op: 'mldsa_verify', pk: hex(c.pk), msg: hex(c.msg), sig: hex(c.sig), ctx: hex(c.ctx) })));
  cases.forEach((c, i) => {
    assert.equal(out[i].ok, false, `PHP rejects tampered ${c.what}`);
    assert.equal(rejects(() => ml_dsa87.verify(c.sig, c.msg, c.pk, { context: c.ctx })), true, `noble rejects tampered ${c.what}`);
  });
});

test('ML-DSA-87 malformed signatures and keys are rejected without notices', { skip }, () => {
  const k = mlKeys[3], msg = mlMsgs[3], ctx = mlCtxs[3];
  const sig = ml_dsa87.sign(msg, k.secretKey, { context: ctx, extraEntropy: false });
  const H = 64 + 7 * 640; // hint area: 75 index bytes then 8 running counts
  const withByte = (pos, val) => { const c = sig.slice(); c[pos] = val; return c; };
  const counts = Array.from(sig.slice(H + 75));
  const used = counts[7];
  const cases = [
    ['sig too short', k.publicKey, sig.slice(0, 4626)],
    ['sig too long', k.publicKey, concat(sig, new Uint8Array(1))],
    ['empty sig', k.publicKey, new Uint8Array()],
    ['pk too short', k.publicKey.slice(0, 2591), sig],
    ['pk too long', concat(k.publicKey, new Uint8Array(1)), sig],
    ['hint count above omega', k.publicKey, withByte(H + 75 + 7, 76)],
    ['hint counts decreasing', k.publicKey, withByte(H + 75, counts[1] + 1)],
    ['z out of range', k.publicKey, (() => { const c = sig.slice(); c[64] = 120; c[65] = 0; c[66] &= 0xf0; return c; })()],
  ];
  if (used < 75) cases.push(['non-zero unused hint slot', k.publicKey, withByte(H + 74, 1)]);
  const firstPoly = counts.findIndex((n, i) => n - (i ? counts[i - 1] : 0) >= 2);
  if (firstPoly >= 0) {
    const start = firstPoly ? counts[firstPoly - 1] : 0;
    const c = sig.slice(); [c[H + start], c[H + start + 1]] = [c[H + start + 1], c[H + start]];
    cases.push(['hint indices not increasing', k.publicKey, c]);
  }
  const out = php(cases.map(([, pk, s]) => ({ op: 'mldsa_verify', pk: hex(pk), msg: hex(msg), sig: hex(s), ctx: hex(ctx) })));
  cases.forEach(([what, pk, s], i) => {
    assert.equal(out[i].ok, false, `PHP rejects: ${what}`);
    assert.equal(rejects(() => ml_dsa87.verify(s, msg, pk, { context: ctx })), true, `noble rejects: ${what}`);
  });
});

test('ML-DSA-87 empty message and 255-byte context work; a 256-byte context is refused', { skip }, () => {
  const k = mlKeys[0], c255 = rnd(255), c256 = rnd(256), empty = new Uint8Array();
  const nobleSig = ml_dsa87.sign(empty, k.secretKey, { context: c255 });
  const [det, hedged, v1, signBig, verBig] = php([
    { op: 'mldsa_sign', sk: hex(k.secretKey), msg: '', ctx: hex(c255), rnd: hex(new Uint8Array(32)) },
    { op: 'mldsa_sign', sk: hex(k.secretKey), msg: '', ctx: hex(c255) },
    { op: 'mldsa_verify', pk: hex(k.publicKey), msg: '', sig: hex(nobleSig), ctx: hex(c255) },
    { op: 'mldsa_sign', sk: hex(k.secretKey), msg: '', ctx: hex(c256) },
    { op: 'mldsa_verify', pk: hex(k.publicKey), msg: '', sig: hex(nobleSig), ctx: hex(c256) },
  ]);
  assert.equal(det.sig, hex(ml_dsa87.sign(empty, k.secretKey, { context: c255, extraEntropy: false })));
  assert.equal(ml_dsa87.verify(unhex(hedged.sig), empty, k.publicKey, { context: c255 }), true);
  assert.equal(v1.ok, true);
  assert.equal(signBig.error, 'InvalidArgumentException');
  assert.equal(verBig.ok, false);
  assert.throws(() => ml_dsa87.sign(empty, k.secretKey, { context: c256 }));
});

test('ML-DSA-87 key_matches and wrong input lengths', { skip }, () => {
  const [a, b] = mlKeys;
  const badTr = a.secretKey.slice(); badTr[70] ^= 1;
  const badRho = a.secretKey.slice(); badRho[0] ^= 1;
  const badEta = a.secretKey.slice(); badEta[128] = 0xff; // first s1 coefficients become 7 > 2 * eta
  const out = php([
    { op: 'mldsa_key_matches', sk: hex(a.secretKey), pk: hex(a.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(a.secretKey), pk: hex(b.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(badTr), pk: hex(a.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(badRho), pk: hex(a.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(badEta), pk: hex(a.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(a.secretKey.slice(1)), pk: hex(a.publicKey) },
    { op: 'mldsa_key_matches', sk: hex(a.secretKey), pk: hex(a.publicKey.slice(1)) },
    { op: 'mldsa_keygen', seed: hex(rnd(31)) },
    { op: 'mldsa_sign', sk: hex(a.secretKey.slice(1)), msg: '00', ctx: '' },
    { op: 'mldsa_sign', sk: hex(a.secretKey), msg: '00', ctx: '', rnd: hex(rnd(31)) },
    { op: 'mldsa_sign', sk: hex(badEta), msg: '00', ctx: '' },
    { op: 'mldsa_keygen' },
  ]);
  assert.deepEqual(out.slice(0, 7).map((o) => o.ok), [true, false, false, false, false, false, false]);
  for (const o of out.slice(7, 11)) assert.equal(o.error, 'InvalidArgumentException', JSON.stringify(o));
  assert.throws(() => ml_dsa87.sign(new Uint8Array([0]), badEta, {}), 'noble also refuses the out-of-range key');
  // A random PHP key pair works with noble.
  const fresh = out[11], m = rnd(100);
  assert.equal(ml_dsa87.verify(ml_dsa87.sign(m, unhex(fresh.sk), { context: CTX }), m, unhex(fresh.pk), { context: CTX }), true);
});

// ---------------------------------------------------------------------------------------------- Ed448

const RFC8032 = [ // RFC 8032 section 7.4: [secret, public, message, context, signature]
  ['6c82a562cb808d10d632be89c8513ebf6c929f34ddfa8c9f63c9960ef6e348a3528c8a3fcc2f044e39a3fc5b94492f8f032e7549a20098f95b', '5fd7449b59b461fd2ce787ec616ad46a1da1342485a70e1f8a0ea75d80e96778edf124769b46c7061bd6783df1e50f6cd1fa1abeafe8256180', '', '', '533a37f6bbe457251f023c0d88f976ae2dfb504a843e34d2074fd823d41a591f2b233f034f628281f2fd7a22ddd47d7828c59bd0a21bfd3980ff0d2028d4b18a9df63e006c5d1c2d345b925d8dc00b4104852db99ac5c7cdda8530a113a0f4dbb61149f05a7363268c71d95808ff2e652600'],
  ['c4eab05d357007c632f3dbb48489924d552b08fe0c353a0d4a1f00acda2c463afbea67c5e8d2877c5e3bc397a659949ef8021e954e0a12274e', '43ba28f430cdff456ae531545f7ecd0ac834a55d9358c0372bfa0c6c6798c0866aea01eb00742802b8438ea4cb82169c235160627b4c3a9480', '03', '', '26b8f91727bd62897af15e41eb43c377efb9c610d48f2335cb0bd0087810f4352541b143c4b981b7e18f62de8ccdf633fc1bf037ab7cd779805e0dbcc0aae1cbcee1afb2e027df36bc04dcecbf154336c19f0af7e0a6472905e799f1953d2a0ff3348ab21aa4adafd1d234441cf807c03a00'],
  ['c4eab05d357007c632f3dbb48489924d552b08fe0c353a0d4a1f00acda2c463afbea67c5e8d2877c5e3bc397a659949ef8021e954e0a12274e', '43ba28f430cdff456ae531545f7ecd0ac834a55d9358c0372bfa0c6c6798c0866aea01eb00742802b8438ea4cb82169c235160627b4c3a9480', '03', '666f6f', 'd4f8f6131770dd46f40867d6fd5d5055de43541f8c5e35abbcd001b32a89f7d2151f7647f11d8ca2ae279fb842d607217fce6e042f6815ea000c85741de5c8da1144a6a1aba7f96de42505d7a7298524fda538fccbbb754f578c1cad10d54d0d5428407e85dcbc98a49155c13764e66c3c00'],
  ['cd23d24f714274e744343237b93290f511f6425f98e64459ff203e8985083ffdf60500553abc0e05cd02184bdb89c4ccd67e187951267eb328', 'dcea9e78f35a1bf3499a831b10b86c90aac01cd84b67a0109b55a36e9328b1e365fce161d71ce7131a543ea4cb5f7e9f1d8b00696447001400', '0c3e544074ec63b0265e0c', '', '1f0a8888ce25e8d458a21130879b840a9089d999aaba039eaf3e3afa090a09d389dba82c4ff2ae8ac5cdfb7c55e94d5d961a29fe0109941e00b8dbdeea6d3b051068df7254c0cdc129cbe62db2dc957dbb47b51fd3f213fb8698f064774250a5028961c9bf8ffd973fe5d5c206492b140e00'],
];

test('Ed448 RFC 8032 section 7.4 vectors (including the context "foo" vector)', { skip }, () => {
  const out = php(RFC8032.flatMap(([sk, pk, msg, ctx, sig]) => [
    { op: 'ed_pub', sk },
    { op: 'ed_sign', sk, msg, ctx },
    { op: 'ed_verify', pk, msg, sig, ctx },
    { op: 'ed_verify', pk, msg, sig, ctx: ctx ? '' : '666f6f' },
  ]));
  RFC8032.forEach(([, pk, , , sig], i) => {
    assert.equal(out[4 * i].pk, pk, `RFC vector ${i} public key`);
    assert.equal(out[4 * i + 1].sig, sig, `RFC vector ${i} signature`);
    assert.equal(out[4 * i + 2].ok, true, `RFC vector ${i} verifies`);
    assert.equal(out[4 * i + 3].ok, false, `RFC vector ${i} fails under another context`);
  });
});

test(`Ed448 public keys and signatures equal noble byte for byte (${N} random keys)`, { skip }, () => {
  const cases = Array.from({ length: N }, (_, i) => ({
    sk: rnd(57),
    msg: i === 0 ? new Uint8Array() : rnd(randInt(1500)),
    ctx: i === 1 ? rnd(255) : i === 2 ? new Uint8Array() : i === 3 ? CTX : rnd(randInt(256)),
  }));
  const out = php(cases.flatMap((c) => [{ op: 'ed_pub', sk: hex(c.sk) }, { op: 'ed_sign', sk: hex(c.sk), msg: hex(c.msg), ctx: hex(c.ctx) }]));
  const ver = [];
  cases.forEach((c, i) => {
    const pk = ed448.getPublicKey(c.sk);
    const sig = ed448.sign(c.msg, c.sk, { context: c.ctx });
    assert.equal(out[2 * i].pk, hex(pk), `public key #${i}`);
    assert.equal(out[2 * i + 1].sig, hex(sig), `signature #${i}`);
    assert.equal(ed448.verify(unhex(out[2 * i + 1].sig), c.msg, pk, { context: c.ctx }), true);
    ver.push(
      { op: 'ed_verify', pk: hex(pk), msg: hex(c.msg), sig: hex(sig), ctx: hex(c.ctx), want: true },
      { op: 'ed_verify', pk: hex(pk), msg: hex(c.msg), sig: hex(flipBit(sig)), ctx: hex(c.ctx), want: false },
      { op: 'ed_verify', pk: hex(pk), msg: hex(concat(c.msg, new Uint8Array([1]))), sig: hex(sig), ctx: hex(c.ctx), want: false },
      { op: 'ed_verify', pk: hex(pk), msg: hex(c.msg), sig: hex(sig), ctx: hex(c.ctx.length ? flipBit(c.ctx) : new Uint8Array([7])), want: false },
      { op: 'ed_verify', pk: hex(ed448.getPublicKey(rnd(57))), msg: hex(c.msg), sig: hex(sig), ctx: hex(c.ctx), want: false },
    );
  });
  const vo = php(ver.map(({ want, ...op }) => op));
  ver.forEach((v, i) => {
    assert.equal(vo[i].ok, v.want, `PHP verify case ${i}`);
    assert.equal(!rejects(() => ed448.verify(unhex(v.sig), unhex(v.msg), unhex(v.pk), { context: unhex(v.ctx) })), v.want, `noble verify case ${i}`);
  });
});

test('Ed448 malformed inputs are rejected exactly like noble (lengths, non-canonical points, S >= L)', { skip }, () => {
  const P = 2n ** 448n - 2n ** 224n - 1n;
  const L = BigInt('0x3fffffffffffffffffffffffffffffffffffffffffffffffffffffff7cca23e9c44edb49aed63690216cc2728dc58f552378c292ab5844f3');
  const le = (n, len) => { const r = new Uint8Array(len); for (let i = 0; i < len; i++) { r[i] = Number(n & 0xffn); n >>= 8n; } return r; };
  const toN = (u8) => u8.reduceRight((acc, b) => (acc << 8n) | BigInt(b), 0n);
  const pt = (y, sign = 0) => { const b = le(y, 57); b[56] |= sign ? 0x80 : 0; return b; };
  const sk = rnd(57), msg = rnd(80), ctx = CTX;
  const pk = ed448.getPublicKey(sk), sig = ed448.sign(msg, sk, { context: ctx });
  const R = sig.slice(0, 57), S = toN(sig.slice(57));
  let offCurve; // a y for which (y^2 - 1) / (d y^2 - 1) has no square root
  for (let y = 2n; !offCurve; y++) { try { ed448.Point.fromBytes(pt(y)); } catch { offCurve = y; } }
  const withR = (r) => concat(r, sig.slice(57));
  const withS = (s) => concat(R, le(s, 57));
  const cases = [
    ['pk 56 bytes', pk.slice(0, 56), sig], ['pk 58 bytes', concat(pk, new Uint8Array(1)), sig], ['pk empty', new Uint8Array(), sig],
    ['sig 113 bytes', pk, sig.slice(0, 113)], ['sig 115 bytes', pk, concat(sig, new Uint8Array(1))], ['sig empty', pk, new Uint8Array()],
    ['pk unused bit set', pk, sig, (() => { const c = pk.slice(); c[56] |= 0x01; return c; })()],
    ['pk y = p', pt(P), sig], ['pk y = p + 1', pt(P + 1n), sig], ['pk y = 2^448 - 1', pt(2n ** 448n - 1n), sig],
    ['pk identity (small order)', pt(1n), sig], ['pk x = 0 with sign bit', pt(1n, 1), sig], ['pk (0, -1) (small order)', pt(P - 1n), sig],
    ['pk (0, -1) with sign bit', pt(P - 1n, 1), sig], ['pk not on curve', pt(offCurve), sig],
    ['R y = p + 1', pk, withR(pt(P + 1n))], ['R unused bit set', pk, withR((() => { const c = R.slice(); c[56] |= 0x40; return c; })())],
    ['R x = 0 with sign bit', pk, withR(pt(1n, 1))], ['R not on curve', pk, withR(pt(offCurve))],
    ['S = L', pk, withS(L)], ['S + L (malleated)', pk, withS(S + L)], ['S = 2^448 - 1', pk, withS(2n ** 448n - 1n)],
    ['S top byte set', pk, concat(R, (() => { const c = sig.slice(57); c[56] = 1; return c; })())],
  ].map(([what, p, s, p2]) => [what, p2 || p, s]);
  for (let i = 0; i < N; i++) cases.push([`random garbage ${i}`, rnd(57), rnd(114)]);
  const out = php(cases.map(([, p, s]) => ({ op: 'ed_verify', pk: hex(p), msg: hex(msg), sig: hex(s), ctx: hex(ctx) })));
  cases.forEach(([what, p, s], i) => {
    assert.equal(out[i].ok, false, `PHP rejects: ${what}`);
    assert.equal(rejects(() => ed448.verify(s, msg, p, { context: ctx })), true, `noble rejects: ${what}`);
  });
  const err = php([
    { op: 'ed_pub', sk: hex(rnd(56)) }, { op: 'ed_sign', sk: hex(rnd(58)), msg: '', ctx: '' },
    { op: 'ed_sign', sk: hex(sk), msg: '', ctx: hex(rnd(256)) },
    { op: 'ed_verify', pk: hex(pk), msg: hex(msg), sig: hex(sig), ctx: hex(rnd(256)) },
    { op: 'ed_verify', pk: hex(pk), msg: hex(msg), sig: hex(sig), ctx: hex(ctx) },
    { op: 'ed_keygen' },
  ]);
  for (const o of err.slice(0, 3)) assert.equal(o.error, 'InvalidArgumentException', JSON.stringify(o));
  assert.equal(err[3].ok, false);
  assert.equal(err[4].ok, true, 'the untouched signature still verifies');
  assert.equal(hex(ed448.getPublicKey(unhex(err[5].sk))), err[5].pk, 'PHP keygen pair is consistent');
});

test('Ed448 field (mod p) and scalar (mod L) arithmetic equal BigInt, including extreme values', { skip }, () => {
  const P = 2n ** 448n - 2n ** 224n - 1n;
  const L = BigInt('0x3fffffffffffffffffffffffffffffffffffffffffffffffffffffff7cca23e9c44edb49aed63690216cc2728dc58f552378c292ab5844f3');
  const le = (n, len) => { const r = new Uint8Array(len); for (let i = 0; i < len; i++) { r[i] = Number(n & 0xffn); n >>= 8n; } return r; };
  const big = (u8) => u8.reduceRight((acc, b) => (acc << 8n) | BigInt(b), 0n);
  const md = (a, m) => ((a % m) + m) % m;
  const pow = (b, e, m) => { let r = 1n; b = md(b, m); for (; e > 0n; e >>= 1n, b = b * b % m) if (e & 1n) r = r * b % m; return r; };
  // Inputs below 2^448 (the decoder never feeds more); the extremes have every limb at its maximum.
  const edges = [0n, 1n, 2n, P - 1n, P - 2n, P, P + 1n, 2n ** 448n - 1n, 2n ** 224n, 2n ** 224n - 1n, 2n ** 447n, (2n ** 448n - 1n) / 3n];
  const vals = [...edges, ...Array.from({ length: N }, () => big(rnd(56)))];
  const ops = [], want = [];
  for (let i = 0; i < vals.length; i++) {
    const a = vals[i], b = vals[(i * 5 + 1) % vals.length];
    const A = hex(le(a, 56)), B = hex(le(b, 56));
    ops.push({ op: 'ed_fe', fn: 'mul', a: A, b: B }, { op: 'ed_fe', fn: 'sqr', a: A }, { op: 'ed_fe', fn: 'add', a: A, b: B },
      { op: 'ed_fe', fn: 'sub', a: A, b: B }, { op: 'ed_fe', fn: 'chain', a: A, b: B });
    want.push(md(a * b, P), md(a * a, P), md(a + b, P), md(a - b, P), md(md((a * b + a) * (a - b), P) ** 2n, P));
    if (md(a, P) !== 0n) { ops.push({ op: 'ed_fe', fn: 'inv', a: A }); want.push(pow(a, P - 2n, P)); }
  }
  const scEdges = [0n, 1n, L - 1n, L, L + 1n, 2n * L - 1n, 2n * L, 2n ** 446n, 2n ** 446n - 1n, 2n ** 912n - 1n, (2n ** 912n - 1n) / L * L, 2n ** 456n - 1n];
  const scVals = [...scEdges, ...Array.from({ length: N }, () => big(rnd(114)))];
  scVals.forEach((x, i) => {
    const y = scVals[(i + 3) % scVals.length], z = scVals[(i + 7) % scVals.length];
    ops.push({ op: 'ed_sc', fn: 'reduce', a: hex(le(x, 114)) });
    want.push(x % L);
    ops.push({ op: 'ed_sc', fn: 'muladd', a: hex(le(x, 114)), b: hex(le(y, 114)), c: hex(le(z, 114)) });
    want.push(((x % L) * (y % L) + (z % L)) % L);
  });
  const out = php(ops);
  ops.forEach((o, i) => {
    const len = o.op === 'ed_fe' ? 56 : 57;
    assert.equal(out[i].out, hex(le(want[i], len)), `${o.op} ${o.fn} #${i}`);
  });
});

test('timings on this machine (median of 10 runs) meet the targets', { skip }, (t) => {
  const r = spawnSync(PHP, [HARNESS, '--bench', '10'], { encoding: 'utf8' });
  assert.equal(r.status, 0, r.stderr);
  const b = JSON.parse(r.stdout);
  t.diagnostic(`PHP ${b.php}, opcache.enable_cli=${b.opcache_cli}; median ms: ML-DSA-87 keygen ${b.kg.median}, sign ${b.sign.median}, verify ${b.verify.median}; Ed448 keygen ${b.edkg.median}, sign ${b.edsign.median}, verify ${b.edverify.median}`);
  assert.ok(b.sign.median < 1500, 'ML-DSA-87 sign < 1.5 s');
  assert.ok(b.verify.median < 600, 'ML-DSA-87 verify < 0.6 s');
  assert.ok(b.edsign.median < 500, 'Ed448 sign < 0.5 s');
  assert.ok(b.edverify.median < 500, 'Ed448 verify < 0.5 s');
});
