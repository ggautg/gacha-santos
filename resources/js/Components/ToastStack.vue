<script setup>
import { computed } from 'vue';
import { dismissToast, useToasts } from '../lib/toast';

const toasts = useToasts();
const hasErrors = computed(() => toasts.some((item) => item.type === 'error'));
</script>

<template>
    <Teleport to="body">
        <div
            class="toast-stack"
            role="region"
            aria-label="Avisos"
            :aria-live="hasErrors ? 'assertive' : 'polite'"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                class="toast"
                :class="`is-${item.type}`"
            >
                <span class="toast-message">{{ item.message }}</span>
                <button
                    type="button"
                    class="toast-close"
                    :aria-label="`Cerrar aviso: ${item.message}`"
                    @click="dismissToast(item.id)"
                >
                    ✕
                </button>
            </div>
        </div>
    </Teleport>
</template>
