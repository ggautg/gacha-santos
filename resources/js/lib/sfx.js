import { ref } from 'vue';

const STORAGE_KEY = 'sanctorum.muted';

let context = null;

function readMuted() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export const isMuted = ref(readMuted());

function audio() {
    if (typeof window === 'undefined') {
        return null;
    }

    const Ctor = window.AudioContext ?? window.webkitAudioContext;

    if (!Ctor) {
        return null;
    }

    if (!context) {
        context = new Ctor();
    }

    if (context.state === 'suspended') {
        context.resume().catch(() => {});
    }

    return context;
}

function tone(frequency, { duration = 0.08, type = 'square', gain = 0.03, delay = 0, endFrequency = null } = {}) {
    const ctx = audio();

    if (!ctx || isMuted.value) {
        return;
    }

    const start = ctx.currentTime + delay;
    const oscillator = ctx.createOscillator();
    const volume = ctx.createGain();

    oscillator.type = type;
    oscillator.frequency.setValueAtTime(frequency, start);

    if (endFrequency !== null) {
        oscillator.frequency.linearRampToValueAtTime(endFrequency, start + duration);
    }

    volume.gain.setValueAtTime(0.0001, start);
    volume.gain.exponentialRampToValueAtTime(gain, start + 0.012);
    volume.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(volume).connect(ctx.destination);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.02);
}

function noise({ duration = 0.09, gain = 0.05, delay = 0 } = {}) {
    const ctx = audio();

    if (!ctx || isMuted.value) {
        return;
    }

    const start = ctx.currentTime + delay;
    const frames = Math.max(1, Math.floor(ctx.sampleRate * duration));
    const buffer = ctx.createBuffer(1, frames, ctx.sampleRate);
    const data = buffer.getChannelData(0);

    for (let i = 0; i < frames; i += 1) {
        data[i] = (Math.random() * 2 - 1) * (1 - i / frames) ** 2;
    }

    const source = ctx.createBufferSource();
    const volume = ctx.createGain();
    const filter = ctx.createBiquadFilter();

    filter.type = 'lowpass';
    filter.frequency.setValueAtTime(1800, start);

    volume.gain.setValueAtTime(gain, start);
    volume.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    source.buffer = buffer;
    source.connect(filter).connect(volume).connect(ctx.destination);
    source.start(start);
}

function melody(notes, { spacing = 0.09, duration = 0.12, type = 'triangle', gain = 0.035 } = {}) {
    notes.forEach((note, index) => {
        tone(note, { duration, type, gain, delay: index * spacing });
    });
}

const sounds = {
    click: () => tone(340, { duration: 0.05, gain: 0.02, endFrequency: 260 }),
    open: () => tone(440, { duration: 0.07, gain: 0.025, endFrequency: 620 }),
    pull: () => melody([330, 440, 554, 660], { spacing: 0.07, duration: 0.14, type: 'triangle' }),
    common: () => melody([262, 330], { spacing: 0.08, duration: 0.12 }),
    rare: () => melody([523, 659, 784, 1046], { spacing: 0.08, duration: 0.18, type: 'square', gain: 0.03 }),
    five: () =>
        melody([523, 659, 784, 1046, 1318], {
            spacing: 0.1,
            duration: 0.26,
            type: 'triangle',
            gain: 0.04,
        }),
    hit: () => {
        noise({ gain: 0.045 });
        tone(110, { duration: 0.07, type: 'sawtooth', gain: 0.025, endFrequency: 70 });
    },
    crit: () => {
        noise({ gain: 0.06 });
        tone(180, { duration: 0.09, type: 'square', gain: 0.03, endFrequency: 90 });
    },
    heal: () => melody([392, 523, 659], { spacing: 0.06, duration: 0.16, type: 'sine', gain: 0.03 }),
    shield: () => melody([220, 330], { spacing: 0.07, duration: 0.14, type: 'triangle', gain: 0.025 }),
    stun: () => tone(200, { duration: 0.18, type: 'sawtooth', gain: 0.022, endFrequency: 420 }),
    win: () => melody([523, 659, 784, 1046], { spacing: 0.13, duration: 0.3, type: 'triangle', gain: 0.04 }),
    lose: () => melody([392, 330, 262, 196], { spacing: 0.14, duration: 0.28, type: 'sawtooth', gain: 0.03 }),
    level: () => melody([659, 880, 1046], { spacing: 0.08, duration: 0.2, type: 'square', gain: 0.03 }),
    error: () => melody([196, 165], { spacing: 0.09, duration: 0.12, type: 'square', gain: 0.025 }),
};

export function play(name) {
    try {
        sounds[name]?.();
    } catch {
        // El audio es un extra: si falla, el juego sigue funcionando.
    }
}

export function toggleMuted() {
    isMuted.value = !isMuted.value;

    try {
        window.localStorage.setItem(STORAGE_KEY, isMuted.value ? '1' : '0');
    } catch {
        // Sin almacenamiento disponible: el ajuste dura la sesión.
    }

    if (!isMuted.value) {
        play('click');
    }

    return isMuted.value;
}
