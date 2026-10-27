<script setup>
import { dismissToast, useToasts } from '../lib/toast';

const toasts = useToasts();
</script>

<template>
    <Teleport to="body">
        <div class="toast-stack" role="status" aria-live="polite">
            <div
                v-for="item in toasts"
                :key="item.id"
                class="toast"
                :class="`is-${item.type}`"
                @click="dismissToast(item.id)"
            >
                {{ item.message }}
            </div>
        </div>
    </Teleport>
</template>
