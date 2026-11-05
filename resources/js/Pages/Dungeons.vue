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

    <div class="mz-hub">
        <header class="mz-hub-head">
            <div>
                <span class="mz-kicker">Sanctorum · Descensos procedurales</span>
                <h1 class="mz-hub-title">Elige tu descenso</h1>
                <p class="mz-hub-lead">
                    Cada mazmorra se genera al azar. Entra, elige un camino y mira a tus santos abrirse paso
                    hasta el jefe del piso.
                </p>
            </div>

            <div class="mz-stats">
                <span class="mz-stat">Mazmorras <strong>{{ dungeons.length }}</strong></span>
                <span class="mz-stat">Piso más hondo <strong>{{ deepest }}</strong></span>
                <span class="mz-stat mz-stat--gold">
                    <span class="coin" aria-hidden="true"></span>
                    <strong>{{ player.grace }}</strong>
                </span>
            </div>
        </header>

        <div class="mz-dungeons">
            <article
                v-for="dungeon in dungeons"
                :key="dungeon.key"
                class="mz-dungeon"
                :class="{ 'is-sealed': !dungeon.unlocked }"
                :data-theme="dungeon.theme"
                :style="{ '--mz-accent': dungeon.accent, '--mz-accent2': dungeon.accent2 }"
            >
                <div class="mz-dungeon-banner">
                    <span class="mz-dungeon-kicker">{{ dungeon.unlocked ? 'Abierta' : 'Sellada' }}</span>
                    <h2 class="mz-dungeon-name">{{ dungeon.name }}</h2>
                    <p class="mz-dungeon-sub">{{ dungeon.subtitle }}</p>
                    <span v-if="!dungeon.unlocked" class="mz-dungeon-seal" aria-hidden="true">✕</span>
                </div>

                <div class="mz-dungeon-body">
                    <div class="mz-dungeon-next">
                        <template v-if="dungeon.unlocked">
                            <span class="mz-dungeon-portrait">
                                <img
                                    v-if="dungeon.next?.sprite"
                                    :src="dungeon.next.sprite"
                                    :alt="dungeon.next.name"
                                    draggable="false"
                                />
                                <span v-else aria-hidden="true">{{ nodeGlyph(dungeon.next?.kind) }}</span>
                            </span>
                            <div class="mz-dungeon-next-copy">
                                <span class="mz-kicker">{{ nodeLabel(dungeon.next?.kind) }} · Piso {{ dungeon.next?.floor ?? dungeon.run_floor }}</span>
                                <strong>{{ dungeon.next?.name ?? 'La puerta aguarda' }}</strong>
                                <span class="mz-muted">{{ dungeon.next?.subtitle }}</span>
                            </div>
                        </template>

                        <template v-else>
                            <span class="mz-dungeon-portrait is-locked" aria-hidden="true">?</span>
                            <div class="mz-dungeon-next-copy">
                                <span class="mz-kicker">Sellada</span>
                                <strong>{{ dungeon.unlock_text }}</strong>
                            </div>
                        </template>
                    </div>

                    <div class="mz-progress">
                        <span :style="{ width: `${Math.min(100, dungeon.best_floor * 10)}%` }"></span>
                    </div>
                    <div class="mz-progress-note">
                        <span>Mejor piso · {{ dungeon.best_floor }}</span>
                        <span>{{ dungeon.cleared }} limpios</span>
                    </div>

                    <Link
                        v-if="dungeon.unlocked"
                        :href="`/mazmorras/${dungeon.key}`"
                        class="mz-btn mz-btn--block"
                        @click="play('click')"
                    >
                        {{ dungeon.cleared > 0 ? 'Volver al campamento' : 'Entrar al descenso' }}
                    </Link>
                </div>
            </article>
        </div>

        <p v-if="!dungeons.length" class="mz-empty">
            <strong>Ninguna mazmorra está abierta por ahora.</strong>
        </p>
    </div>
</template>
