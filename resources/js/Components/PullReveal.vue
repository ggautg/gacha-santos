<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import SaintCard from './SaintCard.vue';

const props = defineProps({
    cards: { type: Array, required: true },
});

const emit = defineEmits(['close']);

const best = computed(() => Math.max(...props.cards.map((card) => card.rarity), 1));
const freshCount = computed(() => props.cards.filter((card) => card.is_new).length);
const repeatedCount = computed(() => props.cards.length - freshCount.value);
const dialog = ref(null);
const closeButton = ref(null);

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
        return '¡MILAGRO! UN SANTO DE CINCO ESTRELLAS';
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

let previousFocus = null;

function focusables() {
    return Array.from(
        dialog.value?.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])') ?? [],
    );
}

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const items = focusables();

    if (!items.length) {
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

onMounted(async () => {
    previousFocus = document.activeElement;
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKeydown);
    await nextTick();
    closeButton.value?.focus();
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
    previousFocus?.focus?.();
});
</script>

<template>
    <Teleport to="body">
        <div ref="dialog" class="reveal" :style="{ '--reveal-glow': glow }" role="dialog" aria-modal="true" aria-label="Resultado de la oración">
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
                    :style="{ animationDelay: `${index * 0.08}s` }"
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
