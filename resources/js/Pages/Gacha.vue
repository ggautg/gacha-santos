<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PullReveal from '../Components/PullReveal.vue';
import { post } from '../lib/api';
import {
    formatCountdown,
    formatNumber,
    patchPlayer,
    usePlayer,
} from '../lib/player';
import { play } from '../lib/sfx';
import { toast } from '../lib/toast';

const props = defineProps({
    player: { type: Object, required: true },
    featured: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
    banner: { type: Array, default: () => [] },
    costs: { type: Object, required: true },
    rates: { type: Array, default: () => [] },
});

const store = usePlayer();

const loading = ref(null);
const cards = ref([]);
const revealOpen = ref(false);
const freePullUsed = ref(false);

const pityMax = computed(() => props.player.pity_max ?? 60);
const pityPercent = computed(() => Math.min(100, Math.round((store.pity / pityMax.value) * 100)));
const pityLeft = computed(() => Math.max(0, pityMax.value - store.pity));

const freeReady = computed(() => store.free_ready);
const freeLabel = computed(() => (freeReady.value ? 'GRATIS' : formatCountdown(store.free_in)));

const singleDisabled = computed(() => loading.value !== null || store.grace < props.costs.single);
const tenDisabled = computed(() => loading.value !== null || store.grace < props.costs.ten);
const freeDisabled = computed(() => loading.value !== null || !freeReady.value);

async function summon(count, free = false) {
    if (loading.value !== null) {
        return;
    }

    loading.value = free ? 'free' : count === 10 ? 'ten' : 'single';
    play('pull');

    try {
        const result = await post('/gacha', { count, free });

        patchPlayer({
            grace: result.grace,
            pity: result.pity,
            free_ready: result.free_ready,
            free_in: result.free_in,
        });

        cards.value = result.cards;
        freePullUsed.value = result.free;
        revealOpen.value = true;

        const best = Math.max(...result.cards.map((card) => card.rarity));
        play(best === 5 ? 'five' : best >= 4 ? 'rare' : 'common');

        if (result.cards.some((card) => card.is_new)) {
            toast.success('El santoral crece: tu oración ha revelado nuevos santos.');
        }
    } catch (error) {
        play('error');
        toast.error(error.firstError?.() ?? error.message);
    } finally {
        loading.value = null;
    }
}

