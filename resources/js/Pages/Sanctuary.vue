<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ProgressMeter from '../Components/ProgressMeter.vue';
import SaintCard from '../Components/SaintCard.vue';
import { formatNumber } from '../lib/player';
import { play } from '../lib/sfx';

const props = defineProps({
    player: { type: Object, required: true },
    featured: { type: Object, default: null },
    banner: { type: Array, default: () => [] },
    rarityProgress: { type: Array, default: () => [] },
    costs: { type: Object, required: true },
});

const featured = computed(() => props.featured ?? {});

const saintPercent = computed(() =>
    Math.round(((props.player.owned ?? 0) / Math.max(1, props.player.total ?? 0)) * 100),
);

const dungeonPercent = computed(() =>
    Math.round(((props.player.dungeons?.open ?? 0) / Math.max(1, props.player.dungeons?.total ?? 1)) * 100),
);

function rarityPercent(item) {
    return item.total > 0 ? Math.round((item.owned / item.total) * 100) : 0;
}
</script>

<template>
    <Head title="Santuario" />

    <PageHeader
        kicker="Sanctorum · Santuario"
        :title="`Bienvenido, ${player.name || 'Peregrino'}`"
        lead="Reúne santos, ilumínalos con Puntos de Fe y desciende a las mazmorras que asedian al mundo."
    >
        <div class="btn-row">
            <Link href="/coleccion" class="btn btn--ghost btn--sm" @click="play('click')">Ver colección</Link>
            <Link href="/orar" class="btn btn--sm" @click="play('click')">Ir a orar</Link>
        </div>
    </PageHeader>

    <section v-if="featured.name" class="hero">
        <div class="hero-stage">
            <div class="hero-art">
                <SaintCard :saint="featured" :interactive="false" />
            </div>

            <div class="hero-copy">
                <span class="eyebrow">Santo destacado · {{ featured.rarity_label }}</span>
                <h1>{{ featured.name }}</h1>
                <p class="epithet">{{ featured.epithet }}</p>
                <p v-if="featured.quote" class="quote">«{{ featured.quote }}»</p>

                <div class="hero-tags">
                    <span class="tag">{{ featured.role }}</span>
                    <span class="tag">{{ featured.region }}</span>
                    <span v-if="featured.owned" class="tag">En tu santoral · Nv. {{ featured.owned.level }}</span>
                </div>

                <div v-if="banner.length" class="hero-banner">
                    <span class="hero-banner-label">Milagrosos del santoral</span>
                    <div class="banner-row">
                        <Link
                            v-for="saint in banner"
                            :key="saint.id"
                            href="/coleccion"
                            class="banner-thumb"
                            :class="{ 'is-locked': !saint.owned }"
                            :style="{ '--rarity': saint.rarity_color }"
                            :aria-label="saint.owned ? `Ver ${saint.name} en la colección` : `${saint.name}, aún sin invocar`"
                            @click="play('click')"
                        >
                            <img :src="saint.art" :alt="saint.name" draggable="false" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="stats-grid">
            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Tu peregrinación</h2>
                </div>
                <div class="panel-body">
                    <div class="stat-list">
                        <div class="stat-row">
                            <span class="stat-label">Santoral</span>
                            <ProgressMeter :value="saintPercent" variant="thin" />
                            <span class="stat-value">{{ player.owned }}/{{ player.total }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Mazmorras abiertas</span>
                            <ProgressMeter :value="dungeonPercent" :variant="['thin', 'violet']" />
                            <span class="stat-value">{{ player.dungeons?.open ?? 0 }}/{{ player.dungeons?.total ?? 0 }}</span>
                        </div>
                    </div>

                    <div class="modal-tags" style="margin-top: 12px">
                        <span class="tag">Piso más hondo <strong>{{ player.dungeons?.deepest ?? 0 }}</strong></span>
                        <span class="tag">Pisos limpios <strong>{{ player.dungeons?.cleared ?? 0 }}</strong></span>
                        <span class="tag">Victorias <strong>{{ player.wins }}</strong></span>
                        <span class="tag">Derrotas <strong>{{ player.losses }}</strong></span>
                        <span class="tag">
                            Puntos de Fe <strong>{{ formatNumber(player.grace) }}</strong>
                        </span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Progreso por rareza</h2>
                </div>
                <div class="panel-body">
                    <div v-if="rarityProgress.length" class="progress-list">
                        <div v-for="item in rarityProgress" :key="item.rarity" class="progress-item">
                            <span class="label">
                                <span class="dot" :style="{ background: item.color }"></span>
                                {{ item.label }}
                            </span>
                            <ProgressMeter :value="rarityPercent(item)" variant="thin" />
                            <span class="count">{{ item.owned }}/{{ item.total }}</span>
                        </div>
                    </div>
                    <p v-else class="panel-note">Todavía no hay santos registrados en el santoral.</p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Tu siguiente paso</h2>
                </div>
                <div class="panel-body">
                    <p class="bio">
                        Cada santo tiene una habilidad: curación, escudo, golpes de fe o fervor que
                        aumenta el ataque del equipo. Combina tres perfiles en el campamento antes
                        de bajar a una mazmorra.
                    </p>
                    <div class="divider"></div>
                    <p class="panel-note">
                        Iluminar a un santo cuesta
                        <strong class="gold">{{ formatNumber(costs.level) }}</strong>
                        Puntos de Fe por nivel y mejora todas sus estadísticas.
                    </p>
                    <Link href="/mazmorras" class="btn btn--ghost btn--sm btn--block" style="margin-top: 12px" @click="play('click')">
                        Preparar el descenso
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
