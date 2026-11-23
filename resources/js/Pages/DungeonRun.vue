<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BattleFighter from '../Components/BattleFighter.vue';
import ConfirmDialog from '../Components/ConfirmDialog.vue';
import DungeonMap from '../Components/DungeonMap.vue';
import SaintCard from '../Components/SaintCard.vue';
import { post, reportError } from '../lib/api';
import { nodeGlyph, nodeHint, nodeKinds, nodeLabel, nodeStatus } from '../lib/dungeonNodes';
import { prefersReducedMotion } from '../lib/motion';
import { formatNumber, patchPlayer } from '../lib/player';
import { play } from '../lib/sfx';
import { useTemporaryTheme } from '../lib/theme';
import { toast } from '../lib/toast';

const props = defineProps({
    dungeon: { type: Object, required: true },
    encounter: { type: Object, required: true },
    map: { type: Object, default: () => ({}) },
    roster: { type: Array, default: () => [] },
    teamIds: { type: Array, default: () => [] },
    vitals: { type: Object, default: () => ({}) },
    blessing: { type: Number, default: 0 },
    history: { type: Array, default: () => [] },
    player: { type: Object, required: true },
});

const battleEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/batalla`);
const moveEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/mover`);
const stepEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/paso`);
const teamEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/equipo`);

const team = ref([...props.teamIds]);
const savedTeam = ref([...props.teamIds]);
const current = ref({ ...props.encounter });
const mapState = ref({ ...props.map });
const vitals = ref({ ...props.vitals });
const blessing = ref(props.blessing ?? 0);
const bestFloor = ref(props.dungeon.best_floor);
const runFloor = ref(props.dungeon.run_floor);
const grace = ref(props.player.grace ?? 0);
const history = ref([...props.history]);
const lastOutcome = ref(null);

const phase = ref('idle');
const result = ref(null);
const allies = ref([]);
const foes = ref([]);
const hp = reactive({});
const shields = reactive({});
const down = reactive({});
const activeUnit = ref(null);
const hitUnit = ref(null);
const floats = reactive({});
const logEntries = ref([]);
const round = ref(0);
const skipRequested = ref(prefersReducedMotion());
const submitting = ref(false);
const choosing = ref(false);
const stepping = ref(false);
const teamSaving = ref(false);
const logBox = ref(null);
const logPinned = ref(true);
const skipButton = ref(null);
const rosterPanel = ref(null);
const rosterOpen = ref(props.teamIds.length === 0);
const walking = ref(false);
const journalTab = ref('equipo');

let runToken = 0;
let floatKey = 0;
let alive = true;
const timers = new Set();

const byId = computed(() => new Map(props.roster.map((saint) => [saint.id, saint])));
const teamCards = computed(() => team.value.map((id) => byId.value.get(id)).filter(Boolean));
const localPower = computed(() => teamCards.value.reduce((total, card) => total + (card.owned?.power ?? 0), 0));
const slots = computed(() => [0, 1, 2].map((index) => teamCards.value[index] ?? null));
const allUnits = computed(() => [...allies.value, ...foes.value]);
const isBattle = computed(() => ['battle', 'boss'].includes(current.value.kind));
const isBoss = computed(() => current.value.kind === 'boss');
const isCamp = computed(() => current.value.kind === 'camp');
const canEditTeam = computed(() => isCamp.value && phase.value !== 'running' && !stepping.value);
const teamDirty = computed(() => team.value.join(',') !== savedTeam.value.join(','));
const battleVisible = computed(() => phase.value === 'running' || result.value !== null);
const canFight = computed(
    () => phase.value !== 'running' && isBattle.value && team.value.length > 0 && !submitting.value && !stepping.value,
);
const canChoose = computed(
    () => phase.value !== 'running' && !choosing.value && !stepping.value && !submitting.value,
);
const mapRooms = computed(() => mapState.value.rooms ?? []);
const clearedCount = computed(() => mapRooms.value.filter((room) => room.cleared).length);
const currentCleared = computed(() => mapRooms.value.find((room) => room.current)?.cleared ?? false);

const exploredPercent = computed(() => {
    const total = mapRooms.value.length;

    return total > 0 ? Math.round((clearedCount.value / total) * 100) : 0;
});

