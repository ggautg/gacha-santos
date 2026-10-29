<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import SaintCard from '../Components/SaintCard.vue';
import SaintDetail from '../Components/SaintDetail.vue';
import { post } from '../lib/api';
import { patchPlayer, usePlayer } from '../lib/player';
import { play } from '../lib/sfx';
import { toast } from '../lib/toast';

const props = defineProps({
    saints: { type: Array, default: () => [] },
    player: { type: Object, required: true },
    raritySummary: { type: Array, default: () => [] },
    level: { type: Object, required: true },
});

const store = usePlayer();

const rarity = ref(null);
const search = ref('');
const onlyOwned = ref(false);
const selected = ref(null);
const busy = ref(false);

const normalize = (value) =>
    String(value ?? '')
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const ownedCount = computed(() => props.saints.filter((saint) => saint.owned).length);
const veiledCount = computed(() => props.saints.filter((saint) => saint.mystery).length);
const total = computed(() => props.saints.length);
const percent = computed(() => Math.round((ownedCount.value / Math.max(1, total.value)) * 100));

const filtersActive = computed(() => rarity.value !== null || onlyOwned.value || search.value.trim() !== '');
const revealsVeiled = computed(() => rarity.value === null && search.value.trim() === '' && !onlyOwned.value);

const filtered = computed(() => {
    const query = normalize(search.value.trim());

    return props.saints.filter((saint) => {
        if (saint.mystery) {
            return revealsVeiled.value;
        }

        if (rarity.value !== null && saint.rarity !== rarity.value) {
            return false;
        }

        if (onlyOwned.value && !saint.owned) {
            return false;
        }

        if (query !== '') {
            const haystack = normalize(
                [saint.name, saint.epithet, saint.patronage, saint.role, saint.region, saint.era].join(' '),
            );

            if (!haystack.includes(query)) {
                return false;
            }
        }

        return true;
    });
});

function openSaint(saint) {
    play('open');
    selected.value = saint;
}

function clearFilters() {
    rarity.value = null;
    onlyOwned.value = false;
    search.value = '';
}

async function levelUp(saint) {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        const result = await post(`/santos/${saint.slug}/iluminar`);

        if (saint.owned) {
            saint.owned = {
                ...saint.owned,
                level: result.level,
                xp: result.xp,
                xp_next: result.xp_next,
                stats: result.stats,
                power: result.power,
            };
        }

        patchPlayer({ grace: result.grace });
        play('level');
        toast.success(`${saint.name} alcanza el nivel ${result.level} y brilla con más fuerza.`);
    } catch (error) {
        play('error');
        toast.error(error.firstError?.() ?? error.message);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head title="Colección" />

    <header class="page-head">
        <div>
            <span class="page-kicker">Sanctorum · Santoral</span>
            <h1 class="page-title">El santoral reunido</h1>
            <p class="page-lead">
                Cada oración añade un retrato al santoral. Ilumina a tus santos con Puntos de Fe para
                subir su nivel, su poder y sus estadísticas de combate.
            </p>
        </div>

        <div class="panel" style="min-width: 260px">
            <div class="panel-body" style="padding: 12px 16px">
                <div class="pity-head">
                    <span>Progreso del santoral</span>
                    <strong>{{ ownedCount }} / {{ total }}</strong>
                </div>
                <div class="meter">
                    <span :style="{ width: `${percent}%` }"></span>
                </div>
                <p class="panel-note" style="margin-top: 6px">
                    {{ percent }}% de los santos revelados · {{ store.grace }} Puntos de Fe
                </p>
            </div>
        </div>
    </header>

    <div class="toolbar">
        <button type="button" class="chip" :class="{ 'is-active': rarity === null }" @click="rarity = null">
            Todas
        </button>
        <button
            v-for="item in raritySummary"
            :key="item.rarity"
            type="button"
            class="chip"
            :class="{ 'is-active': rarity === item.rarity }"
            @click="rarity = item.rarity"
        >
            <span class="dot" :style="{ background: item.color }"></span>
            {{ item.label }}
            <span class="dim">{{ item.owned }}/{{ item.total }}</span>
        </button>

        <label class="search">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="square" aria-hidden="true">
                <circle cx="7" cy="7" r="4.5" />
                <path d="M10.5 10.5 14 14" />
            </svg>
            <input v-model="search" type="search" placeholder="Buscar por nombre o patronazgo…" aria-label="Buscar santos" />
        </label>

        <label class="toggle">
            <input v-model="onlyOwned" type="checkbox" />
            Solo obtenidos
        </label>
    </div>

    <div class="collection-head">
        <span>
            Mostrando <strong>{{ filtered.length }}</strong> santos
            <template v-if="filtersActive"> · <button type="button" class="section-link" style="background: none; border: 0; cursor: pointer" @click="clearFilters">Limpiar filtros</button></template>
        </span>
        <span v-if="veiledCount && !filtersActive" class="dim">
            {{ veiledCount }} velados · su identidad permanece oculta
        </span>
        <span v-else-if="store.grace < level.base_cost" class="dim">Gana Puntos de Fe en las mazmorras para iluminar</span>
    </div>

    <div v-if="filtered.length" class="card-grid card-grid--wide">
        <SaintCard
            v-for="saint in filtered"
            :key="saint.id"
            :saint="saint"
            :locked="!saint.owned && !saint.mystery"
            :selected="selected?.id === saint.id"
            @select="openSaint"
        />
    </div>

    <div v-else class="empty">
        <strong>Ningún santo coincide</strong>
        <p>Prueba con otro nombre, rareza o limpia los filtros para ver el santoral completo.</p>
        <button type="button" class="btn btn--ghost btn--sm" style="margin-top: 12px" @click="clearFilters">
            Limpiar filtros
        </button>
    </div>

    <SaintDetail
        v-if="selected"
        :saint="selected"
        :max-level="level.max"
        :cost-base="level.base_cost"
        :busy="busy"
        @close="selected = null"
        @level-up="levelUp"
    />
</template>
