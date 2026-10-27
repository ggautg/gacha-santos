import { reactive } from 'vue';

const state = reactive({
    name: '',
    grace: 0,
    pity: 0,
    wins: 0,
    losses: 0,
    free_ready: true,
    free_in: 0,
});

export function syncPlayer(player) {
    if (!player || typeof player !== 'object') {
        return;
    }

    for (const key of Object.keys(player)) {
        if (key in state) {
            state[key] = player[key];
        }
    }
}

export function patchPlayer(patch) {
    syncPlayer(patch);
}

export function usePlayer() {
    return state;
}

export function startFreeCountdown() {
    const timer = window.setInterval(() => {
        if (state.free_ready) {
            return;
        }

        if (state.free_in > 0) {
            state.free_in -= 1;

            if (state.free_in <= 0) {
                state.free_in = 0;
                state.free_ready = true;
            }
        }
    }, 1000);

    return () => window.clearInterval(timer);
}

export function formatCountdown(seconds) {
    const total = Math.max(0, Math.floor(seconds ?? 0));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const secs = total % 60;

    if (hours > 0) {
        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    return `${minutes}:${String(secs).padStart(2, '0')}`;
}

export function formatNumber(value) {
    return new Intl.NumberFormat('es-ES').format(value ?? 0);
}
