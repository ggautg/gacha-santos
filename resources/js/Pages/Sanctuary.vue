<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SaintCard from '../Components/SaintCard.vue';
import PullReveal from '../Components/PullReveal.vue';
import { post } from '../lib/api';
import { formatCountdown, formatNumber, patchPlayer, usePlayer } from '../lib/player';
import { play } from '../lib/sfx';
import { toast } from '../lib/toast';

const props = defineProps({
    player: { type: Object, required: true },
    featured: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
    banner: { type: Array, default: () => [] },
    rarityProgress: { type: Array, default: () => [] },
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

const dungeonPercent = computed(() => {
    const total = props.player.dungeons?.total ?? 1;

    return Math.min(100, Math.round(((props.player.dungeons?.open ?? 0) / Math.max(1, total)) * 100));
});

const rarityPercent = (item) => (item.total > 0 ? Math.round((item.owned / item.total) * 100) : 0);

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
            toast.success('El santoral crece: has invocado nuevos santos.');
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
        only: ['featured', 'recent', 'banner', 'rarityProgress', 'player'],
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Santuario" />

    <header class="page-head">
        <div>
            <span class="page-kicker">Sanctorum · Invocación del amanecer</span>
            <h1 class="page-title">El cielo escucha tu plegaria</h1>
            <p class="page-lead">
                Invoca a los santos del santoral, reúne sus retratos pixelados y desciende por las
                mazmorras procedurales que asedian al mundo.
            </p>
        </div>
        <div class="btn-row">
            <Link href="/coleccion" class="btn btn--ghost btn--sm" @click="play('click')">Ver colección</Link>
            <Link href="/mazmorras" class="btn btn--sm" @click="play('click')">Bajar a las mazmorras</Link>
        </div>
    </header>

    <section class="hero">
        <div class="hero-stage">
            <div class="hero-art">
                <SaintCard :saint="featured" :interactive="false" />
            </div>

            <div class="hero-copy">
                <span class="eyebrow">Santo destacado · {{ featured.rarity_label }}</span>
                <h1>{{ featured.name }}</h1>
                <p class="epithet">{{ featured.epithet }}</p>
                <p class="quote">«{{ featured.quote }}»</p>

                <div class="hero-tags">
                    <span class="tag">{{ featured.role }}</span>
                    <span class="tag">{{ featured.region }}</span>
                    <span class="tag">Fiesta · {{ featured.feast_day }}</span>
                    <span v-if="featured.owned" class="tag">En tu santoral · Nv. {{ featured.owned.level }}</span>
                </div>

                <div class="hero-banner">
                    <span class="hero-banner-label">Milagrosos del santoral</span>
                    <div class="banner-row">
                        <Link
                            v-for="saint in banner"
                            :key="saint.id"
                            href="/coleccion"
                            class="banner-thumb"
                            :class="{ 'is-locked': !saint.owned }"
                            :style="{ '--rarity': saint.rarity_color }"
                            :aria-label="saint.owned ? `Ver ${saint.name} en la colección` : `${saint.name}: aún sin invocar`"
                            :title="saint.owned ? saint.name : `${saint.name} (sin invocar)`"
                            @click="play('click')"
                        >
                            <img :src="saint.art" :alt="saint.name" draggable="false" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="gacha-panel">
            <div>
                <span class="eyebrow">Invocar</span>
                <h2>Trae luz a tu santoral</h2>
            </div>

            <div class="gacha-offer">
                <button type="button" class="offer-btn" :class="{ 'offer-btn--gold': freeReady }" :disabled="freeDisabled" @click="summon(1, true)">
                    <span class="offer-label">
                        <span class="offer-title">Invocación gratuita</span>
                        <span class="offer-sub">Cada {{ costs.free_hours }} h el cielo regala una tirada</span>
                    </span>
                    <span class="offer-price">{{ freeLabel }}</span>
                </button>

                <button type="button" class="offer-btn" :disabled="singleDisabled" @click="summon(1)">
                    <span class="offer-label">
                        <span class="offer-title">Una invocación</span>
                        <span class="offer-sub">Un santo responde a tu llamada</span>
                    </span>
                    <span class="offer-price">
                        <span class="coin" aria-hidden="true"></span>{{ costs.single }}
                    </span>
                </button>

                <button type="button" class="offer-btn offer-btn--gold" :disabled="tenDisabled" @click="summon(10)">
                    <span class="offer-label">
                        <span class="offer-title">Diez invocaciones</span>
                        <span class="offer-sub">Garantiza al menos un 4★ o superior</span>
                    </span>
                    <span class="offer-price">
                        <span class="coin" aria-hidden="true"></span>{{ costs.ten }}
                    </span>
                </button>
            </div>

            <div class="pity-box">
                <div class="pity-head">
                    <span>Pity 5★</span>
                    <strong>{{ store.pity }} / {{ pityMax }}</strong>
                </div>
                <div class="meter meter--violet">
                    <span :style="{ width: `${pityPercent}%` }"></span>
                </div>
                <p class="panel-note" style="margin-top: 8px">
                    <template v-if="pityLeft === 0">La próxima invocación será Milagrosa.</template>
                    <template v-else>Faltan {{ pityLeft }} invocaciones para un 5★ asegurado.</template>
                </p>
            </div>

            <div>
                <div class="pity-head">
                    <span>Probabilidades</span>
                    <span>por tirada</span>
                </div>
                <div class="rates">
                    <div v-for="rate in rates" :key="rate.rarity" class="rate-row">
                        <span><span class="dot" :style="{ background: rate.color }"></span>{{ rate.label }}</span>
                        <strong>{{ rate.chance }}%</strong>
                    </div>
                </div>
            </div>

            <p class="panel-note">
                Los repetidos se convierten en Gracia extra. Tu equipo se completa solo con las
                primeras cartas nuevas.
            </p>
        </div>
    </section>

    <section v-if="recent.length" class="section">
        <div class="section-head">
            <h2 class="section-title">Últimas invocaciones</h2>
            <Link href="/coleccion" class="section-link" @click="play('click')">Ver el santoral completo →</Link>
        </div>

        <div class="panel">
            <div class="panel-body">
                <div class="recent-row">
                    <div
                        v-for="pull in recent"
                        :key="pull.id"
                        class="recent-item"
                        :class="{ 'is-new': pull.is_new }"
                        :style="{ '--rarity': pull.rarity_color }"
                        :title="`${pull.name} · ${pull.at}`"
                    >
                        <img :src="pull.art" :alt="pull.name" draggable="false" />
                        <div class="recent-meta">
                            <span>{{ pull.is_new ? 'NUEVO' : 'REP.' }}</span>
                            <span>{{ pull.at }}</span>
                        </div>
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
                            <div class="meter meter--thin">
                                <span :style="{ width: `${Math.round((player.owned / Math.max(1, player.total)) * 100)}%` }"></span>
                            </div>
                            <span class="stat-value">{{ player.owned }}/{{ player.total }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Mazmorras abiertas</span>
                            <div class="meter meter--thin meter--violet">
                                <span :style="{ width: `${dungeonPercent}%` }"></span>
                            </div>
                            <span class="stat-value">{{ player.dungeons?.open ?? 0 }}/{{ player.dungeons?.total ?? 3 }}</span>
                        </div>
                    </div>

                    <div class="modal-tags" style="margin-top: 12px">
                        <span class="tag">Piso más hondo <strong>{{ player.dungeons?.deepest ?? 0 }}</strong></span>
                        <span class="tag">Pisos limpios <strong>{{ player.dungeons?.cleared ?? 0 }}</strong></span>
                        <span class="tag">Victorias <strong>{{ store.wins }}</strong></span>
                        <span class="tag">Derrotas <strong>{{ store.losses }}</strong></span>
                        <span class="tag">Gracia <strong>{{ formatNumber(store.grace) }}</strong></span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Progreso por rareza</h2>
                </div>
                <div class="panel-body">
                    <div class="progress-list">
                        <div v-for="item in rarityProgress" :key="item.rarity" class="progress-item">
                            <span class="label">
                                <span class="dot" :style="{ background: item.color }"></span>
                                {{ item.label }}
                            </span>
                            <div class="meter meter--thin">
                                <span :style="{ width: `${rarityPercent(item)}%` }"></span>
                            </div>
                            <span class="count">{{ item.owned }}/{{ item.total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Consejo del claustro</h2>
                </div>
                <div class="panel-body">
                    <p class="bio">
                        Cada santo tiene una habilidad: curación, escudo, golpes de fe o fervor que
                        aumenta el ataque del equipo. Combina tres perfiles distintos en el campamento
                        antes de bajar a la mazmorra.
                    </p>
                    <div class="divider"></div>
                    <p class="panel-note">
                        La Gracia se gana en los descensos. Iluminar a un santo cuesta
                        <strong class="gold">{{ costs.level }}</strong> por nivel y mejora todas sus estadísticas.
                    </p>
                    <Link href="/mazmorras" class="btn btn--ghost btn--sm btn--block" style="margin-top: 12px" @click="play('click')">
                        Preparar el descenso
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <PullReveal v-if="revealOpen" :cards="cards" :free="freePullUsed" @close="closeReveal" />
</template>
