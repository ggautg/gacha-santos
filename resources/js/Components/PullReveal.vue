<script setup>
import { computed, ref } from 'vue';
import SaintCard from './SaintCard.vue';
import { useDialogFocus } from '../lib/dialog';
import { prefersReducedMotion } from '../lib/motion';

const props = defineProps({
    cards: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const best = computed(() => Math.max(...props.cards.map((card) => card.rarity), 1));
const freshCount = computed(() => props.cards.filter((card) => card.is_new).length);
const repeatedCount = computed(() => props.cards.length - freshCount.value);
const dialog = ref(null);
const closeButton = ref(null);
const reduceMotion = prefersReducedMotion();
const cardDelay = (index) => (reduceMotion ? undefined : `${index * 0.08}s`);

const glowByRarity = {
    5: 'rgba(242, 201, 76, 0.28)',
    4: 'rgba(242, 153, 74, 0.24)',
    3: 'rgba(176, 124, 240, 0.22)',
    2: 'rgba(91, 192, 235, 0.18)',
    1: 'rgba(154, 165, 177, 0.14)',
};

const glow = computed(() => glowByRarity[best.value] ?? glowByRarity[1]);

const title = computed(() => {
    if (best.value === 5) {
        return '¡MILAGROSO! UN SANTO DE CINCO ESTRELLAS';
    }

    if (best.value === 4) {
        return '¡UN SANTO LEGENDARIO RESPONDE!';
    }

    if (best.value === 3) {
        return 'El cielo responde a tu oración';
    }

    return 'Los santos escuchan tu plegaria';
});

const close = () => emit('close');

useDialogFocus(dialog, closeButton, close);
</script>

<template>
    <Teleport to="body">
        <div ref="dialog" class="reveal" :style="{ '--reveal-glow': glow }" role="dialog" aria-modal="true" aria-label="Resultado de la oración" @click.self="close">
            <div class="reveal-burst" aria-hidden="true"></div>

            <h2 class="reveal-title">{{ title }}</h2>

            <p class="reveal-note">
                {{ freshCount }} {{ freshCount === 1 ? 'nuevo' : 'nuevos' }} ·
                {{ repeatedCount }} {{ repeatedCount === 1 ? 'repetido' : 'repetidos' }}
            </p>

            <div class="reveal-grid">
                <div
                    v-for="(card, index) in cards"
                    :key="`${card.id}-${index}`"
                    class="reveal-card"
                    :class="{ 'is-five': card.rarity === 5, 'is-four': card.rarity === 4 }"
                    :style="{ animationDelay: cardDelay(index) }"
                >
                    <SaintCard :saint="card" :fresh="card.is_new" :interactive="false" />
                </div>
            </div>

            <div class="reveal-actions">
                <button ref="closeButton" type="button" class="btn btn--gold btn--lg" @click="close">Continuar</button>
            </div>
        </div>
    </Teleport>
</template>
