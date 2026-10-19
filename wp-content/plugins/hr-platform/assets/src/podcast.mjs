// Headline Recorder Podcast: enhances [data-hrp-player] (a native <audio controls> fallback) into the accessible
// player, wires buttons elsewhere on the page to a player, and reads text aloud with the device voice
// ([data-hrp-voice]). Playback only ever starts from a click or key press: no autoplay.

const SPEEDS = [1, 1.25, 1.5, 2, 0.75];
const players = new Map(); // root element -> controller
const voices = new Set();
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Resume positions are a convenience: storage may be blocked (private mode), so every access is guarded.
const saved = {
  get(key) { try { return Number(localStorage.getItem(key)) || 0; } catch { return 0; } },
  set(key, seconds) {
    try { seconds > 5 ? localStorage.setItem(key, String(Math.floor(seconds))) : localStorage.removeItem(key); } catch { /* ignore */ }
  },
};

const clock = (s) => {
  s = Math.max(0, Math.floor(s || 0));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = String(s % 60).padStart(2, '0');
  return h ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`;
};

const spoken = (s) => {
  s = Math.max(0, Math.floor(s || 0));
  const m = Math.floor(s / 60);
  const sec = s % 60;
  const parts = [];
  if (m) parts.push(`${m} minute${m === 1 ? '' : 's'}`);
  if (sec || !m) parts.push(`${sec} second${sec === 1 ? '' : 's'}`);
  return parts.join(' ');
};

function pauseAll(exceptAudio, exceptVoice) {
  for (const p of players.values()) if (p.audio !== exceptAudio) p.audio.pause();
  for (const v of voices) if (v !== exceptVoice) v.pause();
}

function initPlayer(root) {
  const audio = root.querySelector('audio');
  const ui = root.querySelector('.hrp-player__ui');
  if (!audio || !ui) return;
  const q = (s) => root.querySelector(s);
  const seek = q('.hrp-player__seek');
  const elapsed = q('[data-hrp-elapsed]');
  const remaining = q('[data-hrp-remaining]');
  const speed = q('[data-hrp-speed]');
  const mute = q('[data-hrp-mute]');
  let key = `hrp:pos:${root.dataset.key}`;
  let known = Number(root.dataset.duration) || 0;
  let lastSave = 0;
  let rate = 0;

  audio.controls = false;
  audio.hidden = true;
  ui.hidden = false;

  const duration = () => (Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : known);
  const buttons = () => [
    ...root.querySelectorAll('[data-hrp-play]'),
    ...(root.id ? document.querySelectorAll(`[data-hrp-target="${root.id}"]`) : []),
  ];

  function render() {
    const d = duration();
    const t = audio.currentTime || 0;
    seek.max = String(Math.max(1, Math.round(d)));
    seek.value = String(Math.floor(t));
    seek.setAttribute('aria-valuetext', d ? `${spoken(t)} of ${spoken(d)}` : spoken(t));
    elapsed.textContent = clock(t);
    remaining.textContent = d ? `-${clock(d - t)}` : '--:--';
  }

  function setPlaying(on) {
    root.classList.toggle('is-playing', on);
    for (const b of buttons()) {
      b.setAttribute('aria-pressed', String(on));
      b.classList.toggle('is-playing', on);
    }
  }

  // Before metadata loads, setting currentTime sets the start position, so a saved position shows straight away.
  function restore() {
    const at = saved.get(key);
    audio.currentTime = at && (!known || at < known - 5) ? at : 0;
    render();
  }

  function play() {
    const p = audio.play();
    if (p) p.catch(() => setPlaying(false));
  }
  const toggle = () => (audio.paused ? play() : audio.pause());
  const skip = (n) => {
    const d = duration();
    audio.currentTime = Math.max(0, Math.min(d || Infinity, (audio.currentTime || 0) + n));
    render();
  };

  function mediaSession() {
    if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined') return;
    const ms = navigator.mediaSession;
    ms.metadata = new MediaMetadata({
      title: root.dataset.title || '',
      artist: 'Headline Recorder',
      album: root.dataset.album || '',
      artwork: root.dataset.artwork ? [{ src: root.dataset.artwork }] : [],
    });
    const handlers = {
      play,
      pause: () => audio.pause(),
      stop: () => { audio.pause(); audio.currentTime = 0; },
      seekbackward: (e) => skip(-(e.seekOffset || 15)),
      seekforward: (e) => skip(e.seekOffset || 15),
      seekto: (e) => { audio.currentTime = e.seekTime; render(); },
    };
    for (const [action, fn] of Object.entries(handlers)) {
      try { ms.setActionHandler(action, fn); } catch { /* unsupported action */ }
    }
  }

  audio.addEventListener('play', () => { pauseAll(audio); setPlaying(true); mediaSession(); });
  audio.addEventListener('pause', () => { setPlaying(false); saved.set(key, audio.currentTime); });
  audio.addEventListener('ended', () => { setPlaying(false); saved.set(key, 0); });
  audio.addEventListener('error', () => setPlaying(false));
  audio.addEventListener('loadedmetadata', render);
  audio.addEventListener('timeupdate', () => {
    render();
    if (Date.now() - lastSave > 5000) {
      lastSave = Date.now();
      saved.set(key, audio.currentTime);
    }
  });

  for (const b of root.querySelectorAll('[data-hrp-play]')) b.addEventListener('click', toggle);
  for (const b of root.querySelectorAll('[data-hrp-skip]')) b.addEventListener('click', () => skip(Number(b.dataset.hrpSkip)));
  seek.addEventListener('input', () => { audio.currentTime = Number(seek.value); render(); });
  speed.addEventListener('click', () => {
    rate = (rate + 1) % SPEEDS.length;
    audio.playbackRate = audio.defaultPlaybackRate = SPEEDS[rate];
    speed.textContent = `${SPEEDS[rate]}×`;
    speed.setAttribute('aria-label', `Playback speed: ${SPEEDS[rate]}×`);
  });
  mute.addEventListener('click', () => {
    audio.muted = !audio.muted;
    mute.setAttribute('aria-pressed', String(audio.muted));
  });

  // Space/K play-pause, J/L ±15 s while focus is inside the player; arrows stay native on the slider.
  root.addEventListener('keydown', (e) => {
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    const k = e.key.toLowerCase();
    if (k === 'k' || (k === ' ' && e.target.tagName !== 'BUTTON')) toggle();
    else if (k === 'j') skip(-15);
    else if (k === 'l') skip(15);
    else return;
    e.preventDefault();
  });

  players.set(root, {
    audio,
    toggle,
    focus: () => q('[data-hrp-play]').focus({ preventScroll: true }),
    load(d) {
      if (`hrp:pos:${d.key}` === key) return toggle();
      saved.set(key, audio.currentTime);
      audio.pause();
      key = `hrp:pos:${d.key}`;
      known = Number(d.duration) || 0;
      root.dataset.key = d.key;
      root.dataset.title = d.title;
      for (const t of root.querySelectorAll('[data-hrp-title]')) t.textContent = d.title;
      audio.src = d.src;
      restore();
      play();
    },
  });
  restore();
}

function initVoice(el) {
  const synth = window.speechSynthesis;
  const source = document.querySelector(el.dataset.source);
  if (!synth || typeof SpeechSynthesisUtterance === 'undefined' || !source) return;
  const q = (s) => el.querySelector(s);
  const playBtn = q('[data-hrp-voice-play]');
  const stopBtn = q('[data-hrp-voice-stop]');
  const label = q('[data-hrp-voice-label]');
  const status = q('[data-hrp-voice-status]');
  const note = q('[data-hrp-voice-note] span');
  let chunks = null;
  let index = 0;
  let state = 'stopped';
  let token = 0; // invalidates callbacks of utterances we cancelled

  q('.hrp-voice__buttons').hidden = false;
  note.textContent = 'Read aloud by your device’s built-in synthetic voice, not a recording.';

  // Sentences grouped into short utterances: some engines stop silently on long ones.
  function split(text) {
    const sentences = text.replace(/\s+/g, ' ').match(/[^.!?]+[.!?]+["'”’)]*\s*|[^.!?]+$/g) || [];
    const out = [];
    for (const s of sentences) {
      const last = out.length - 1;
      if (last >= 0 && out[last].length + s.length < 220) out[last] += s;
      else out.push(s);
    }
    return out.map((s) => s.trim()).filter(Boolean);
  }

  function voice() {
    const all = synth.getVoices().filter((v) => v.lang.toLowerCase().startsWith('en'));
    return all.find((v) => v.localService) || all[0] || null;
  }

  function setState(next, message) {
    state = next;
    el.classList.toggle('is-playing', next === 'playing');
    label.textContent = next === 'playing' ? 'Pause device voice' : next === 'paused' ? 'Resume device voice' : 'Listen with device voice';
    stopBtn.disabled = next === 'stopped';
    status.textContent = message;
  }

  function speak() {
    const mine = ++token;
    if (index >= chunks.length) {
      index = 0;
      setState('stopped', 'Finished reading.');
      return;
    }
    const u = new SpeechSynthesisUtterance(chunks[index]);
    const v = voice();
    if (v) u.voice = v;
    u.lang = v ? v.lang : 'en';
    u.onend = () => {
      if (mine !== token || state !== 'playing') return;
      index++;
      speak();
    };
    u.onerror = (e) => {
      if (mine !== token || e.error === 'interrupted' || e.error === 'canceled') return;
      setState('stopped', 'Your device could not read this aloud.');
    };
    synth.speak(u);
  }

  const controller = {
    pause() {
      if (state !== 'playing') return;
      token++;
      synth.cancel();
      setState('paused', 'Paused.');
    },
  };
  voices.add(controller);

  playBtn.addEventListener('click', () => {
    if (state === 'playing') return controller.pause();
    pauseAll(null, controller);
    if (!chunks) {
      const clone = source.cloneNode(true);
      for (const n of clone.querySelectorAll('script, style, noscript, iframe, svg, button, figcaption, .hrp-listen')) n.remove();
      chunks = split(clone.textContent || '');
    }
    if (!chunks.length) return setState('stopped', 'There is no text to read.');
    synth.cancel();
    setState('playing', 'Reading aloud with your device voice.');
    speak();
  });
  stopBtn.addEventListener('click', () => {
    token++;
    synth.cancel();
    index = 0;
    setState('stopped', 'Stopped.');
  });
  window.addEventListener('pagehide', () => synth.cancel());
}

document.addEventListener('click', (e) => {
  const target = e.target.closest('[data-hrp-target]');
  if (target) {
    const p = players.get(document.getElementById(target.dataset.hrpTarget));
    if (p) p.toggle();
    return;
  }
  const load = e.target.closest('[data-hrp-load]');
  if (!load) return;
  const root = document.getElementById(load.dataset.hrpLoad);
  const p = players.get(root);
  if (!p) return;
  const d = load.dataset;
  const card = root.closest('.hrp-podcast-featured');
  if (card) {
    const link = card.querySelector('[data-hrp-featured-link]');
    link.textContent = d.title;
    link.href = d.url;
    const meta = card.querySelector('[data-hrp-featured-meta]');
    if (meta) meta.textContent = d.meta;
    const excerpt = card.querySelector('[data-hrp-featured-excerpt]');
    if (excerpt) excerpt.textContent = d.excerpt;
    card.querySelector('.hrp-eyebrow').textContent = 'Now playing';
    card.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
  }
  p.load(d);
  p.focus();
});

document.querySelectorAll('[data-hrp-player]').forEach(initPlayer);
document.querySelectorAll('[data-hrp-voice]').forEach(initVoice);
