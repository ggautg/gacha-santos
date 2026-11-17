<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, required: true },
    variant: { type: [String, Array], default: '' },
});

const percent = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const variants = computed(() =>
    (Array.isArray(props.variant) ? props.variant : [props.variant])
        .filter(Boolean)
        .map((name) => `meter--${name}`),
);
</script>

<template>
    <div
        class="meter"
        :class="variants"
        role="progressbar"
        :aria-valuenow="percent"
        aria-valuemin="0"
        aria-valuemax="100"
    >
        <span :style="{ width: `${percent}%` }"></span>
    </div>
</template>
