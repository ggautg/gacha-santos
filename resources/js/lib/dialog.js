import { nextTick, onBeforeUnmount, onMounted } from 'vue';

/**
 * Cierre compartido de los menús emergentes: clic fuera y Escape.
 * Escape devuelve el foco al botón que abrió el menú.
 */
export function useDismissable(isOpen, containerRef) {
    function onDocumentClick(event) {
        if (isOpen.value && containerRef.value && !containerRef.value.contains(event.target)) {
            isOpen.value = false;
        }
    }

    function onDocumentKey(event) {
        if (event.key !== 'Escape' || !isOpen.value) {
            return;
        }

        isOpen.value = false;
        nextTick(() => containerRef.value?.querySelector('button')?.focus());
    }

    onMounted(() => {
        document.addEventListener('click', onDocumentClick);
        document.addEventListener('keydown', onDocumentKey);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('click', onDocumentClick);
        document.removeEventListener('keydown', onDocumentKey);
    });
}

/**
 * Foco inicial, atrapamiento de Tab, cierre con Escape y restauración del foco.
 * Comparte el comportamiento de los diálogos modales de la aplicación.
 */
export function useDialogFocus(dialogRef, closeButtonRef, onClose) {
    let previousFocus = null;

    function focusables() {
        return Array.from(
            dialogRef.value?.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])') ?? [],
        );
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            onClose();

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
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    onMounted(async () => {
        previousFocus = document.activeElement;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeydown);
        await nextTick();
        closeButtonRef.value?.focus();
    });

    onBeforeUnmount(() => {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', onKeydown);
        previousFocus?.focus?.();
    });
}
