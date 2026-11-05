import { reactive } from 'vue';

const state = reactive({
    name: '',
    grace: 0,
    pity: 0,
    wins: 0,
    losses: 0,
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

export function formatNumber(value) {
    return new Intl.NumberFormat('es-ES').format(value ?? 0);
}
