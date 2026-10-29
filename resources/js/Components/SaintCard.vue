<script setup>
import { computed } from 'vue';

const props = defineProps({
    saint: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    locked: { type: Boolean, default: false },
    fresh: { type: Boolean, default: false },
    interactive: { type: Boolean, default: true },
});

const emit = defineEmits(['select']);

const mystery = computed(() => Boolean(props.saint.mystery));
const stars = computed(() =>
    mystery.value ? '· · ·' : '★'.repeat(props.saint.stars ?? props.saint.rarity ?? 1),
);
const rarity = computed(() => (mystery.value ? '???' : (props.saint.rarity_label ?? '')));
const style = computed(() => ({ '--rarity': props.saint.rarity_color ?? '#5a4a9a' }));

const activate = () => {
    if (props.interactive) {
        emit('select', props.saint);
    }
};
</script>

<template>
    <component
        :is="interactive ? 'button' : 'div'"
        :type="interactive ? 'button' : undefined"
        class="card"
        :class="{ 'is-locked': locked && !mystery, 'is-mystery': mystery, 'is-selected': selected }"
        :style="style"
        :aria-label="mystery ? 'Santo velado, identidad oculta' : locked ? `Santo sin revelar: ${saint.name}` : `Ver ${saint.name}`"
        :aria-pressed="interactive ? selected : undefined"
        @click="activate"
    >
        <span class="card-badge">{{ rarity }}</span>
        <span v-if="fresh" class="card-new">NUEVO</span>

        <div class="card-art">
            <img
                :src="saint.art"
                :alt="mystery ? 'Retrato velado' : locked ? 'Retrato oculto' : saint.name"
                draggable="false"
            />
        </div>

        <div class="card-info">
            <span class="card-name">{{ mystery ? 'Santo velado' : saint.name }}</span>
            <span class="card-stars" aria-hidden="true">{{ stars }}</span>
            <span class="card-meta">
                <span>{{ mystery ? 'Sin revelar' : locked ? 'Sin revelar' : saint.owned ? `Nv. ${saint.owned.level}` : saint.role ?? '' }}</span>
                <span v-if="mystery" class="card-chip">???</span>
                <span v-else-if="!locked && saint.owned" class="card-chip">{{ saint.owned.power }} PX</span>
                <span v-else class="card-chip">{{ saint.rarity }}★</span>
            </span>
        </div>

        <span v-if="selected" class="card-flag" aria-hidden="true">✓</span>
    </component>
</template>
