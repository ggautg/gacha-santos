<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PullReveal from '../Components/PullReveal.vue';
import { post } from '../lib/api';
import {
    formatNumber,
    patchPlayer,
    usePlayer,
} from '../lib/player';
import { play } from '../lib/sfx';
import { toast } from '../lib/toast';

const props = defineProps({
    player: { type: Object, required: true },
    costs: { type: Object, required: true },
    rates: { type: Array, default: () => [] },
});

const store = usePlayer();

const loading = ref(null);
const cards = ref([]);
const revealOpen = ref(false);

const pityMax = computed(() => props.player.pity_max ?? 60);
const pityPercent = computed(() => Math.min(100, Math.round((store.pity / pityMax.value) * 100)));
const pityLeft = computed(() => Math.max(0, pityMax.value - store.pity));

const singleDisabled = computed(() => loading.value !== null || store.grace < props.costs.single);
const tenDisabled = computed(() => loading.value !== null || store.grace < props.costs.ten);

async function summon(count) {
    if (loading.value !== null) {
        return;
    }

    loading.value = count === 10 ? 'ten' : 'single';
    play('pull');

    try {
        const result = await post('/gacha', { count });

        patchPlayer({
            grace: result.grace,
            pity: result.pity,
        });

        cards.value = result.cards;
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

    router.reload({
        only: ['player'],
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <Head title="Orar" />

    <section class="orar">
        <header class="orar-head">
            <span class="orar-kicker">Invocación</span>
            <h1 class="orar-title">Orar</h1>
            <span class="orar-balance">
                <span class="coin" aria-hidden="true"></span>
                <strong>{{ formatNumber(store.grace) }}</strong>
                <span>Puntos de Fe</span>
            </span>
        </header>

        <div class="orar-modes">
            <button
                type="button"
                class="orar-mode"
                :disabled="singleDisabled"
                @click="summon(1)"
            >
                <span class="orar-mode-face">
                    <span class="orar-mode-tag">Individual</span>
                    <span class="orar-mode-title">Una oración</span>
                    <span class="orar-mode-sub">Un santo responde a tu llamada</span>
                    <span class="orar-mode-price">
                        <span class="coin" aria-hidden="true"></span>{{ costs.single }}
                    </span>
                </span>
            </button>

            <button
                type="button"
                class="orar-mode orar-mode--featured"
                :disabled="tenDisabled"
                @click="summon(10)"
            >
                <span class="orar-mode-face">
                    <span class="orar-mode-tag">Destacado</span>
                    <span class="orar-mode-title">Diez oraciones</span>
                    <span class="orar-mode-sub">Garantiza al menos un 4★ o superior</span>
                    <span class="orar-mode-price">
                        <span class="coin" aria-hidden="true"></span>{{ costs.ten }}
                    </span>
                </span>
            </button>
        </div>

        <div class="orar-pity">
            <div class="orar-pity-head">
                <span>Pity 5★</span>
                <strong>{{ store.pity }} / {{ pityMax }}</strong>
            </div>
            <div class="orar-pity-bar">
                <span :style="{ width: `${pityPercent}%` }"></span>
            </div>
            <p class="orar-pity-note">
                <template v-if="pityLeft === 0">La próxima oración será Milagrosa.</template>
                <template v-else>Faltan {{ pityLeft }} oraciones para un 5★ asegurado.</template>
            </p>
        </div>

        <div class="orar-rates">
            <span v-for="rate in rates" :key="rate.rarity" class="orar-rate">
                <span class="orar-rate-dot" :style="{ background: rate.color }" aria-hidden="true"></span>
                {{ rate.label }} · {{ rate.chance }}%
            </span>
        </div>
    </section>

    <PullReveal v-if="revealOpen" :cards="cards" @close="closeReveal" />
</template>
