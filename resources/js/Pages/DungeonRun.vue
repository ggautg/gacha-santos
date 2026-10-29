<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SaintCard from '../Components/SaintCard.vue';
import { post } from '../lib/api';
import { patchPlayer } from '../lib/player';
import { play } from '../lib/sfx';
import { applyTheme } from '../lib/theme';
import { toast } from '../lib/toast';

const props = defineProps({
    dungeon: { type: Object, required: true },
    encounter: { type: Object, required: true },
    roster: { type: Array, default: () => [] },
    teamIds: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
    player: { type: Object, required: true },
});

const endpoint = computed(() => `/mazmorras/${props.dungeon.key}/batalla`);

const team = ref([...props.teamIds]);
const current = ref({ ...props.encounter });
const bestFloor = ref(props.dungeon.best_floor);
const runFloor = ref(props.dungeon.run_floor);
const history = ref([...props.history]);
const nextEncounter = ref(null);

const phase = ref('idle');
const result = ref(null);
const allies = ref([]);
const foe = ref(null);
const hp = reactive({});
const shields = reactive({});
const down = reactive({});
const activeUnit = ref(null);
const hitUnit = ref(null);
const floats = reactive({});
const logEntries = ref([]);
const round = ref(0);
const skipRequested = ref(false);
const submitting = ref(false);
const logBox = ref(null);
const rosterPanel = ref(null);

let runToken = 0;
let floatKey = 0;
let alive = true;
const timers = new Set();

const byId = computed(() => new Map(props.roster.map((saint) => [saint.id, saint])));
const teamCards = computed(() => team.value.map((id) => byId.value.get(id)).filter(Boolean));
const localPower = computed(() => teamCards.value.reduce((total, card) => total + (card.owned?.power ?? 0), 0));
const slots = computed(() => [0, 1, 2].map((index) => teamCards.value[index] ?? null));
const allUnits = computed(() => [...allies.value, ...(foe.value ? [foe.value] : [])]);
const pendingWin = computed(() => result.value?.result === 'win' && nextEncounter.value);
const canFight = computed(() => phase.value !== 'running' && team.value.length > 0 && result.value === null);

const statusText = computed(() => {
    if (phase.value === 'running') {
        return round.value > 0 ? `Ronda ${round.value}` : 'El descenso comienza…';
    }

    if (phase.value === 'done') {
        return result.value?.result === 'win' ? '¡Piso conquistado!' : 'La oscuridad te ha vencido';
    }

    return 'Prepara el equipo y presenta batalla';
});

function shortName(name) {
    return String(name ?? '').replace(/^(San|Santa|Santo|Beato|Beata)\s+/u, '');
}

function unitFromCard(card, index) {
    const stats = card.owned?.stats ?? {};

    return {
        id: `a${index + 1}`,
        side: 'ally',
        name: card.name,
        short: shortName(card.name),
        level: card.owned?.level ?? 1,
        hp: stats.hp ?? 1,
        maxHp: stats.hp ?? 1,
        atk: stats.atk ?? 1,
        def: stats.def ?? 0,
        spd: stats.spd ?? 1,
        skillName: card.skill?.name ?? 'Milagro',
        sprite: card.art,
        rarity: card.rarity,
    };
}

const units = computed(() => teamCards.value.map((card, index) => unitFromCard(card, index)));

function buildFoe(source) {
    if (!source) {
        return null;
    }

    return {
        id: 'b1',
        side: 'foe',
        name: source.name,
        short: source.name,
        level: source.floor,
        hp: source.hp,
        maxHp: source.hp,
        atk: source.atk,
        def: source.def ?? 0,
        spd: source.spd ?? 0,
        skillName: source.skill_name,
        sprite: source.sprite,
        rarity: 5,
    };
}

function setTimer(callback, delay) {
    const id = window.setTimeout(() => {
        timers.delete(id);
        callback();
    }, delay);

    timers.add(id);

    return id;
}

function clearTimers() {
    for (const id of timers) {
        window.clearTimeout(id);
    }

    timers.clear();
}

