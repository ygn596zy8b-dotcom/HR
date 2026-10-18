// Post-quantum integrity for published content and confidentiality for submissions.
// Runs unchanged in Node (>=20.19) and browsers: noble for PQ/EC, WebCrypto for AES-256-GCM.
import { ml_dsa87 } from '@noble/post-quantum/ml-dsa.js';
import { ml_kem1024_p384 } from '@noble/post-quantum/hybrid.js';
import { ed448 } from '@noble/curves/ed448.js';
import { sha256, sha512 } from '@noble/hashes/sha2.js';
import { bytesToHex, randomBytes } from '@noble/hashes/utils.js';

export const SIG_ALG = 'ML-DSA-87+Ed448';
export const SEAL_ALG = 'ML-KEM-1024+P-384/AES-256-GCM';
const CTX = new TextEncoder().encode('pqsig-v1'); // FIPS 204 / RFC 8032 context: domain-separates our signatures
const te = new TextEncoder();
const td = new TextDecoder('utf-8', { fatal: true });

export const b64u = {
  enc(bytes) {
    let s = '';
    for (const b of bytes) s += String.fromCharCode(b);
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  },
  dec(str) {
    if (typeof str !== 'string' || !/^[A-Za-z0-9_-]*$/.test(str)) throw new Error('bad base64url');
    const s = atob(str.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(s, (c) => c.charCodeAt(0));
  },
};
const concat = (a, b) => { const out = new Uint8Array(a.length + b.length); out.set(a); out.set(b, a.length); return out; };
const keyId = (...parts) => bytesToHex(sha256(parts.reduce(concat)));
export const sha512hex = (bytes) => bytesToHex(sha512(bytes));

// ---- Signatures: both ML-DSA-87 and Ed448 must verify (hybrid AND), so a break in either alone is not enough.

export function generateSigningKey() {
  const pq = ml_dsa87.keygen();
  const ec = ed448.keygen();
  const kid = keyId(pq.publicKey, ec.publicKey);
  return {
    publicKey: { alg: SIG_ALG, kid, mldsa87: b64u.enc(pq.publicKey), ed448: b64u.enc(ec.publicKey), created: new Date().toISOString() },
    secretKey: { alg: SIG_ALG, kid, mldsa87: b64u.enc(pq.secretKey), ed448: b64u.enc(ec.secretKey) },
  };
}

/** Sign bytes. Returns a JSON-able signature document; pass attach=true to embed the content. */
export function sign(bytes, secretKey, { type = 'file', url, attach = false } = {}) {
  if (secretKey?.alg !== SIG_ALG) throw new Error('not a signing key');
  const header = { v: 1, alg: SIG_ALG, kid: secretKey.kid, type: String(type), url: url == null ? undefined : String(url), sha512: sha512hex(bytes), signedAt: new Date().toISOString() };
  // The exact header bytes are signed and shipped (JWS-style), so verifiers never re-canonicalise JSON.
  const hdr = te.encode(JSON.stringify(header));
  const doc = {
    protected: b64u.enc(hdr),
    mldsa87: b64u.enc(ml_dsa87.sign(hdr, b64u.dec(secretKey.mldsa87), { context: CTX })),
    ed448: b64u.enc(ed448.sign(hdr, b64u.dec(secretKey.ed448), { context: CTX })),
  };
  if (attach) doc.payload = b64u.enc(bytes);
  return doc;
}

/**
 * Verify a signature document against content bytes (defaults to the attached payload) and a keyset ({keys:[...]}).
 * Never throws on hostile input: returns { ok:false, reason } or { ok:true, header, key, bytes }.
 */
export function verify(doc, bytes, keyset) {
  const fail = (reason, header) => ({ ok: false, reason, header });
  let hdr, header;
  try {
    hdr = b64u.dec(doc.protected);
    header = JSON.parse(td.decode(hdr));
  } catch {
    return fail('Malformed signature file.');
  }
  if (header?.v !== 1 || header.alg !== SIG_ALG) return fail('Unsupported signature version or algorithm.', header);
  const key = (keyset?.keys ?? []).find((k) => k.kid === header.kid);
  if (!key) return fail('Signed by a key that is not in the publisher keyset.', header);
  try {
    const pk = b64u.dec(key.mldsa87), ek = b64u.dec(key.ed448);
    if (keyId(pk, ek) !== key.kid) return fail('Keyset entry does not match its key ID.', header);
    if (key.revoked) return fail(`Signing key was revoked on ${key.revoked}.`, header);
    const pqOk = ml_dsa87.verify(b64u.dec(doc.mldsa87), hdr, pk, { context: CTX });
    const ecOk = ed448.verify(b64u.dec(doc.ed448), hdr, ek, { context: CTX });
    if (!pqOk || !ecOk) return fail('Signature is invalid.', header);
  } catch {
    return fail('Signature is invalid.', header);
  }
  if (bytes == null) {
    if (typeof doc.payload !== 'string') return fail('This signature is detached: provide the content it covers.', header);
    try { bytes = b64u.dec(doc.payload); } catch { return fail('Malformed signature file.', header); }
  }
  if (sha512hex(bytes) !== header.sha512) return fail('Content was altered after it was signed.', header);
  return { ok: true, header, key, bytes };
}

// ---- Sealing: hybrid KEM (IETF hybrid-KEM draft construction) -> 32-byte key -> AES-256-GCM.

export function generateSealingKey() {
  const { publicKey, secretKey } = ml_kem1024_p384.keygen();
  const kid = keyId(publicKey);
  return {
    publicKey: { alg: SEAL_ALG, kid, key: b64u.enc(publicKey) },
    secretKey: { alg: SEAL_ALG, kid, key: b64u.enc(secretKey) },
  };
}

async function aesKey(secret, usage) {
  if (secret.length !== 32) throw new Error('KEM secret is not 256 bits'); // importKey would silently pick AES-128 for 16 bytes
  return crypto.subtle.importKey('raw', secret, { name: 'AES-GCM' }, false, [usage]);
}

export async function seal(bytes, publicKey) {
  if (publicKey?.alg !== SEAL_ALG) throw new Error('not a sealing key');
  const { cipherText, sharedSecret } = ml_kem1024_p384.encapsulate(b64u.dec(publicKey.key));
  const hdr = te.encode(JSON.stringify({ v: 1, alg: SEAL_ALG, kid: publicKey.kid }));
  const iv = randomBytes(12);
  const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: concat(hdr, cipherText) }, await aesKey(sharedSecret, 'encrypt'), bytes);
  return { protected: b64u.enc(hdr), kem: b64u.enc(cipherText), iv: b64u.enc(iv), ct: b64u.enc(new Uint8Array(ct)) };
}

/** Decrypt a sealed envelope. Throws if it was tampered with or sealed to another key. */
export async function open(env, secretKey) {
  const hdr = b64u.dec(env.protected);
  const header = JSON.parse(td.decode(hdr));
  if (header.v !== 1 || header.alg !== SEAL_ALG) throw new Error('unsupported envelope');
  if (header.kid !== secretKey.kid) throw new Error('sealed to a different key');
  const kem = b64u.dec(env.kem);
  const secret = ml_kem1024_p384.decapsulate(kem, b64u.dec(secretKey.key));
  const pt = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: b64u.dec(env.iv), additionalData: concat(hdr, kem) }, await aesKey(secret, 'decrypt'), b64u.dec(env.ct));
  return new Uint8Array(pt);
}
