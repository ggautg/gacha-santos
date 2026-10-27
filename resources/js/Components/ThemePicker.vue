<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { play } from '../lib/sfx';
import {
    applyFont,
    applyTheme,
    currentFont,
    currentTheme,
    FONTS,
    resetTheme,
    THEMES,
} from '../lib/theme';

const open = ref(false);
const picker = ref(null);

const activeTheme = computed(() => THEMES.find((theme) => theme.key === currentTheme.value) ?? THEMES[0]);

function toggle() {
    open.value = !open.value;
    play('click');
}

function chooseTheme(key) {
    applyTheme(key);
    play('click');
}

function chooseFont(key) {
    applyFont(key);
    play('click');
}

function reset() {
    resetTheme();
    play('click');
}

function onDocumentClick(event) {
    if (open.value && picker.value && !picker.value.contains(event.target)) {
        open.value = false;
    }
}

function onDocumentKey(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKey);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKey);
});
</script>

<template>
    <div ref="picker" class="theme-picker">
        <button
            type="button"
            class="icon-btn theme-toggle"
            :class="{ 'is-open': open }"
            :aria-expanded="open"
            aria-haspopup="dialog"
            :title="`Temas y fuentes · ${activeTheme.name}`"
            :aria-label="`Temas y fuentes. Tema actual: ${activeTheme.name}`"
            @click="toggle"
        >
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                <path d="M8 2.5a5.5 5.5 0 1 0 0 11c1.2 0 1.8-.8 1.8-1.8 0-.6-.25-1-.25-1.7 0-1 .8-1.7 1.9-1.7H13A2.5 2.5 0 0 0 15.5 7c0-2.5-3.1-4.5-7.5-4.5Z" />
                <circle cx="5.4" cy="6.2" r="0.9" fill="currentColor" stroke="none" />
                <circle cx="8.4" cy="4.8" r="0.9" fill="currentColor" stroke="none" />
                <circle cx="11.2" cy="6.4" r="0.9" fill="currentColor" stroke="none" />
            </svg>
        </button>

        <div v-if="open" class="theme-popover" role="dialog" aria-label="Selector de temas y fuentes">
            <div class="theme-popover-head">
                <span class="page-kicker">Apariencia</span>
                <button type="button" class="link-button" @click="reset">Restablecer</button>
            </div>

            <p class="theme-picker-label">Paleta</p>
            <div class="theme-grid">
                <button
                    v-for="theme in THEMES"
                    :key="theme.key"
                    type="button"
                    class="theme-option"
                    :class="{ 'is-active': currentTheme === theme.key }"
                    :aria-pressed="currentTheme === theme.key"
                    @click="chooseTheme(theme.key)"
                >
                    <span class="theme-swatches" aria-hidden="true">
                        <span :style="{ background: theme.accent }"></span>
                        <span :style="{ background: theme.accent2 }"></span>
                        <span :style="{ background: theme.ink }"></span>
                    </span>
                    <strong>{{ theme.name }}</strong>
                    <span class="theme-picker-note">{{ theme.note }}</span>
                </button>
            </div>

            <p class="theme-picker-label">Tipografía</p>
            <div class="theme-font-grid">
                <button
                    v-for="font in FONTS"
                    :key="font.key"
                    type="button"
                    class="theme-font-option"
                    :class="{ 'is-active': currentFont === font.key }"
                    :aria-pressed="currentFont === font.key"
                    @click="chooseFont(font.key)"
                >
                    <span class="theme-font-sample" :style="{ fontFamily: font.stack }">Aa</span>
                    <span class="theme-font-copy">
                        <strong>{{ font.name }}</strong>
                        <span class="theme-picker-note">{{ font.note }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>