function reset() {
    runToken += 1;
    clearTimers();
    phase.value = 'idle';
    result.value = null;
    logEntries.value = [];
    round.value = 0;
    activeUnit.value = null;
    hitUnit.value = null;
    skipRequested.value = false;
    allies.value = units.value.map((unit) => ({ ...unit }));
    foe.value = buildFoe(current.value);
    Object.keys(floats).forEach((key) => delete floats[key]);

    for (const unit of [...allies.value, ...(foe.value ? [foe.value] : [])]) {
        hp[unit.id] = unit.maxHp ?? unit.hp;
        shields[unit.id] = 0;
        down[unit.id] = false;
    }
}

const maxHpOf = (unit) => unit.maxHp ?? unit.hp ?? 1;
const hpOf = (unit) => Math.max(0, hp[unit.id] ?? maxHpOf(unit));
const hpPercent = (unit) => Math.round((hpOf(unit) / Math.max(1, maxHpOf(unit))) * 100);
const shieldPercent = (unit) => Math.min(100, Math.round(((shields[unit.id] ?? 0) / Math.max(1, maxHpOf(unit))) * 100));

function hpClass(unit) {
    const percent = hpPercent(unit);

    if (percent <= 25) {
        return 'is-low';
    }

    if (percent <= 55) {
        return 'is-mid';
    }

    return '';
}

function isDown(unit) {
    return down[unit.id] || hpOf(unit) <= 0;
}

function fighterClass(unit) {
    return {
        'is-active': activeUnit.value === unit.id && phase.value === 'running',
        'is-hit': hitUnit.value === unit.id,
        'is-down': isDown(unit),
    };
}

function unitName(id) {
    return allUnits.value.find((unit) => unit.id === id)?.short ?? id;
}

function floatFor(id) {
    return floats[id];
}

function showFloat(id, text, type = 'dmg') {
    floatKey += 1;
    const key = floatKey;
    floats[id] = { text, type, key };

    setTimer(() => {
        if (floats[id]?.key === key) {
            delete floats[id];
        }
    }, 950);
}

function pushLog(type, text, turn = null) {
    logEntries.value.push({
        key: `${logEntries.value.length}-${Math.random().toString(36).slice(2, 7)}`,
        type,
        text,
        turn,
    });
}

function applyEntry(entry, silent = false) {
    const currentRound = round.value;

    switch (entry.t) {
        case 'start':
            pushLog('start', entry.text);
            break;

        case 'round':
            round.value = entry.n;
            pushLog('round', `Ronda ${entry.n}`, entry.n);
            break;

        case 'cast':
            activeUnit.value = entry.actor;
            pushLog('cast', entry.text, currentRound);
            if (!silent) {
                play('open');
            }
            break;

        case 'attack': {
            const actor = allUnits.value.find((unit) => unit.id === entry.actor);
            hp[entry.target] = entry.hp;
            shields[entry.target] = Math.max(0, (shields[entry.target] ?? 0) - (entry.absorbed ?? 0));
            activeUnit.value = entry.actor;
            hitUnit.value = entry.target;

            const damageText = entry.dmg > 0 ? `-${entry.dmg}` : 'ABSORBIDO';
            pushLog(
                'attack',
                `${unitName(entry.actor)} ${entry.skill ? `usa «${actor?.skillName ?? 'su habilidad'}» y golpea` : 'golpea'} a ${unitName(entry.target)} · ${entry.dmg} de daño${entry.absorbed > 0 ? ` (${entry.absorbed} absorbido)` : ''}${entry.crit ? ' ¡CRÍTICO!' : ''}`,
                currentRound,
            );

            if (!silent) {
                showFloat(entry.target, damageText, entry.crit ? 'crit' : 'dmg');
                play(entry.crit ? 'crit' : 'hit');
                setTimer(() => {
                    hitUnit.value = null;
                }, 320);
            }
            break;
        }

        case 'heal':
            hp[entry.target] = entry.hp;
            pushLog('heal', `${unitName(entry.actor)} sana a ${unitName(entry.target)} · +${entry.amount} de vida`, currentRound);
            if (!silent) {
                showFloat(entry.target, `+${entry.amount}`, 'heal');
                play('heal');
            }
            break;

        case 'shield':
            shields[entry.target] = entry.shield;
            pushLog('shield', `${unitName(entry.actor)} protege a ${unitName(entry.target)} · +${entry.amount} de escudo`, currentRound);
            if (!silent) {
                showFloat(entry.target, `+${entry.amount} ESCUDO`, 'shield');
                play('shield');
            }
            break;

        case 'stun':
            if (entry.actor) {
                activeUnit.value = entry.actor;
            }
            pushLog('stun', entry.text, currentRound);
            if (!silent) {
                play('stun');
            }
            break;

        case 'buff':
            pushLog('buff', entry.text, currentRound);
            if (!silent) {
                play('level');
            }
            break;

        case 'down':
            down[entry.unit] = true;
            hp[entry.unit] = 0;
            pushLog('down', entry.text, currentRound);
            if (!silent) {
                showFloat(entry.unit, 'CAÍDO', 'crit');
                play('hit');
            }
            break;

        case 'end':
            break;

        default:
            break;
    }
}

