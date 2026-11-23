<script setup>
import { formatNumber } from '../lib/player';

const props = defineProps({
    value: { type: Number, required: true },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span class="grace-chip" :class="{ 'grace-chip--sm': props.compact }" title="Puntos de Fe">
        <span class="coin" aria-hidden="true"></span>
        {{ formatNumber(props.value) }}
        <span class="sr-only">Puntos de Fe</span>
    </span>
</template>