const statusText = computed(() => {
    if (phase.value === 'running') {
        return round.value > 0 ? `Ronda ${round.value}` : 'El descenso comienza…';
    }

    if (stepping.value) {
        return 'Resolviendo la sala…';
    }

    if (result.value?.result === 'lose') {
        return 'La oscuridad te ha vencido';
    }

    return nodeStatus(current.value.kind);
});

const mapHint = computed(() => {
    const hint = nodeHint(current.value.kind);

    if (hint) {
        return hint;
    }

    return mapState.value.resolved
        ? 'Elige una sala conectada. El jefe se esconde en algún rincón del laberinto.'
        : 'Presenta batalla para despejar la sala y poder seguir explorando.';
});

function shortName(name) {
    return String(name ?? '').replace(/^(San|Santa|Santo|Beato|Beata)\s+/u, '');
}

function vitalFor(id) {
    const stored = vitals.value?.[id];
    const card = byId.value.get(id);
    const max = Math.max(1, stored?.max ?? card?.owned?.stats?.hp ?? 1);
    const currentHp = Math.max(0, Math.min(stored?.hp ?? max, max));

    return { hp: currentHp, max, percent: Math.round((currentHp / max) * 100) };
}

function vitalPercent(card) {
    return vitalFor(card.id).percent;
}

function vitalClass(card) {
    const percent = vitalPercent(card);

    if (percent <= 25) {
        return 'is-low';
    }

    if (percent <= 55) {
        return 'is-mid';
    }

    return '';
}

function unitFromCard(card, index) {
    const stats = card.owned?.stats ?? {};
    const vital = vitalFor(card.id);

    return {
        id: `a${index + 1}`,
        side: 'ally',
        name: card.name,
        short: shortName(card.name),
        level: card.owned?.level ?? 1,
        hp: vital.hp,
        maxHp: vital.max || stats.hp || 1,
        atk: stats.atk ?? 1,
        def: stats.def ?? 0,
        spd: stats.spd ?? 1,
        skillName: card.skill?.name ?? 'Milagro',
        sprite: card.art,
        rarity: card.rarity,
    };
}

const units = computed(() => teamCards.value.map((card, index) => unitFromCard(card, index)));

function buildFoes(source) {
    if (!source || !['battle', 'boss'].includes(source.kind)) {
        return [];
    }

    const mobs = Array.isArray(source.mobs) && source.mobs.length > 0 ? source.mobs : [source];

    return mobs.map((mob, index) => ({
        id: `b${index + 1}`,
        side: 'foe',
        name: mob.name,
        short: mob.name,
        level: mob.level ?? source.floor,
        hp: mob.hp,
        maxHp: mob.hp,
        atk: mob.atk ?? 1,
        def: mob.def ?? 0,
        spd: mob.spd ?? 0,
        skillName: mob.skill_name,
        sprite: mob.sprite ?? source.sprite,
        rarity: 5,
    }));
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
    logEntries.value = [];
    round.value = 0;
    activeUnit.value = null;
    hitUnit.value = null;
    skipRequested.value = prefersReducedMotion();
    allies.value = units.value.map((unit) => ({ ...unit }));
    foes.value = buildFoes(current.value);
    Object.keys(floats).forEach((key) => delete floats[key]);

    for (const unit of [...allies.value, ...foes.value]) {
        hp[unit.id] = unit.hp ?? unit.maxHp ?? 1;
        shields[unit.id] = 0;
        down[unit.id] = (unit.hp ?? 0) <= 0;
    }
}

function markWalking(delay = 900) {
    walking.value = true;
    setTimer(() => {
        walking.value = false;
    }, delay);
}

const maxHpOf = (unit) => unit.maxHp ?? unit.hp ?? 1;
const hpOf = (unit) => Math.max(0, hp[unit.id] ?? maxHpOf(unit));

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