function setupFrom(data) {
    const initial = data.initial ?? [];
    allies.value = initial.filter((unit) => unit.side === 'ally').map((unit) => ({ ...unit }));
    foe.value = initial.find((unit) => unit.side === 'foe') ?? buildFoe(current.value);
    logEntries.value = [];
    round.value = 0;
    activeUnit.value = null;
    hitUnit.value = null;

    for (const unit of [...allies.value, ...(foe.value ? [foe.value] : [])]) {
        hp[unit.id] = unit.maxHp ?? unit.hp;
        shields[unit.id] = 0;
        down[unit.id] = false;
    }
}

function delayFor(entry) {
    switch (entry.t) {
        case 'round':
            return 300;
        case 'cast':
            return 380;
        case 'attack':
            return 340;
        case 'down':
            return 360;
        case 'heal':
        case 'shield':
        case 'buff':
            return 300;
        case 'stun':
            return 280;
        case 'end':
            return 220;
        default:
            return 240;
    }
}

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

async function animate(log, data, token) {
    for (const entry of log) {
        if (!alive || token !== runToken) {
            return;
        }

        if (!skipRequested.value) {
            await wait(delayFor(entry));
        }

        if (!alive || token !== runToken) {
            return;
        }

        applyEntry(entry, skipRequested.value);
    }

    if (token === runToken) {
        for (const unit of data.units ?? []) {
            if (typeof unit.hp === 'number') {
                hp[unit.id] = unit.hp;
                if (unit.hp <= 0) {
                    down[unit.id] = true;
                }
            }
        }
    }
}

