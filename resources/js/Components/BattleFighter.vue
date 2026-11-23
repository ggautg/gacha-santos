<script setup>
import { computed } from 'vue';

/**
 * Combatiente de la arena. Centraliza el cálculo de barras y estados que
 * antes se repetía en los bloques de aliados y enemigos de DungeonRun.
 * El estado de animación (activo, golpeado, caído) lo calcula la página.
 */
const props = defineProps({
    unit: { type: Object, required: true },
    state: { type: Object, default: () => ({}) },
    floatValue: { type: Object, default: null },
    hpValue: { type: Number, required: true },
    shieldValue: { type: Number, default: 0 },
    levelLabel: { type: String, required: true },
    foe: { type: Boolean, default: false },
});

const maxHp = computed(() => Math.max(1, props.unit.maxHp ?? props.unit.hp ?? 1));
const hpPercent = computed(() => Math.round((Math.max(0, props.hpValue) / maxHp.value) * 100));
const shieldPercent = computed(() => Math.min(100, Math.round((props.shieldValue / maxHp.value) * 100)));
const hpClass = computed(() => {
    if (hpPercent.value <= 25) {
        return 'is-low';
    }

    if (hpPercent.value <= 55) {
        return 'is-mid';
    }

    return '';
});
</script>

<template>
    <div class="mz-fighter" :class="[state, { 'mz-fighter--foe': foe }]">
        <span
            v-if="floatValue"
            :key="floatValue.key"
            class="mz-float"
            :class="{
                'is-heal': floatValue.type === 'heal',
                'is-shield': floatValue.type === 'shield',
                'is-crit': floatValue.type === 'crit',
            }"
        >
            {{ floatValue.text }}
        </span>
        <img :src="unit.sprite" :alt="unit.name" draggable="false" />
        <span class="mz-fighter-name">{{ unit.short }}</span>
        <span class="mz-fighter-lv">{{ levelLabel }}</span>
        <div
            class="mz-hp"
            :class="hpClass"
            role="progressbar"
            :aria-label="`Puntos de vida de ${unit.name}`"
            :aria-valuenow="hpPercent"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <span class="mz-hp-bar" :style="{ width: `${hpPercent}%` }"></span>
            <span v-if="shieldValue" class="mz-shield" :style="{ width: `${shieldPercent}%` }"></span>
        </div>
    </div>
</template>
