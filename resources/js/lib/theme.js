import { ref } from 'vue';

const THEME_KEY = 'sanctorum.theme';
const FONT_KEY = 'sanctorum.font';

export const THEMES = [
    {
        key: 'clasico',
        name: 'Sanctorum',
        note: 'Púrpura y oro',
        font: 'pixel',
        accent: '#5a4a9a',
        accent2: '#f2c94c',
        ink: '#0b0818',
    },
    {
        key: 'pecados',
        name: 'Pecados',
        note: 'Carmesí y oro',
        font: 'pixel',
        accent: '#d14a3f',
        accent2: '#f2c94c',
        ink: '#150507',
    },
    {
        key: 'goetia',
        name: 'Goetia',
        note: 'Violeta y cian',
        font: 'rubrica',
        accent: '#8b6cf0',
        accent2: '#49b8c9',
        ink: '#0a0716',
    },
    {
        key: 'catacumbas',
        name: 'Catacumbas',
        note: 'Verde hueso',
        font: 'hueso',
        accent: '#8fb26b',
        accent2: '#e4d8a8',
        ink: '#0c110c',
    },
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

export const currentTheme = ref('clasico');
export const currentFont = ref('pixel');

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
    return THEMES.find((theme) => theme.key === key) ?? THEMES[0];
}

function findFont(key) {
    return FONTS.find((font) => font.key === key) ?? FONTS[0];
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
        storeValue(FONT_KEY, font.key === 'pixel' ? null : font.key);
    }
}

export function applyTheme(key, { persist = true, font } = {}) {
    const theme = findTheme(key);

    currentTheme.value = theme.key;

    if (theme.key === 'clasico') {
        delete document.documentElement.dataset.theme;
    } else {
        document.documentElement.dataset.theme = theme.key;
    }

    if (persist) {
        storeValue(THEME_KEY, theme.key === 'clasico' ? null : theme.key);
    }

    applyFont(font ?? theme.font, { persist });
    syncMeta(theme);
}

export function resetTheme() {
    applyTheme('clasico');
    applyFont('pixel');
}

/**
 * Aplica el tema guardado antes de montar Vue para evitar un parpadeo de color.
 */
export function initTheme() {
    const storedTheme = readStored(THEME_KEY);
    const storedFont = readStored(FONT_KEY);

    applyTheme(storedTheme ?? 'clasico', { persist: false });

    if (storedFont) {
        applyFont(storedFont, { persist: false });
    }
}