function focusRoster() {
    rosterPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function toggle(saint) {
    if (phase.value === 'running') {
        return;
    }

    const index = team.value.indexOf(saint.id);

    if (index >= 0) {
        if (team.value.length === 1) {
            toast.info('Lleva al menos un santo al descenso.');
            return;
        }

        team.value.splice(index, 1);
        play('click');

        return;
    }

    if (team.value.length >= 3) {
        toast.info('Solo puedes llevar tres santos al descenso.');
        return;
    }

    team.value.push(saint.id);
    play('click');
}

async function startFight() {
    if (submitting.value || phase.value === 'running' || result.value !== null) {
        return;
    }

    if (team.value.length === 0) {
        toast.info('Elige al menos un santo en el campamento.');
        focusRoster();

        return;
    }

    reset();
    runToken += 1;
    const token = runToken;

    phase.value = 'running';
    submitting.value = true;
    play('pull');

    try {
        const data = await post(endpoint.value, { team: team.value });

        if (token !== runToken) {
            return;
        }

        patchPlayer({
            grace: data.player.grace,
            wins: data.player.wins,
            losses: data.player.losses,
        });

        setupFrom(data);
        await animate(data.log ?? [], data, token);

        if (token !== runToken) {
            return;
        }

        result.value = data;
        phase.value = 'done';
        bestFloor.value = data.best_floor;
        runFloor.value = data.run_floor;
        nextEncounter.value = data.next_encounter;
        history.value = [data.history_entry, ...history.value].slice(0, 8);
        play(data.result === 'win' ? 'win' : 'lose');

        if (data.result === 'win') {
            toast.success(`Piso ${data.floor} conquistado: «${data.stage_name}». +${data.rewards.grace} Puntos de Fe.`);
        } else {
            toast.error('Has caído en el descenso. Puedes reintentar el mismo piso.');
        }

        for (const level of data.levels ?? []) {
            toast.success(`${level.name} sube del nivel ${level.from} al ${level.to}.`);
        }
    } catch (error) {
        if (token === runToken) {
            phase.value = 'idle';
            play('error');
            toast.error(error.firstError?.() ?? error.message);
        }
    } finally {
        submitting.value = false;
    }
}

function advance() {
    if (!pendingWin.value) {
        return;
    }

    current.value = { ...nextEncounter.value };
    runFloor.value = nextEncounter.value.floor;
    nextEncounter.value = null;
    play('click');
    reset();
}

function retry() {
    nextEncounter.value = null;
    play('click');
    reset();
}

function restartRun() {
    if (phase.value === 'running') {
        return;
    }

    play('click');

    router.post(`/mazmorras/${props.dungeon.key}/reiniciar`, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function skipAnimation() {
    skipRequested.value = true;
}

watch(
    () => team.value.join(','),
    () => {
        if (phase.value === 'running') {
            return;
        }

        runToken += 1;
        nextEncounter.value = null;
        result.value = null;
        reset();
    },
);

watch(
    () => logEntries.value.length,
    async () => {
        await nextTick();
        if (logBox.value) {
            logBox.value.scrollTop = logBox.value.scrollHeight;
        }
    },
);

onMounted(() => {
    applyTheme(props.dungeon.theme);
    reset();
});

onBeforeUnmount(() => {
    alive = false;
    runToken += 1;
    clearTimers();
});

const logClass = (entry) => {
    switch (entry.type) {
        case 'round':
            return 'is-round';
        case 'cast':
            return 'is-skill';
        case 'heal':
            return 'is-heal';
        case 'shield':
            return 'is-shield';
        case 'down':
            return 'is-down';
        case 'stun':
            return 'is-stun';
        default:
            return '';
    }
};
</script>

<template>
    <Head :title="dungeon.name" />

    <header class="page-head dungeon-head">
        <div>
            <span class="page-kicker">Sanctorum · {{ dungeon.font_name }}</span>
            <h1 class="page-title">{{ dungeon.name }}</h1>
            <p class="page-lead">{{ dungeon.tagline || dungeon.subtitle }}</p>
        </div>
        <div class="btn-row">
            <span class="tag">Piso actual <strong>{{ current.floor }}</strong></span>
            <span class="tag">Mejor piso <strong>{{ bestFloor }}</strong></span>
            <span class="tag">Poder <strong>{{ localPower }}</strong></span>
            <Link href="/mazmorras" class="btn btn--ghost btn--sm" @click="play('click')">← Mazmorras</Link>
        </div>
    </header>

    <div
        class="dungeon-run"
        :style="{ '--dungeon-accent': dungeon.accent, '--dungeon-accent2': dungeon.accent2 }"
    >
        <aside class="dungeon-camp">
            <div class="panel campsite">
                <div class="panel-head">
                    <h2 class="panel-title">Campamento</h2>
                    <span class="panel-note">{{ team.length }} / 3</span>
                </div>
                <div class="panel-body">
                    <p class="panel-note camp-hint">
                        Elige hasta tres santos antes de entrar. El equipo se sella al presentar batalla.
                    </p>

                    <div class="camp-slots">
                        <div
                            v-for="(slot, index) in slots"
                            :key="index"
                            class="camp-slot"
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
                                    :disabled="phase === 'running'"
                                    @click.stop="toggle(slot)"
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
                            <span class="eyebrow">Poder del equipo</span>
                            <div class="power-value">{{ localPower }}</div>
                        </div>
                        <button type="button" class="btn btn--ghost btn--sm" :disabled="phase === 'running'" @click="restartRun">
                            Abrir grieta nueva
                        </button>
                    </div>

                    <p class="panel-note" style="margin-top: 10px">
                        «Abrir grieta nueva» cambia el elenco de jefes del descenso, pero conserva tu mejor piso.
                    </p>
                </div>
            </div>

            <div ref="rosterPanel" class="panel" style="scroll-margin-top: 96px">
                <div class="panel-head">
                    <h2 class="panel-title">Tu santoral</h2>
                    <span class="panel-note">{{ roster.length }} santos</span>
                </div>
                <div class="panel-body">
                    <div v-if="roster.length" class="camp-roster">
                        <SaintCard
                            v-for="saint in roster"
                            :key="saint.id"
                            :saint="saint"
                            :selected="team.includes(saint.id)"
                            @select="toggle"
                        />
                    </div>

                    <div v-else class="empty">
                        <strong>Tu santoral está vacío</strong>
                        <p>Ora por tu primer santo en el santuario para descender.</p>
                        <Link href="/" class="btn btn--gold btn--sm" style="margin-top: 12px">Ir al santuario</Link>
                    </div>
                </div>
            </div>

            <div v-if="history.length" class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Crónicas del descenso</h2>
                </div>
                <div class="panel-body">
                    <div class="history">
                        <div v-for="(entry, index) in history" :key="index" class="history-row">
                            <span class="res" :class="entry.result === 'win' ? 'res--win' : 'res--lose'">
                                {{ entry.result === 'win' ? 'V' : 'D' }}
                            </span>
                            <span>{{ entry.floor }}. {{ entry.name }}</span>
                            <span class="spacer">{{ entry.at }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <div class="battle-main">
            <div class="boss-panel">
                <div class="boss-art">
                    <img :src="current.sprite" :alt="current.name" draggable="false" />
                </div>

                <div class="boss-copy">
                    <span class="eyebrow">Piso {{ current.floor }} · jefe al azar</span>
                    <h2>{{ current.name }}</h2>
                    <p class="boss-sub">{{ current.subtitle }}</p>

                    <div class="boss-stats">
                        <span class="tag">Vida <strong>{{ current.hp }}</strong></span>
                        <span class="tag">Ataque <strong>{{ current.atk }}</strong></span>
                        <span class="tag">Defensa <strong>{{ current.def }}</strong></span>
                        <span class="tag">Habilidad <strong>{{ current.skill_name }}</strong></span>
                        <span class="tag">Puntos de Fe <strong>{{ current.grace }}</strong></span>
                        <span class="tag">XP <strong>{{ current.xp }}</strong></span>
                    </div>

                    <div class="lineup">
                        <div v-for="unit in units" :key="unit.id" class="lineup-unit">
                            <img :src="unit.sprite" :alt="unit.name" draggable="false" />
                            <span>
                                <span class="u-name">{{ unit.short }}</span>
                                <span class="u-lv">Nv. {{ unit.level }}</span>
                            </span>
                        </div>
                        <span v-if="units.length === 0" class="panel-note">
                            Sin equipo. <button type="button" class="link-button" @click="focusRoster">Elige santos en el campamento.</button>
                        </span>
                    </div>

                    <div class="btn-row" style="margin-top: 10px">
                        <button
                            type="button"
                            class="btn btn--gold"
                            :disabled="!canFight || submitting"
                            @click="startFight"
                        >
                            {{ phase === 'running' ? 'Batalla en curso…' : 'Presentar batalla' }}
                        </button>
                        <button
                            v-if="phase === 'running'"
                            type="button"
                            class="btn btn--ghost btn--sm"
                            @click="skipAnimation"
                        >
                            Saltar animación
                        </button>
                        <span v-if="!pendingWin && result === null && team.length === 0" class="panel-note">
                            El campamento está vacío.
                        </span>
                    </div>
                </div>
            </div>

            <div class="arena">
                <div class="arena-status">{{ statusText }}</div>
                <div class="arena-vs" aria-hidden="true">VS</div>

                <div class="arena-field">
                    <div class="arena-side">
                        <div v-for="unit in allies" :key="unit.id" class="fighter" :class="fighterClass(unit)">
                            <span
                                v-if="floatFor(unit.id)"
                                :key="floatFor(unit.id).key"
                                class="float-dmg"
                                :class="{ 'is-heal': floatFor(unit.id).type === 'heal', 'is-shield': floatFor(unit.id).type === 'shield', 'is-crit': floatFor(unit.id).type === 'crit' }"
                            >
                                {{ floatFor(unit.id).text }}
                            </span>
                            <img :src="unit.sprite" :alt="unit.name" draggable="false" />
                            <span class="fighter-name">{{ unit.short }}</span>
                            <span class="fighter-lv">Nv. {{ unit.level }}</span>
                            <div class="hp-wrap">
                                <div class="hp-bar" :class="hpClass(unit)">
                                    <span :style="{ width: `${hpPercent(unit)}%` }"></span>
                                    <span v-if="shields[unit.id]" class="shield-bar" :style="{ width: `${shieldPercent(unit)}%` }"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="arena-side arena-side--foe">
                        <div v-if="foe" class="fighter fighter--foe" :class="fighterClass(foe)">
                            <span
                                v-if="floatFor(foe.id)"
                                :key="floatFor(foe.id).key"
                                class="float-dmg"
                                :class="{ 'is-heal': floatFor(foe.id).type === 'heal', 'is-shield': floatFor(foe.id).type === 'shield', 'is-crit': floatFor(foe.id).type === 'crit' }"
                            >
                                {{ floatFor(foe.id).text }}
                            </span>
                            <img :src="foe.sprite" :alt="foe.name" draggable="false" />
                            <span class="fighter-name">{{ foe.short }}</span>
                            <span class="fighter-lv">Piso {{ foe.level }}</span>
                            <div class="hp-wrap">
                                <div class="hp-bar" :class="hpClass(foe)">
                                    <span :style="{ width: `${hpPercent(foe)}%` }"></span>
                                    <span v-if="shields[foe.id]" class="shield-bar" :style="{ width: `${shieldPercent(foe)}%` }"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="phase === 'done' && result" class="result-banner" :class="{ 'is-lose': result.result !== 'win' }">
                <div>
                    <span class="eyebrow">{{ result.result === 'win' ? 'Piso conquistado' : 'Descenso truncado' }}</span>
                    <div class="result-title">{{ result.result === 'win' ? '¡Victoria!' : 'Derrota' }}</div>
                    <p class="panel-note">Piso {{ result.floor }} · {{ result.stage_name }}</p>
                    <p v-if="result.levels?.length" class="panel-note">
                        Suben de nivel:
                        <span v-for="level in result.levels" :key="level.slug" class="gold">{{ level.name }} ({{ level.to }}) </span>
                    </p>
                </div>
                <div class="result-rewards">
                    <span>Puntos de Fe <strong>+{{ result.rewards.grace }}</strong></span>
                    <span>XP <strong>+{{ result.rewards.xp }}</strong></span>
                </div>
                <div class="btn-row">
                    <button v-if="result.result !== 'win'" type="button" class="btn btn--ghost btn--sm" @click="retry">
                        Reintentar piso
                    </button>
                    <button v-if="pendingWin" type="button" class="btn btn--gold btn--sm" @click="advance">
                        Descender al piso {{ nextEncounter.floor }} →
                    </button>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Crónica del combate</h2>
                    <span class="panel-note">Piso {{ current.floor }}</span>
                </div>
                <div class="panel-body">
                    <div ref="logBox" class="battle-log" aria-live="polite">
                        <p v-if="logEntries.length === 0" class="panel-note">
                            La crónica aparecerá aquí cuando presente batalla.
                        </p>
                        <div
                            v-for="entry in logEntries"
                            :key="entry.key"
                            class="log-entry"
                            :class="logClass(entry)"
                        >
                            <span v-if="entry.turn" class="log-turn">{{ entry.turn }}</span>
                            <span>{{ entry.text }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
