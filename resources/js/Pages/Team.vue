<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SaintCard from '../Components/SaintCard.vue';
import { post } from '../lib/api';
import { play } from '../lib/sfx';
import { toast } from '../lib/toast';

const props = defineProps({
    roster: { type: Array, default: () => [] },
    teamIds: { type: Array, default: () => [] },
    power: { type: Number, default: 0 },
    player: { type: Object, required: true },
});

const team = ref([...props.teamIds]);
const savedTeam = ref([...props.teamIds]);
const serverPower = ref(props.power);
const status = ref('saved');
const saving = ref(false);
const rosterPanel = ref(null);

let debounceTimer = null;

const byId = computed(() => new Map(props.roster.map((saint) => [saint.id, saint])));
const slots = computed(() => [0, 1, 2].map((index) => byId.value.get(team.value[index]) ?? null));
const sortedRoster = computed(() => [...props.roster].sort((a, b) => (b.owned?.power ?? 0) - (a.owned?.power ?? 0)));

const localPower = computed(() =>
    team.value.reduce((sum, id) => sum + (byId.value.get(id)?.owned?.power ?? 0), 0),
);

const statusLabel = computed(() => {
    switch (status.value) {
        case 'saving':
            return 'Guardando…';
        case 'pending':
            return 'Cambios sin guardar';
        case 'error':
            return 'No se pudo guardar';
        default:
            return 'Guardado';
    }
});

const sameTeam = (a, b) => a.length === b.length && a.every((id, index) => id === b[index]);

function scheduleSave() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => persist(), 700);
}

async function persist(manual = false) {
    if (saving.value || team.value.length === 0) {
        return;
    }

    if (sameTeam(team.value, savedTeam.value)) {
        status.value = 'saved';

        if (manual) {
            toast.info('El equipo ya está guardado.');
        }

        return;
    }

    saving.value = true;
    status.value = 'saving';
    const snapshot = [...team.value];

    try {
        const result = await post('/equipo', { team: snapshot });
        savedTeam.value = [...result.team];
        serverPower.value = result.power;

        if (!sameTeam(team.value, result.team)) {
            status.value = 'pending';
            scheduleSave();
        } else {
            status.value = 'saved';
        }

        if (manual) {
            toast.success('Equipo guardado. La hueste está lista.');
            play('level');
        }
    } catch (error) {
        status.value = 'error';
        play('error');
        toast.error(error.firstError?.() ?? error.message);
    } finally {
        saving.value = false;
    }
}

watch(team, () => {
    if (sameTeam(team.value, savedTeam.value)) {
        status.value = 'saved';

        return;
    }

    status.value = 'pending';
    scheduleSave();
}, { deep: true });

onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);

    if (!saving.value && !sameTeam(team.value, savedTeam.value)) {
        persist();
    }
});

function toggle(saint) {
    const index = team.value.indexOf(saint.id);

    if (index !== -1) {
        if (team.value.length === 1) {
            toast.info('Tu equipo necesita al menos un santo.');
            return;
        }

        team.value.splice(index, 1);
        play('click');

        return;
    }

    if (team.value.length >= 3) {
        toast.info('Solo puedes llevar tres santos. Quita uno antes de añadir otro.');
        play('error');

        return;
    }

    team.value.push(saint.id);
    play('open');
}

function removeAt(index) {
    const id = team.value[index];

    if (id === undefined) {
        return;
    }

    if (team.value.length === 1) {
        toast.info('Tu equipo necesita al menos un santo.');
        return;
    }

    team.value.splice(index, 1);
    play('click');
}

function focusRoster() {
    rosterPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <Head title="Equipo" />

    <header class="page-head">
        <div>
            <span class="page-kicker">Sanctorum · Escuadra</span>
            <h1 class="page-title">Tu hueste de santos</h1>
            <p class="page-lead">
                Elige hasta tres santos. Combina sanadores, escudos y golpes de fe: cada perfil
                cambia el ritmo de la batalla.
            </p>
        </div>
        <div class="btn-row">
            <span class="tag">Poder <strong>{{ localPower }}</strong></span>
            <Link href="/mazmorras" class="btn btn--gold btn--sm" @click="play('click')">A las mazmorras</Link>
        </div>
    </header>

    <div class="team-layout">
        <div class="panel team-panel">
            <div class="panel-head">
                <h2 class="panel-title">Formación</h2>
                <span class="panel-note">{{ team.length }} / 3</span>
            </div>
            <div class="panel-body">
                <div class="slots">
                    <div
                        v-for="(slot, index) in slots"
                        :key="index"
                        class="slot"
                        :class="{ 'is-filled': slot }"
                        :style="slot ? { '--rarity': slot.rarity_color } : {}"
                        :role="slot ? undefined : 'button'"
                        :tabindex="slot ? undefined : 0"
                        @click="!slot && focusRoster()"
                        @keydown.enter="!slot && focusRoster()"
                    >
                        <template v-if="slot">
                            <button
                                type="button"
                                class="slot-remove"
                                :aria-label="`Quitar a ${slot.name}`"
                                @click.stop="removeAt(index)"
                            >
                                ✕
                            </button>
                            <img :src="slot.art" :alt="slot.name" draggable="false" />
                            <span class="slot-name">{{ slot.name }}</span>
                            <span class="slot-level">Nv. {{ slot.owned.level }} · {{ slot.owned.power }} PX</span>
                        </template>
                        <template v-else>
                            <span class="slot-empty">
                                <span class="plus" aria-hidden="true">+</span>
                                Elegir santo
                            </span>
                        </template>
                    </div>
                </div>

                <div class="power-line">
                    <div>
                        <span class="eyebrow">Poder total</span>
                        <div class="power-value">{{ localPower }}</div>
                    </div>
                    <div style="text-align: right">
                        <span class="panel-note" :class="{ gold: status === 'pending' }" :style="status === 'error' ? 'color: var(--danger)' : ''">
                            {{ statusLabel }}
                        </span>
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            style="margin-top: 6px"
                            :disabled="saving || team.length === 0"
                            @click="persist(true)"
                        >
                            Guardar ahora
                        </button>
                    </div>
                </div>

                <p class="panel-note" style="margin-top: 10px">
                    El equipo se guarda automáticamente. El poder inicial era {{ serverPower }}.
                </p>
            </div>
        </div>

        <div ref="rosterPanel" class="panel" style="scroll-margin-top: 96px">
            <div class="panel-head">
                <h2 class="panel-title">Tu santoral</h2>
                <span class="panel-note">{{ roster.length }} santos invocados</span>
            </div>
            <div class="panel-body">
                <div class="hint" style="margin-bottom: 14px">
                    <span class="hint-icon" aria-hidden="true">!</span>
                    <span>
                        Pulsa una carta para añadirla o quitarla. Los cambios se guardan solos y se
                        aplican en el próximo descenso.
                    </span>
                </div>

                <div v-if="sortedRoster.length" class="roster-grid">
                    <SaintCard
                        v-for="saint in sortedRoster"
                        :key="saint.id"
                        :saint="saint"
                        :selected="team.includes(saint.id)"
                        @select="toggle"
                    />
                </div>

                <div v-else class="empty">
                    <strong>Tu santoral está vacío</strong>
                    <p>Invoca a tu primer santo en el santuario para formar un equipo.</p>
                    <Link href="/" class="btn btn--gold btn--sm" style="margin-top: 12px">Ir al santuario</Link>
                </div>
            </div>
        </div>
    </div>
</template>
