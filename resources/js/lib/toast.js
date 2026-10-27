import { reactive } from 'vue';

let nextId = 1;

const toasts = reactive([]);

export function useToasts() {
    return toasts;
}

export function dismissToast(id) {
    const index = toasts.findIndex((toast) => toast.id === id);

    if (index !== -1) {
        toasts.splice(index, 1);
    }
}

export function pushToast(message, type = 'info', duration = 3600) {
    const id = nextId++;

    toasts.push({ id, message, type });

    if (toasts.length > 4) {
        toasts.shift();
    }

    if (duration > 0) {
        window.setTimeout(() => dismissToast(id), duration);
    }

    return id;
}

export const toast = {
    success: (message, duration) => pushToast(message, 'success', duration),
    error: (message, duration) => pushToast(message, 'error', duration ?? 5200),
    info: (message, duration) => pushToast(message, 'info', duration),
};
