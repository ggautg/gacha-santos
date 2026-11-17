<script setup>
defineProps({
    kicker: { type: String, required: true },
    title: { type: String, required: true },
    lead: { type: String, default: '' },
});
</script>

<template>
    <header class="page-head">
        <div>
            <span class="page-kicker">{{ kicker }}</span>
            <h1 class="page-title">{{ title }}</h1>
            <p v-if="lead" class="page-lead">{{ lead }}</p>
        </div>
        <slot />
    </header>
</template>
