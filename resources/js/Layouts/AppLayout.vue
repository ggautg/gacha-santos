<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ToastStack from '../Components/ToastStack.vue';
import {
    formatCountdown,
    formatNumber,
    startFreeCountdown,
    syncPlayer,
    usePlayer,
} from '../lib/player';
import { isMuted, play, toggleMuted } from '../lib/sfx';
import {
    applyFont,
    applyTheme,
    currentFont,
    currentTheme,
    FONTS,
    resetTheme,
    THEMES,
} from '../lib/theme';
import { toast } from '../lib/toast';

const page = usePage();
const player = usePlayer();

const nav = [
    { href: '/', label: 'Inicio', icon: 'M2 7.5 8 2.5l6 5V14H2z M6 14V9.5h4V14' },
    { href: '/orar', label: 'Orar', icon: 'M8 1.5 9.9 6 14.5 6.7 11.2 9.9 12 14.5 8 12.1 4 14.5 4.8 9.9 1.5 6.7 6.1 6z' },
    { href: '/coleccion', label: 'Colección', icon: 'M3 2.5h10v11H3z M5.5 2.5v11 M10.5 2.5v11' },
    { href: '/mazmorras', label: 'Mazmorras', icon: 'M3 2.5h10v11H3z M6 13.5V9a2 2 0 0 1 4 0v4.5 M3 6.5h10' },
];

const isActive = (href) => (href === '/' ? page.url === '/' : page.url.startsWith(href));

const profileOpen = ref(false);
const profileMenu = ref(null);

const freeInLabel = computed(() =>
    player.free_ready ? 'Gratis lista' : `Gratis en ${formatCountdown(player.free_in)}`,
);
const soundTitle = computed(() => (isMuted.value ? 'Activar sonido' : 'Silenciar sonido'));
const initial = computed(() => (player.name ? player.name.trim().charAt(0).toUpperCase() : '✦'));

watch(
    () => page.props.player,
    (value) => syncPlayer(value),
    { immediate: true, deep: true },
);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    },
    { deep: true },
);

let stopCountdown = null;

function onDocumentClick(event) {
    if (profileOpen.value && profileMenu.value && !profileMenu.value.contains(event.target)) {
        profileOpen.value = false;
    }
}

function onDocumentKey(event) {
    if (event.key === 'Escape') {
        profileOpen.value = false;
    }
}

onMounted(() => {
    stopCountdown = startFreeCountdown();
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKey);
});

onBeforeUnmount(() => {
    stopCountdown?.();
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKey);
});

const onNav = () => {
    play('click');
    profileOpen.value = false;
};

