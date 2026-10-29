<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { play } from '../lib/sfx';

defineProps({
    dungeons: { type: Array, default: () => [] },
    deepest: { type: Number, default: 0 },
    player: { type: Object, required: true },
});
</script>

<template>
    <Head title="Mazmorras" />

    <header class="page-head">
        <div>
            <span class="page-kicker">Sanctorum · Descensos procedurales</span>
            <h1 class="page-title">Mazmorras que nunca se repiten</h1>
            <p class="page-lead">
                Cada entrada abre un descenso nuevo: los jefes se escogen al azar, los pisos no
                tienen final y cada mazmorra impone su propia paleta y tipografía a toda la web.
                Prepara tu equipo en el campamento antes de cruzar la puerta.
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
                <div class="dungeon-theme-line">
                    <span class="dungeon-kicker">Descenso procedural</span>
                    <span class="dungeon-font">{{ dungeon.font_name }}</span>
                </div>
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
                                v-if="dungeon.next"
                                :src="dungeon.next.sprite"
                                :alt="dungeon.next.name"
                                draggable="false"
                            />
                            <span v-else aria-hidden="true">✦</span>
                        </div>
                        <div class="dungeon-next-copy">
                            <span class="eyebrow">Piso {{ dungeon.next?.floor ?? dungeon.run_floor }} · jefe al azar</span>
                            <strong>{{ dungeon.next?.name ?? 'La puerta aguarda' }}</strong>
                            <span class="panel-note">{{ dungeon.next?.subtitle }}</span>
                        </div>
                    </div>

                    <div v-else class="dungeon-next-inner">
                        <div class="dungeon-portrait dungeon-portrait--locked" aria-hidden="true">?</div>
                        <div class="dungeon-next-copy">
                            <span class="eyebrow">Sellada</span>
                            <strong>{{ dungeon.unlock_text }}</strong>
                            <span class="panel-note">Aún no puedes cruzar esta puerta.</span>
                        </div>
                    </div>
                </div>

                <div v-if="dungeon.unlocked && dungeon.next" class="dungeon-next-stats">
                    <span class="tag">Vida <strong>{{ dungeon.next.hp }}</strong></span>
                    <span class="tag">Ataque <strong>{{ dungeon.next.atk }}</strong></span>
                    <span class="tag">Puntos de Fe <strong>{{ dungeon.next.grace }}</strong></span>
                    <span class="tag">XP <strong>{{ dungeon.next.xp }}</strong></span>
                </div>

                <div class="meter" :class="dungeon.unlocked ? '' : 'meter--violet'">
                    <span :style="{ width: `${Math.min(100, dungeon.best_floor * 10)}%` }"></span>
                </div>
                <div class="dungeon-progress-note">
                    <span>Mejor piso · {{ dungeon.best_floor }}</span>
                    <span>Descenso actual · {{ dungeon.run_floor }}</span>
                    <span>{{ dungeon.cleared }} limpios</span>
                </div>

                <p v-if="dungeon.tagline" class="dungeon-tagline">«{{ dungeon.tagline }}»</p>

                <div class="btn-row">
                    <Link
                        v-if="dungeon.unlocked"
                        :href="`/mazmorras/${dungeon.key}`"
                        class="btn btn--gold btn--sm"
                        @click="play('click')"
                    >
                        {{ dungeon.cleared > 0 ? 'Volver al campamento' : 'Acampar y descender' }}
                    </Link>
                    <span v-else class="panel-note">{{ dungeon.unlock_text }}</span>
                </div>
            </div>
        </article>
    </div>

    <div class="panel" style="margin-top: 18px">
        <div class="panel-head">
            <h2 class="panel-title">Cómo funciona el descenso</h2>
        </div>
        <div class="panel-body">
            <p class="panel-note">
                No hay una escalera fija: cada descenso tiene una semilla propia y el jefe de cada
                piso se elige al azar entre el elenco de la mazmorra. Los pisos no terminan y sus
                criaturas ganan vida, ataque y botín con la profundidad. Elige hasta tres santos en
                el campamento antes de presentar batalla; si caes, puedes reintentar el mismo piso o
                abrir una grieta nueva para cambiar el elenco. Los combates no cuestan Puntos de Fe.
            </p>
        </div>
    </div>
</template>