function summonUnit(entry) {
    const unit = entry.unit;

    if (!unit || foes.value.some((foe) => foe.id === unit.id)) {
        return;
    }

    const summoned = {
        id: unit.id,
        side: 'foe',
        name: unit.name,
        short: unit.short ?? unit.name,
        level: unit.level,
        hp: unit.hp,
        maxHp: unit.maxHp ?? unit.hp,
        atk: unit.atk,
        def: unit.def,
        spd: unit.spd,
        skillName: unit.skillName ?? unit.skill_name,
        sprite: unit.sprite,
        rarity: 5,
    };

    foes.value.push(summoned);
    hp[summoned.id] = summoned.hp;
    shields[summoned.id] = 0;
    down[summoned.id] = false;
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

        case 'summon':
            activeUnit.value = entry.actor;
            summonUnit(entry);
            pushLog('summon', entry.text, currentRound);
            if (!silent && entry.unit) {
                showFloat(entry.unit.id, 'INVOCADO', 'shield');
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
    foes.value = initial.filter((unit) => unit.side === 'foe').map((unit) => ({ ...unit }));
    logEntries.value = [];
    round.value = 0;
    activeUnit.value = null;
    hitUnit.value = null;

    for (const unit of [...allies.value, ...foes.value]) {
        hp[unit.id] = unit.maxHp ?? unit.hp;
        down[unit.id] = (unit.hp ?? 0) <= 0;
        if (down[unit.id]) {
            hp[unit.id] = 0;
        }
        shields[unit.id] = 0;
    }
}

function delayFor(entry) {
    switch (entry.t) {
        case 'round':
            return 300;
        case 'cast':
            return 380;
        case 'summon':
            return 420;
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
    const pacing = log.length > 80 ? 0.35 : log.length > 45 ? 0.55 : 1;

    for (const entry of log) {
        if (!alive || token !== runToken) {
            return;
        }

        if (!skipRequested.value) {
            await wait(delayFor(entry) * pacing);
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

function applyState(data) {
    if (Array.isArray(data.team)) {
        team.value = [...data.team];
        savedTeam.value = [...data.team];
    }

    if (data.vitals) {
        vitals.value = { ...data.vitals };
    }

    if (typeof data.blessing === 'number') {
        blessing.value = data.blessing;
    }

    if (typeof data.run_floor === 'number') {
        runFloor.value = data.run_floor;
    }

    if (typeof data.best_floor === 'number') {
        bestFloor.value = data.best_floor;
    }

    if (typeof data.player?.grace === 'number') {
        grace.value = data.player.grace;
        patchPlayer({ grace: data.player.grace });
    }

    if (data.encounter) {
        current.value = { ...data.encounter };
    }

    if (data.map) {
        mapState.value = { ...data.map };
    }
}

function focusRoster() {
    if (!canEditTeam.value) {
        toast.info('Solo puedes cambiar el equipo en un campamento.');
        return;
    }

    rosterOpen.value = true;
    journalTab.value = 'equipo';
    nextTick(() => {
        rosterPanel.value?.scrollIntoView({
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
            block: 'start',
        });
    });
}

function toggle(saint) {
    if (!canEditTeam.value) {
        toast.info('Solo puedes cambiar el equipo en un campamento.');
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

async function saveTeam(silent = false) {
    if (!canEditTeam.value || teamSaving.value) {
        return false;
    }

    if (!teamDirty.value) {
        return true;
    }

    teamSaving.value = true;

    try {
        const data = await post(teamEndpoint.value, { team: team.value });
        applyState(data);
        play('click');

        if (!silent) {
            toast.success('Equipo guardado en el campamento.');
        }

        return true;
    } catch (error) {
        reportError(error);

        return false;
    } finally {
        teamSaving.value = false;
    }
}

async function moveTo(room) {
    if (!canChoose.value || !room.reachable) {
        return;
    }

    if (teamDirty.value && !(await saveTeam(true))) {
        return;
    }

    choosing.value = true;

    try {
        const data = await post(moveEndpoint.value, { room: room.id });
        applyState(data);
        result.value = null;
        lastOutcome.value = null;
        phase.value = 'idle';
        reset();
        markWalking(900);
        play('click');
    } catch (error) {
        reportError(error);
    } finally {
        choosing.value = false;
    }
}

async function resolveAction(action) {
    if (stepping.value || phase.value === 'running') {
        return;
    }

    stepping.value = true;
    play('click');

    const payload = { action };

    if (isCamp.value) {
        payload.team = team.value;
    }

    try {
        const data = await post(stepEndpoint.value, payload);
        applyState(data);
        lastOutcome.value = data.outcome;
        result.value = null;
        phase.value = 'idle';
        reset();
        markWalking(900);
        toast.info(data.outcome.text);
        play('click');
    } catch (error) {
        reportError(error);
    } finally {
        stepping.value = false;
    }
}

async function startFight() {
    if (submitting.value || phase.value === 'running') {
        return;
    }

    if (!isBattle.value) {
        return;
    }

    if (team.value.length === 0) {
        toast.info('Elige al menos un santo en el campamento.');
        focusRoster();

        return;
    }

    result.value = null;
    lastOutcome.value = null;
    reset();
    runToken += 1;
    const token = runToken;

    phase.value = 'running';
    submitting.value = true;
    play('pull');

    try {
        const data = await post(battleEndpoint.value, {});

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
        applyState({
            team: data.team,
            vitals: data.vitals,
            blessing: data.blessing,
            run_floor: data.run_floor,
            best_floor: data.best_floor,
            encounter: data.next_encounter,
            map: data.map,
            player: data.player,
        });
        lastOutcome.value = null;
        history.value = [data.history_entry, ...history.value].slice(0, 8);
        markWalking(1000);
        play(data.result === 'win' ? 'win' : 'lose');

        if (data.result === 'win') {
            toast.success(`Sala despejada. +${data.rewards.grace} Puntos de Fe.`);
        } else {
            toast.error('Has caído. El equipo recupera el aliento para reintentar.');
        }

        for (const level of data.levels ?? []) {
            toast.success(`${level.name} sube al nivel ${level.to}.`);
        }
    } catch (error) {
        if (token === runToken) {
            phase.value = 'idle';
            reportError(error);
        }
    } finally {
        submitting.value = false;
    }
}

function switchTab(direction) {
    const tabs = ['equipo', 'cronicas'];
    const index = tabs.indexOf(journalTab.value);
    journalTab.value = tabs[(index + direction + tabs.length) % tabs.length];

    nextTick(() => {
        document.querySelector(`#mz-tab-${journalTab.value}`)?.focus();
    });
}

function focusEncounter() {
    nextTick(() => {
        const target = skipButton.value ?? document.querySelector('.mz-encounter .mz-btn:not([disabled])');
        target?.focus();
    });
}

function dismissResult() {
    result.value = null;
    phase.value = 'idle';
    play('click');
    focusEncounter();
}

function retry() {
    result.value = null;
    phase.value = 'idle';
    play('click');
    startFight();
    focusEncounter();
}

const confirmRestart = ref(false);
const restartMessage = '¿Reiniciar el descenso desde el campamento de entrada? El récord se conserva.';

function restartRun() {
    if (phase.value === 'running') {
        return;
    }

    confirmRestart.value = true;
}

function confirmRestartRun() {
    confirmRestart.value = false;
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
    () => logEntries.value.length,
    async () => {
        await nextTick();
        if (logPinned.value && logBox.value) {
            logBox.value.scrollTop = logBox.value.scrollHeight;
        }
    },
);

function trackLogScroll() {
    const box = logBox.value;

    if (box) {
        logPinned.value = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
    }
}

const restoreTheme = useTemporaryTheme(props.dungeon.theme);

onMounted(() => {
    reset();
    nextTick(() => {
        setTimer(() => {
            markWalking(900);
        }, 420);
    });
});

onBeforeUnmount(() => {
    alive = false;
    runToken += 1;
    clearTimers();
    restoreTheme();
});

const logClass = (entry) => {
    switch (entry.type) {
        case 'round':
            return 'is-round';
        case 'cast':
            return 'is-skill';
        case 'summon':
            return 'is-summon';
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

    <div class="mz" :style="{ '--mz-accent': dungeon.accent, '--mz-accent2': dungeon.accent2 }">
        <header class="mz-head">
            <div class="mz-head-title">
                <Link href="/mazmorras" class="mz-back" aria-label="Volver a las mazmorras" @click="play('click')">←</Link>
                <div>
                    <span class="mz-kicker">Descenso procedural</span>
                    <h1 class="mz-title">{{ dungeon.name }}</h1>
                </div>
            </div>

            <div class="mz-head-actions">
                <div class="mz-stats">
                    <span class="mz-stat">Piso <strong>{{ runFloor }}</strong></span>
                    <span class="mz-stat">Récord <strong>{{ bestFloor }}</strong></span>
                    <span class="mz-stat mz-stat--gold">
                        <span class="coin" aria-hidden="true"></span>
                        <strong>{{ formatNumber(grace) }}</strong>
                    </span>
                    <span v-if="blessing > 0" class="mz-stat mz-stat--teal">
                        <strong>+{{ blessing }}%</strong> de ataque
                    </span>
                </div>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" :disabled="phase === 'running' || choosing || stepping || submitting || teamSaving" @click="restartRun">
                    Reiniciar descenso
                </button>
            </div>
        </header>

        <div class="mz-layout">
            <section class="mz-panel mz-map">
                <header class="mz-panel-head">
                    <h2 class="mz-panel-title">Laberinto del piso {{ runFloor }}</h2>
                    <span class="mz-panel-note">{{ statusText }}</span>
                </header>

                <DungeonMap
                    :map="mapState"
                    :team="teamCards"
                    :walking="walking"
                    :can-choose="canChoose"
                    @move="moveTo"
                />

                <footer class="mz-map-foot">
                    <div class="mz-legend">
                        <span v-for="kind in nodeKinds()" :key="kind.key" class="mz-legend-item" :class="`is-${kind.key}`">
                            <span aria-hidden="true">{{ kind.glyph }}</span> {{ kind.label }}
                        </span>
                        <span class="mz-legend-item is-fog"><span aria-hidden="true">?</span> Sin explorar</span>
                    </div>

                    <div class="mz-explore">
                        <span class="mz-explore-label">Despejado</span>
                        <div
                            class="mz-explore-bar"
                            role="progressbar"
                            aria-label="Salas despejadas"
                            :aria-valuenow="exploredPercent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span :style="{ width: `${exploredPercent}%` }"></span>
                        </div>
                        <strong>{{ clearedCount }}/{{ mapRooms.length }}</strong>
                    </div>

                    <p class="mz-hint">{{ mapHint }}</p>

                    <button v-if="phase === 'running'" ref="skipButton" type="button" class="mz-btn mz-btn--ghost mz-btn--sm" @click="skipAnimation">
                        Saltar animación
                    </button>
                </footer>
            </section>

            <aside class="mz-side">
                <section v-if="battleVisible" class="mz-panel mz-battle">
                    <header class="mz-panel-head">
                        <h2 class="mz-panel-title">Combate</h2>
                        <span class="mz-panel-note">{{ statusText }}</span>
                    </header>

                    <div class="mz-arena">
                        <div class="mz-arena-vs" aria-hidden="true">VS</div>

                        <div class="mz-field">
                            <div class="mz-side-units">
                                <BattleFighter
                                    v-for="unit in allies"
                                    :key="unit.id"
                                    :unit="unit"
                                    :state="fighterClass(unit)"
                                    :float-value="floats[unit.id] ?? null"
                                    :hp-value="hp[unit.id] ?? unit.maxHp ?? unit.hp ?? 1"
                                    :shield-value="shields[unit.id] ?? 0"
                                    :level-label="`Nv. ${unit.level}`"
                                />
                            </div>

                            <div class="mz-side-units mz-side-units--foe" :class="{ 'is-crowded': foes.length > 2 }">
                                <BattleFighter
                                    v-for="(unit, index) in foes"
                                    :key="unit.id"
                                    :unit="unit"
                                    :state="fighterClass(unit)"
                                    :float-value="floats[unit.id] ?? null"
                                    :hp-value="hp[unit.id] ?? unit.maxHp ?? unit.hp ?? 1"
                                    :shield-value="shields[unit.id] ?? 0"
                                    :level-label="isBoss && index === 0 ? 'Jefe' : `Piso ${unit.level}`"
                                    foe
                                />
                            </div>
                        </div>
                    </div>

                    <div v-if="phase === 'done' && result" class="mz-result" :class="{ 'is-lose': result.result !== 'win' }">
                        <div class="mz-result-copy">
                            <strong>{{ result.result === 'win' ? '¡Victoria!' : 'Derrota' }}</strong>
                            <span>Piso {{ result.floor }} · {{ result.stage_name }}</span>
                        </div>

                        <div class="mz-result-rewards">
                            <span>Puntos de Fe <strong>+{{ formatNumber(result.rewards.grace) }}</strong></span>
                            <span>Experiencia <strong>+{{ formatNumber(result.rewards.xp) }}</strong></span>
                            <span v-if="result.levels?.length" class="mz-levels">
                                Suben: {{ result.levels.map((level) => level.name).join(', ') }}
                            </span>
                        </div>

                        <div class="mz-actions">
                            <button v-if="result.result !== 'win'" type="button" class="mz-btn mz-btn--ghost mz-btn--sm" @click="retry">
                                Reintentar
                            </button>
                            <button v-else type="button" class="mz-btn mz-btn--sm" @click="dismissResult">
                                Continuar
                            </button>
                        </div>
                    </div>
                </section>

                <section v-else class="mz-panel mz-encounter">
                    <header class="mz-panel-head">
                        <h2 class="mz-panel-title">Encuentro</h2>
                        <span class="mz-panel-note">{{ nodeLabel(current.kind) }} · {{ clearedCount }}/{{ mapRooms.length }} salas</span>
                    </header>

                    <div class="mz-panel-body">
                        <div class="mz-target">
                            <span class="mz-target-art" :class="`is-${current.kind}`">
                                <img v-if="current.sprite" :src="current.sprite" :alt="current.name" draggable="false" />
                                <span v-else class="mz-target-glyph" aria-hidden="true">{{ nodeGlyph(current.kind) }}</span>
                            </span>
                            <div class="mz-target-copy">
                                <h3>{{ current.name }}</h3>
                                <p>{{ current.subtitle }}</p>
                            </div>
                        </div>

                        <div v-if="isBattle" class="mz-target-stats">
                            <span>Vida <strong>{{ current.hp }}</strong></span>
                            <span v-if="current.mob_count > 1">Criaturas <strong>{{ current.mob_count }}</strong></span>
                            <span>Ataque <strong>{{ current.atk }}</strong></span>
                            <span>Fe <strong>+{{ current.grace }}</strong></span>
                            <span>XP <strong>+{{ current.xp }}</strong></span>
                        </div>

                        <div v-else-if="isCamp" class="mz-target-stats">
                            <span>Descanso <strong>{{ current.rest }}%</strong></span>
                            <span v-if="blessing > 0">Bendición <strong>+{{ blessing }}%</strong></span>
                        </div>

                        <div v-else-if="current.kind === 'altar'" class="mz-target-stats">
                            <span>Curación <strong>{{ current.heal }}%</strong></span>
                            <span>Bendición <strong>+{{ current.blessing }}%</strong></span>
                        </div>

                        <div v-else-if="current.kind === 'trap'" class="mz-target-stats">
                            <span>{{ current.effect === 'grace' ? 'Robo de Fe' : 'Daño' }} <strong>{{ current.power }}%</strong></span>
                            <span>Sortear <strong>{{ current.avoid_cost }} Puntos de Fe</strong></span>
                        </div>

                        <blockquote v-if="current.kind === 'gospel' && current.verse" class="mz-verse">
                            «{{ current.verse }}»
                        </blockquote>

                        <div v-else-if="current.kind === 'gospel'" class="mz-target-stats">
                            <span>Fe <strong>+{{ current.reward }}</strong></span>
                            <span>Bendición <strong>+10%</strong></span>
                        </div>

                        <div v-else-if="current.kind === 'treasure'" class="mz-target-stats">
                            <span>Fe <strong>+{{ current.treasure }}</strong></span>
                        </div>

                        <button
                            v-if="isBattle"
                            type="button"
                            class="mz-btn mz-btn--block"
                            :disabled="!canFight || submitting"
                            @click="startFight"
                        >
                            {{ phase === 'running' ? 'Batalla en curso…' : (result?.result === 'lose' ? 'Reintentar batalla' : 'Presentar batalla') }}
                        </button>

                        <div v-else-if="isCamp" class="mz-actions">
                            <button type="button" class="mz-btn" :disabled="stepping || currentCleared" @click="resolveAction('rest')">
                                {{ currentCleared ? 'Ya descansaste aquí' : 'Descansar' }}
                            </button>
                            <button type="button" class="mz-btn mz-btn--ghost" :disabled="!canEditTeam" @click="focusRoster">
                                Cambiar equipo
                            </button>
                        </div>

                        <div v-else-if="current.kind === 'altar' && !mapState.resolved" class="mz-actions">
                            <button type="button" class="mz-btn" :disabled="stepping" @click="resolveAction('heal')">
                                Curar
                            </button>
                            <button type="button" class="mz-btn mz-btn--ghost" :disabled="stepping" @click="resolveAction('bless')">
                                Bendecir
                            </button>
                        </div>

                        <div v-else-if="current.kind === 'trap' && !mapState.resolved" class="mz-actions">
                            <button type="button" class="mz-btn" :disabled="stepping" @click="resolveAction('cross')">
                                Cruzar la trampa
                            </button>
                            <button
                                type="button"
                                class="mz-btn mz-btn--ghost"
                                :disabled="stepping || grace < current.avoid_cost"
                                :title="`Sortear la trampa cuesta ${current.avoid_cost} Puntos de Fe`"
                                @click="resolveAction('avoid')"
                            >
                                Sortear ({{ current.avoid_cost }} Puntos de Fe)
                            </button>
                        </div>

                        <div v-else-if="current.kind === 'gospel' && !mapState.resolved" class="mz-actions">
                            <button type="button" class="mz-btn mz-btn--block" :disabled="stepping" @click="resolveAction('read')">
                                Leer el fragmento
                            </button>
                        </div>

                        <div v-else-if="current.kind === 'treasure' && !mapState.resolved" class="mz-actions">
                            <button type="button" class="mz-btn mz-btn--block" :disabled="stepping" @click="resolveAction('open')">
                                Abrir el arca
                            </button>
                        </div>

                        <p v-if="lastOutcome" class="mz-outcome">{{ lastOutcome.text }}</p>

                        <p v-if="team.length === 0" class="mz-note">Elige al menos un santo para descender.</p>
                        <p v-else-if="isCamp && teamDirty" class="mz-note">Tienes cambios sin guardar en el equipo.</p>
                    </div>
                </section>

                <!-- Single combat log: always mounted, so logBox is one element and keeps its scroll. -->
                <section class="mz-panel mz-combat-log">
                    <header class="mz-panel-head">
                        <h2 class="mz-panel-title">Crónica del combate</h2>
                    </header>
                    <div class="mz-panel-body">
                        <div ref="logBox" class="mz-log" aria-live="polite" aria-relevant="additions" @scroll="trackLogScroll">
                            <p v-if="logEntries.length === 0" class="mz-note">La crónica aparecerá al presentar batalla.</p>
                            <div
                                v-for="entry in logEntries"
                                :key="entry.key"
                                class="mz-log-entry"
                                :class="logClass(entry)"
                            >
                                <span v-if="entry.turn" class="mz-log-turn">{{ entry.turn }}</span>
                                <span>{{ entry.text }}</span>
                            </div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <section class="mz-panel mz-journal">
            <header class="mz-panel-head">
                <h2 class="mz-panel-title">Diario</h2>
                <div class="mz-tabs" role="tablist" aria-label="Diario del descenso" @keydown.left="switchTab(-1)" @keydown.right="switchTab(1)">
                    <button
                        id="mz-tab-equipo"
                        type="button"
                        class="mz-tab"
                        role="tab"
                        aria-controls="mz-panel-equipo"
                        :class="{ 'is-active': journalTab === 'equipo' }"
                        :aria-selected="journalTab === 'equipo'"
                        :tabindex="journalTab === 'equipo' ? 0 : -1"
                        @click="journalTab = 'equipo'"
                    >
                        Equipo
                        <span class="mz-tab-count">{{ team.length }}/3</span>
                    </button>
                    <button
                        id="mz-tab-cronicas"
                        type="button"
                        class="mz-tab"
                        role="tab"
                        aria-controls="mz-panel-cronicas"
                        :class="{ 'is-active': journalTab === 'cronicas' }"
                        :aria-selected="journalTab === 'cronicas'"
                        :tabindex="journalTab === 'cronicas' ? 0 : -1"
                        @click="journalTab = 'cronicas'"
                    >
                        Crónicas recientes
                        <span class="mz-tab-count">{{ history.length }}</span>
                    </button>
                </div>
            </header>

            <div
                v-show="journalTab === 'equipo'"
                id="mz-panel-equipo"
                ref="rosterPanel"
                class="mz-journal-body mz-team-pane"
                role="tabpanel"
                aria-labelledby="mz-tab-equipo"
            >
                <div class="mz-panel-body">
                    <div class="mz-slots">
                        <div
                            v-for="(slot, index) in slots"
                            :key="index"
                            class="mz-slot"
                            :class="{ 'is-filled': slot }"
                            :style="slot ? { '--rarity': slot.rarity_color } : {}"
                            :role="slot ? undefined : 'button'"
                            :tabindex="slot ? undefined : 0"
                            @click="!slot && focusRoster()"
                            @keydown.enter="!slot && focusRoster()"
                            @keydown.space.prevent="!slot && focusRoster()"
                        >
                            <template v-if="slot">
                                <button
                                    v-if="canEditTeam"
                                    type="button"
                                    class="mz-slot-remove"
                                    :aria-label="`Quitar a ${slot.name}`"
                                    @click.stop="toggle(slot)"
                                >
                                    ✕
                                </button>
                                <img :src="slot.art" :alt="slot.name" draggable="false" />
                                <span class="mz-slot-name">{{ shortName(slot.name) }}</span>
                                <span class="mz-slot-vitals" :class="vitalClass(slot)">
                                    <span class="mz-slot-bar">
                                        <span :style="{ width: `${vitalPercent(slot)}%` }"></span>
                                    </span>
                                    <span class="mz-slot-text">{{ vitalFor(slot.id).hp }} / {{ vitalFor(slot.id).max }}</span>
                                </span>
                            </template>
                            <template v-else>
                                <span class="mz-slot-empty">
                                    <span class="mz-plus" aria-hidden="true">+</span>
                                    Añadir
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="mz-team-foot">
                        <span class="mz-power"><strong>{{ localPower }}</strong> poder</span>
                        <span v-if="blessing > 0" class="mz-blessing">+{{ blessing }}% ataque</span>
                        <button
                            v-if="canEditTeam"
                            type="button"
                            class="mz-btn mz-btn--ghost mz-btn--sm"
                            :disabled="phase === 'running'"
                            @click="rosterOpen = !rosterOpen"
                        >
                            {{ rosterOpen ? 'Cerrar' : 'Cambiar' }}
                        </button>
                        <span v-else class="mz-lock">Equipo fijado</span>
                    </div>

                    <button
                        v-if="canEditTeam && teamDirty"
                        type="button"
                        class="mz-btn mz-btn--block"
                        :disabled="teamSaving"
                        @click="saveTeam(false)"
                    >
                        {{ teamSaving ? 'Guardando…' : 'Guardar equipo' }}
                    </button>

                    <div v-if="rosterOpen && canEditTeam" class="mz-roster">
                        <SaintCard
                            v-for="saint in roster"
                            :key="saint.id"
                            :saint="saint"
                            :selected="team.includes(saint.id)"
                            :interactive="canEditTeam"
                            @select="toggle"
                        />
                    </div>

                    <p v-if="!canEditTeam" class="mz-note">Solo puedes cambiar el equipo al detenerte en un campamento.</p>

                    <div v-if="!roster.length" class="mz-empty">
                        <strong>Tu santoral está vacío</strong>
                        <Link href="/" class="mz-btn mz-btn--sm" @click="play('click')">Ir al santuario</Link>
                    </div>

                    <button type="button" class="mz-rift" :disabled="phase === 'running' || choosing || stepping || submitting || teamSaving" @click="restartRun">
                        Reiniciar el descenso
                    </button>
                </div>
            </div>

            <div
                v-show="journalTab === 'cronicas'"
                id="mz-panel-cronicas"
                class="mz-journal-body mz-history-body"
                role="tabpanel"
                aria-labelledby="mz-tab-cronicas"
            >
                <p v-if="history.length === 0" class="mz-note">Aún no has descendido. Tus batallas aparecerán aquí.</p>
                <div v-for="(entry, index) in history" :key="index" class="mz-history-row">
                    <span class="mz-res" :class="entry.result === 'win' ? 'is-win' : 'is-lose'">
                        {{ entry.result === 'win' ? 'V' : 'D' }}
                    </span>
                    <span>{{ entry.floor }}. {{ entry.name }}</span>
                    <span class="mz-spacer">{{ entry.at }}</span>
                </div>
            </div>
        </section>

        <ConfirmDialog
            v-if="confirmRestart"
            :message="restartMessage"
            @confirm="confirmRestartRun"
            @cancel="confirmRestart = false"
        />
    </div>
</template>