function toggleProfile() {
    profileOpen.value = !profileOpen.value;
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

function resetAppearance() {
    resetTheme();
    play('click');
}

function toggleSound() {
    toggleMuted();
    play('click');
}
</script>

<template>
    <div class="launcher">
        <header class="launcher-top">
            <div class="launcher-top-inner">
                <div class="launcher-left">
                    <Link href="/mazmorras" class="launcher-play" title="Jugar: descender a las mazmorras" @click="onNav">
                        <span class="launcher-play-icon" aria-hidden="true">▶</span>
                        <span class="launcher-play-copy">
                            <strong>Jugar</strong>
                            <span>Descenso procedural</span>
                        </span>
                    </Link>

                    <Link href="/" class="brand launcher-brand" aria-label="Ir al inicio" @click="onNav">
                        <span class="brand-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <rect x="10" y="2" width="4" height="20" />
                                <rect x="5" y="7" width="14" height="4" />
                            </svg>
                        </span>
                        <span class="brand-copy">
                            <span class="brand-name">SANCTORUM</span>
                            <span class="brand-sub">Launcher de Santos</span>
                        </span>
                    </Link>
                </div>

                <div class="launcher-top-actions">
                    <span class="grace-chip" :title="freeInLabel">
                        <span class="coin" aria-hidden="true"></span>
                        {{ formatNumber(player.grace) }}
                        <span class="sr-only">Puntos de Fe</span>
                    </span>

                    <div ref="profileMenu" class="profile-menu">
                        <button
                            type="button"
                            class="launcher-user profile-trigger"
                            :class="{ 'is-open': profileOpen }"
                            :aria-expanded="profileOpen"
                            aria-haspopup="menu"
                            :title="`Perfil de ${player.name || 'Peregrino'}`"
                            @click="toggleProfile"
                        >
                            <span class="launcher-avatar" aria-hidden="true">{{ initial }}</span>
                            <span class="launcher-user-copy">
                                <strong>{{ player.name || 'Peregrino' }}</strong>
                                <span>Peregrino</span>
                            </span>
                            <svg class="profile-chevron" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path d="M2.5 4.5 6 8l3.5-3.5" />
                            </svg>
                        </button>

                        <div v-if="profileOpen" class="profile-dropdown" role="menu" aria-label="Perfil y opciones">
                            <div class="profile-dropdown-head">
                                <span class="launcher-avatar" aria-hidden="true">{{ initial }}</span>
                                <div class="profile-dropdown-id">
                                    <strong>{{ player.name || 'Peregrino' }}</strong>
                                    <span>Peregrino · {{ freeInLabel }}</span>
                                </div>
                                <span class="grace-chip grace-chip--sm">
                                    <span class="coin" aria-hidden="true"></span>
                                    {{ formatNumber(player.grace) }}
                                    <span class="sr-only">Puntos de Fe</span>
                                </span>
                            </div>

                            <p class="profile-dropdown-label">Navegación</p>
                            <nav class="profile-menu-nav">
                                <Link
                                    v-for="item in nav"
                                    :key="item.href"
                                    :href="item.href"
                                    class="profile-menu-item"
                                    :class="{ 'is-active': isActive(item.href) }"
                                    :aria-current="isActive(item.href) ? 'page' : undefined"
                                    role="menuitem"
                                    @click="onNav"
                                >
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true">
                                        <path :d="item.icon" />
                                    </svg>
                                    <span>{{ item.label }}</span>
                                    <span v-if="isActive(item.href)" class="profile-menu-dot" aria-hidden="true"></span>
                                </Link>
                            </nav>

                            <p class="profile-dropdown-label">Apariencia</p>
                            <div class="profile-themes">
                                <button
                                    v-for="theme in THEMES"
                                    :key="theme.key"
                                    type="button"
                                    class="profile-theme"
                                    :class="{ 'is-active': currentTheme === theme.key }"
                                    :aria-pressed="currentTheme === theme.key"
                                    :title="`${theme.name} · ${theme.note}`"
                                    @click="chooseTheme(theme.key)"
                                >
                                    <span class="profile-theme-swatches" aria-hidden="true">
                                        <span :style="{ background: theme.accent }"></span>
                                        <span :style="{ background: theme.accent2 }"></span>
                                        <span :style="{ background: theme.ink }"></span>
                                    </span>
                                    <span>{{ theme.name }}</span>
                                </button>
                            </div>
                            <div class="profile-fonts">
                                <button
                                    v-for="font in FONTS"
                                    :key="font.key"
                                    type="button"
                                    class="profile-font"
                                    :class="{ 'is-active': currentFont === font.key }"
                                    :aria-pressed="currentFont === font.key"
                                    :title="font.note"
                                    :style="{ fontFamily: font.stack }"
                                    @click="chooseFont(font.key)"
                                >
                                    Aa
                                </button>
                                <button type="button" class="profile-reset" @click="resetAppearance">
                                    Restablecer
                                </button>
                            </div>

                            <div class="profile-dropdown-foot">
                                <button type="button" class="profile-action" @click="toggleSound">
                                    <svg v-if="isMuted" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                                        <path d="M2 6h3l3.5-3v10L5 10H2z M11 6l4 4 M15 6l-4 4" />
                                    </svg>
                                    <svg v-else viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                                        <path d="M2 6h3l3.5-3v10L5 10H2z M11 5.5a3.5 3.5 0 0 1 0 5 M12.8 3.5a6 6 0 0 1 0 9" />
                                    </svg>
                                    {{ soundTitle }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="launcher-body">
            <nav class="launcher-rail" aria-label="Navegación principal">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="rail-link"
                    :class="{ 'is-active': isActive(item.href) }"
                    :aria-current="isActive(item.href) ? 'page' : undefined"
                    @click="onNav"
                >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true">
                        <path :d="item.icon" />
                    </svg>
                    <span>{{ item.label }}</span>
                </Link>

                <div class="rail-spacer"></div>

                <div class="rail-foot">
                    <span class="rail-foot-mark" aria-hidden="true">✦</span>
                    <span class="rail-foot-label">v1.0</span>
                </div>
            </nav>

            <main class="launcher-main">
                <div class="launcher-view">
                    <slot />
                </div>
            </main>
        </div>

        <footer class="launcher-bottom">
            <div class="launcher-bottom-left">
                <span class="launcher-dot" aria-hidden="true"></span>
                <span>Conectado · Santoral</span>
            </div>
            <div class="launcher-bottom-center">
                <strong>Sanctorum</strong> · Gacha de Santos
            </div>
            <div class="launcher-bottom-right">
                <span>{{ player.name || 'Peregrino' }}</span>
                <span class="launcher-sep" aria-hidden="true">·</span>
                <span>{{ formatNumber(player.grace) }} Puntos de Fe</span>
                <span class="launcher-sep" aria-hidden="true">·</span>
                <span>{{ freeInLabel }}</span>
            </div>
        </footer>

        <ToastStack />
    </div>
</template>
