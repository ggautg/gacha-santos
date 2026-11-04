<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { play } from '../lib/sfx';

defineProps({
    dungeons: { type: Array, default: () => [] },
    deepest: { type: Number, default: 0 },
    player: { type: Object, required: true },
});

function nodeGlyph(kind) {
    switch (kind) {
        case 'camp':
            return '✦';
        case 'altar':
            return '✚';
        case 'trap':
            return '⚠';
        case 'boss':
            return '☠';
        default:
            return '⚔';
    }
}

function nodeLabel(kind) {
    switch (kind) {
        case 'camp':
            return 'Campamento';
        case 'altar':
            return 'Altar';
        case 'trap':
            return 'Trampa';
        case 'boss':
            return 'Jefe';
        case 'battle':
            return 'Combate';
        default:
            return 'Descenso';
    }
}
</script>

<template>
    <Head title="Mazmorras" />

    <header class="page-head">
        <div>
            <span class="page-kicker">Sanctorum · Descensos procedurales</span>
            <h1 class="page-title">Elige tu descenso</h1>
            <p class="page-lead">
                Cada mazmorra se genera al azar. Entra, elige un camino y mira a tus santos
                abrirse paso hasta el jefe del piso.
            </p>
        </div>
        <div class="btn-row">
            <span class="tag">Mazmorras <strong>{{ dungeons.length }}</strong></span>
            <span class="tag">Piso más hondo <strong>{{ deepest }}</strong></span>
            <span class="tag">Puntos de Fe <strong>{{ player.grace }}</strong></span>
        </div>
    </header>

    <div class="dungeon-grid">
        <article
            v-for="dungeon in dungeons"
            :key="dungeon.key"
            class="dungeon-card"
            :class="{ 'is-sealed': !dungeon.unlocked }"
            :data-theme="dungeon.theme"
            :style="{ '--accent': dungeon.accent, '--accent2': dungeon.accent2 }"
        >
            <div class="dungeon-banner">
                <h2 class="dungeon-name">{{ dungeon.name }}</h2>
                <p class="dungeon-sub">{{ dungeon.subtitle }}</p>
                <span v-if="!dungeon.unlocked" class="dungeon-seal" aria-hidden="true">✕</span>

                <div class="dungeon-swatches" aria-hidden="true">
                    <span :style="{ background: dungeon.accent }"></span>
                    <span :style="{ background: dungeon.accent2 }"></span>
                    <span class="dungeon-swatch--panel"></span>
                </div>
            </div>

            <div class="dungeon-body">
                <div class="dungeon-next">
                    <div v-if="dungeon.unlocked" class="dungeon-next-inner">
                        <div class="dungeon-portrait">
                            <img
                                v-if="dungeon.next?.sprite"
                                :src="dungeon.next.sprite"
                                :alt="dungeon.next.name"
                                draggable="false"
                            />
                            <span v-else aria-hidden="true">{{ nodeGlyph(dungeon.next?.kind) }}</span>
                        </div>
                        <div class="dungeon-next-copy">
                            <span class="eyebrow">{{ nodeLabel(dungeon.next?.kind) }} · Piso {{ dungeon.next?.floor ?? dungeon.run_floor }}</span>
                            <strong>{{ dungeon.next?.name ?? 'La puerta aguarda' }}</strong>
                            <span class="panel-note">{{ dungeon.next?.subtitle }}</span>
                        </div>
                    </div>

                    <div v-else class="dungeon-next-inner">
                        <div class="dungeon-portrait dungeon-portrait--locked" aria-hidden="true">?</div>
                        <div class="dungeon-next-copy">
                            <span class="eyebrow">Sellada</span>
                            <strong>{{ dungeon.unlock_text }}</strong>
                        </div>
                    </div>
                </div>

                <div class="meter" :class="dungeon.unlocked ? '' : 'meter--violet'">
                    <span :style="{ width: `${Math.min(100, dungeon.best_floor * 10)}%` }"></span>
                </div>
                <div class="dungeon-progress-note">
                    <span>Mejor piso · {{ dungeon.best_floor }}</span>
                    <span>{{ dungeon.cleared }} limpios</span>
                </div>

                <div v-if="dungeon.unlocked" class="btn-row">
                    <Link
                        :href="`/mazmorras/${dungeon.key}`"
                        class="btn btn--gold btn--sm"
                        @click="play('click')"
                    >
                        {{ dungeon.cleared > 0 ? 'Volver al campamento' : 'Entrar al descenso' }}
                    </Link>
                </div>
            </div>
        </article>
    </div>
</template>
