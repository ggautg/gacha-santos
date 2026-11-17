<script setup>
import { ref } from 'vue';
import { useDialogFocus } from '../lib/dialog';

defineProps({
    message: { type: String, required: true },
});

const emit = defineEmits(['confirm', 'cancel']);

const dialog = ref(null);
const cancelButton = ref(null);

useDialogFocus(dialog, cancelButton, () => emit('cancel'));
</script>

<template>
    <Teleport to="body">
        <div ref="dialog" class="overlay" role="alertdialog" aria-modal="true" aria-label="Confirmar acción" @click.self="emit('cancel')">
            <div class="modal" style="--rarity: var(--danger)">
                <h2 class="modal-name">Confirma la acción</h2>
                <p class="modal-note">{{ message }}</p>

                <div class="btn-row modal-confirm-actions">
                    <button ref="cancelButton" type="button" class="btn btn--ghost" @click="emit('cancel')">Cancelar</button>
                    <button type="button" class="btn btn--danger" @click="emit('confirm')">Confirmar</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
