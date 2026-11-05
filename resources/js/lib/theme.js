import { ref } from 'vue';

// v2: Gloria pasa a ser el tema por defecto y descarta elecciones antiguas.
const THEME_KEY = 'sanctorum.theme.v2';
// v2: la tipografía legible pasa a ser la opción por defecto.
const FONT_KEY = 'sanctorum.font.v2';

export const THEMES = [
    // accent = --gold, accent2 = --line, ink = --panel
    { key: 'pecados', name: 'Pecados', note: 'Rojo sangre y oro', accent: '#f2b84b', accent2: '#8a2f36', ink: '#2a0e15' },
    { key: 'goetia', name: 'Goetia', note: 'Violeta arcano', accent: '#b79cff', accent2: '#5a4ec2', ink: '#18153f' },
    { key: 'catacumbas', name: 'Catacumbas', note: 'Musgo y hueso', accent: '#c9d98c', accent2: '#587a4c', ink: '#192417' },
    { key: 'gloria', name: 'Gloria', note: 'Azul medianoche y oro', accent: '#f5cf6b', accent2: '#3f5aa8', ink: '#131f44' },
    { key: 'galilea', name: 'Galilea', note: 'Mar verde azulado', accent: '#5eead4', accent2: '#2f8c82', ink: '#0f2c2a' },
    { key: 'rosa', name: 'Rosa', note: 'Ciruela y rosa', accent: '#ff9ec9', accent2: '#a04a8a', ink: '#2c1329' },
    { key: 'alba', name: 'Alba', note: 'Amanecer ámbar', accent: '#ffb347', accent2: '#9a6a2e', ink: '#2a1c0e' },
    { key: 'requiem', name: 'Réquiem', note: 'Grafito y plata', accent: '#c9d2e3', accent2: '#5b6578', ink: '#181b22' },
];

export const FONTS = [
    {
        key: 'pixel',
        name: 'Píxel',
        note: 'Press Start 2P · VT323',
        stack: "'Press Start 2P', 'Courier New', monospace",
    },
    {
        key: 'rubrica',
        name: 'Rúbrica',
        note: 'Palatino · Trebuchet',
        stack: "'Palatino Linotype', 'Book Antiqua', Palatino, Georgia, serif",
    },
    {
        key: 'hueso',
        name: 'Hueso',
        note: 'Courier · Georgia',
        stack: "'Courier New', Courier, monospace",
    },
    {
        key: 'clasica',
        name: 'Clásica',
        note: 'Georgia · Segoe UI',
        stack: "Georgia, 'Times New Roman', serif",
    },
];

const DEFAULT_THEME = 'gloria';

export const currentTheme = ref(DEFAULT_THEME);
export const currentFont = ref('clasica');

function readStored(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function storeValue(key, value) {
    try {
        if (value) {
            window.localStorage.setItem(key, value);
        } else {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Sin almacenamiento: el tema dura lo que la pestaña.
    }
}

function findTheme(key) {
    return THEMES.find((theme) => theme.key === key) ?? THEMES.find((theme) => theme.key === DEFAULT_THEME);
}

function findFont(key) {
    return FONTS.find((font) => font.key === key) ?? FONTS.find((font) => font.key === 'clasica');
}

function syncMeta(theme) {
    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', theme.ink);
    }
}

export function applyFont(key, { persist = true } = {}) {
    const font = findFont(key);

    currentFont.value = font.key;
    document.documentElement.dataset.font = font.key;

    if (persist) {
        storeValue(FONT_KEY, font.key);
    }
}

export function applyTheme(key, { persist = true, font } = {}) {
    const theme = findTheme(key);

    currentTheme.value = theme.key;
    document.documentElement.dataset.theme = theme.key;

    if (persist) {
        storeValue(THEME_KEY, theme.key === DEFAULT_THEME ? null : theme.key);
    }

    applyFont(font ?? theme.font, { persist });
    syncMeta(theme);
}

export function resetTheme() {
    applyTheme(DEFAULT_THEME);
    applyFont('clasica');
}

/**
 * Aplica el tema guardado antes de montar Vue para evitar un parpadeo de color.
 */
export function initTheme() {
    const storedTheme = readStored(THEME_KEY);
    const storedFont = readStored(FONT_KEY);

    applyTheme(storedTheme ?? DEFAULT_THEME, { persist: false });

    if (storedFont) {
        applyFont(storedFont, { persist: false });
    }
}