function closeReveal() {
    revealOpen.value = false;
    cards.value = [];
    freePullUsed.value = false;

    router.reload({
        only: ['featured', 'recent', 'banner', 'player'],
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Orar" />

    <div class="home-layout gacha-layout">
        <div class="home-main">
            <section class="client-hero gacha-hero">
                <div
                    class="client-hero-art"
                    :style="{ backgroundImage: `url(${featured.art})` }"
                    aria-hidden="true"
                ></div>
                <div class="client-hero-shade" aria-hidden="true"></div>

                <div class="client-hero-copy gacha-hero-copy">
                    <span class="client-hero-kicker">Oración · {{ featured.rarity_label }}</span>
                    <h1>Trae luz a tu santoral</h1>
                    <p class="client-hero-epithet">{{ featured.name }}</p>
                    <p class="client-hero-quote">
                        Cada oración abre una página nueva del santoral. Los repetidos vuelven
                        como Puntos de Fe para seguir orando.
                    </p>

                    <div class="hero-tags">
                        <span class="tag">Puntos de Fe <strong>{{ formatNumber(store.grace) }}</strong></span>
                        <span class="tag">Santoral <strong>{{ player.owned }}/{{ player.total }}</strong></span>
                        <span class="tag">{{ freeReady ? 'Gratis lista' : `Gratis en ${formatCountdown(store.free_in)}` }}</span>
                    </div>
                </div>
            </section>

            <section class="client-block">
                <div class="client-block-head">
                    <div>
                        <span class="client-kicker">Oración</span>
                        <h2 class="client-block-title">Elige tu llamada</h2>
                    </div>
                    <span class="client-block-meta">
                        <span class="coin" aria-hidden="true"></span>
                        {{ formatNumber(store.grace) }}
                    </span>
                </div>

                <div class="summon-modes">
                    <button
                        type="button"
                        class="summon-card summon-card--free"
                        :class="{ 'is-ready': freeReady }"
                        :disabled="freeDisabled"
                        @click="summon(1, true)"
                    >
                        <span class="summon-card-tag">Gratis</span>
                        <span class="summon-card-title">Oración diaria</span>
                        <span class="summon-card-sub">Cada {{ costs.free_hours }} h el cielo regala una tirada</span>
                        <span class="summon-card-price">{{ freeLabel }}</span>
                    </button>

                    <button
                        type="button"
                        class="summon-card"
                        :disabled="singleDisabled"
                        @click="summon(1)"
                    >
                        <span class="summon-card-tag">Individual</span>
                        <span class="summon-card-title">Una oración</span>
                        <span class="summon-card-sub">Un santo responde a tu llamada</span>
                        <span class="summon-card-price">
                            <span class="coin" aria-hidden="true"></span>{{ costs.single }}
                        </span>
                    </button>

                    <button
                        type="button"
                        class="summon-card summon-card--gold"
                        :disabled="tenDisabled"
                        @click="summon(10)"
                    >
                        <span class="summon-card-tag">Destacado</span>
                        <span class="summon-card-title">Diez oraciones</span>
                        <span class="summon-card-sub">Garantiza al menos un 4★ o superior</span>
                        <span class="summon-card-price">
                            <span class="coin" aria-hidden="true"></span>{{ costs.ten }}
                        </span>
                    </button>
                </div>

                <div class="summon-foot">
                    <div class="summon-pity">
                        <div class="pity-head">
                            <span>Pity 5★</span>
                            <strong>{{ store.pity }} / {{ pityMax }}</strong>
                        </div>
                        <div class="meter meter--violet">
                            <span :style="{ width: `${pityPercent}%` }"></span>
                        </div>
                        <p class="panel-note">
                            <template v-if="pityLeft === 0">La próxima oración será Milagrosa.</template>
                            <template v-else>Faltan {{ pityLeft }} oraciones para un 5★ asegurado.</template>
                        </p>
                    </div>

                    <div class="summon-rates">
                        <span v-for="rate in rates" :key="rate.rarity" class="rate-pill">
                            <span class="dot" :style="{ background: rate.color }"></span>
                            {{ rate.label }} · {{ rate.chance }}%
                        </span>
                    </div>
                </div>

                <p class="panel-note">
                    Los repetidos se convierten en Puntos de Fe extra. Tu equipo se completa solo con las
                    primeras cartas nuevas.
                </p>
            </section>

            <section class="client-block">
                <div class="client-block-head">
                    <div>
                        <span class="client-kicker">Milagrosos</span>
                        <h2 class="client-block-title">Los cinco estrellas</h2>
                    </div>
                    <span class="client-block-meta">{{ banner.filter((saint) => saint.owned).length }}/{{ banner.length }}</span>
                </div>

                <div class="gacha-showcase">
                    <Link
                        v-for="saint in banner"
                        :key="saint.id"
                        href="/coleccion"
                        class="gacha-showcase-card"
                        :class="{ 'is-locked': !saint.owned }"
                        :style="{ '--rarity': saint.rarity_color }"
                        :title="saint.owned ? saint.name : `${saint.name} (sin revelar)`"
                        @click="play('click')"
                    >
                        <img :src="saint.art" :alt="saint.name" draggable="false" />
                        <span class="gacha-showcase-name">{{ saint.owned ? saint.name : 'Retrato velado' }}</span>
                    </Link>
                </div>
            </section>
        </div>

        <aside class="home-aside">
            <section class="client-module">
                <header class="client-module-head">
                    <span class="client-kicker">Misiones</span>
                    <span class="client-module-tag">Diaria</span>
                </header>
                <div class="client-module-body">
                    <div class="mission-row">
                        <span class="mission-orb" aria-hidden="true">✦</span>
                        <div class="mission-copy">
                            <strong>Oración gratuita</strong>
                            <p>{{ freeReady ? 'Lista para reclamar' : `Se renueva en ${formatCountdown(store.free_in)}` }}</p>
                        </div>
                        <button
                            type="button"
                            class="mission-btn"
                            :disabled="freeDisabled"
                            @click="summon(1, true)"
                        >
                            {{ freeReady ? 'Reclamar' : '···' }}
                        </button>
                    </div>

                    <div class="mission-row">
                        <span class="mission-orb mission-orb--violet" aria-hidden="true">★</span>
                        <div class="mission-copy">
                            <strong>Pity 5★</strong>
                            <p>{{ store.pity }} / {{ pityMax }} · faltan {{ pityLeft }}</p>
                        </div>
                        <span class="mission-count">{{ pityPercent }}%</span>
                    </div>
                </div>
            </section>

            <section class="client-module">
                <header class="client-module-head">
                    <span class="client-kicker">Actividad</span>
                    <span class="client-module-tag">{{ recent.length }} tiradas</span>
                </header>
                <div class="client-module-body">
                    <div v-if="recent.length" class="activity-list">
                        <div
                            v-for="pull in recent.slice(0, 7)"
                            :key="pull.id"
                            class="activity-row"
                            :class="{ 'is-new': pull.is_new }"
                            :style="{ '--rarity': pull.rarity_color }"
                        >
                            <img :src="pull.art" :alt="pull.name" draggable="false" />
                            <div class="activity-copy">
                                <strong>{{ pull.name }}</strong>
                                <span>{{ pull.is_new ? 'Nuevo' : 'Repetido' }} · {{ pull.at }}</span>
                            </div>
                            <span class="activity-rarity">{{ pull.rarity }}★</span>
                        </div>
                    </div>
                    <p v-else class="panel-note">
                        Aún no has orado. La primera tirada es gratuita.
                    </p>

                    <Link href="/coleccion" class="btn btn--ghost btn--sm btn--block" @click="play('click')">
                        Ver colección
                    </Link>
                </div>
            </section>

            <section class="client-module">
                <header class="client-module-head">
                    <span class="client-kicker">Santoral</span>
                    <span class="client-module-tag">{{ player.owned }}/{{ player.total }}</span>
                </header>
                <div class="client-module-body">
                    <p class="panel-note">
                        Iluminar a un santo con Puntos de Fe mejora todas sus estadísticas y su poder en
                        las mazmorras.
                    </p>
                    <Link href="/coleccion" class="btn btn--gold btn--sm btn--block" @click="play('click')">
                        Abrir santoral
                    </Link>
                </div>
            </section>
        </aside>
    </div>

    <PullReveal v-if="revealOpen" :cards="cards" :free="freePullUsed" @close="closeReveal" />
</template>
