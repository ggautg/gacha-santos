<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemePicker from '../Components/ThemePicker.vue';
import ToastStack from '../Components/ToastStack.vue';
import { formatNumber, startFreeCountdown, syncPlayer, usePlayer } from '../lib/player';
import { isMuted, play, toggleMuted } from '../lib/sfx';
import { toast } from '../lib/toast';

const page = usePage();
const player = usePlayer();

const nav = [
    { href: '/', label: 'Santuario', icon: 'M2 7.5 8 2.5l6 5V14H2z M6 14V9.5h4V14' },
    { href: '/coleccion', label: 'Colección', icon: 'M3 2.5h10v11H3z M5.5 2.5v11 M10.5 2.5v11' },
    { href: '/mazmorras', label: 'Mazmorras', icon: 'M3 2.5h10v11H3z M6 13.5V9a2 2 0 0 1 4 0v4.5 M3 6.5h10' },
];

const isActive = (href) => (href === '/' ? page.url === '/' : page.url.startsWith(href));

const freeLabel = computed(() => (player.free_ready ? 'Invocación gratuita lista' : 'La gracia se renueva'));
const soundTitle = computed(() => (isMuted.value ? 'Activar sonido' : 'Silenciar sonido'));

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

onMounted(() => {
    stopCountdown = startFreeCountdown();
});

onBeforeUnmount(() => {
    stopCountdown?.();
});

const onNav = () => play('click');
</script>

<template>
    <div class="shell">
        <header class="topbar">
            <div class="topbar-inner">
                <Link href="/" class="brand" aria-label="Ir al santuario" @click="onNav">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <rect x="10" y="2" width="4" height="20" />
                            <rect x="5" y="7" width="14" height="4" />
                        </svg>
                    </span>
                    <span>
                        <span class="brand-name">SANCTORUM</span>
                        <span class="brand-sub">Gacha de Santos</span>
                    </span>
                </Link>

                <nav class="main-nav" aria-label="Navegación principal">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="nav-link"
                        :class="{ 'is-active': isActive(item.href) }"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        @click="onNav"
                    >
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                            <path :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>

                <div class="topbar-stats">
                    <span v-if="player.name" class="player-chip" :title="`Peregrino: ${player.name}`">
                        {{ player.name }}
                    </span>
                    <span class="grace-chip" :title="freeLabel">
                        <span class="coin" aria-hidden="true"></span>
                        {{ formatNumber(player.grace) }}
                        <span class="sr-only">de Gracia</span>
                    </span>
                    <ThemePicker />
                    <button
                        type="button"
                        class="icon-btn"
                        :title="soundTitle"
                        :aria-label="soundTitle"
                        @click="toggleMuted"
                    >
                        <svg v-if="isMuted" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                            <path d="M2 6h3l3.5-3v10L5 10H2z M11 6l4 4 M15 6l-4 4" />
                        </svg>
                        <svg v-else viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" aria-hidden="true">
                            <path d="M2 6h3l3.5-3v10L5 10H2z M11 5.5a3.5 3.5 0 0 1 0 5 M12.8 3.5a6 6 0 0 1 0 9" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <main class="page">
            <slot />
        </main>

        <footer class="footer">
            <strong>Sanctorum</strong> · Gacha de Santos · Hecho con fe y píxeles ·
            <span class="dim">Las figuras son representaciones devocionales.</span>
        </footer>

        <ToastStack />
    </div>
</template>
