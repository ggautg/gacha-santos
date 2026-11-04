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
    path: { type: Array, default: () => [] },
    map: { type: Object, default: () => ({}) },
    roster: { type: Array, default: () => [] },
    teamIds: { type: Array, default: () => [] },
    vitals: { type: Object, default: () => ({}) },
    blessing: { type: Number, default: 0 },
    history: { type: Array, default: () => [] },
    player: { type: Object, required: true },
});

const battleEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/batalla`);
const routeEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/ruta`);
const stepEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/paso`);
const teamEndpoint = computed(() => `/mazmorras/${props.dungeon.key}/equipo`);

const ROW_HEIGHT = 50;
const BOARD_TOP = 42;
const BOARD_BOTTOM = 34;
const NODE_HALF = 20;

const team = ref([...props.teamIds]);
const savedTeam = ref([...props.teamIds]);
const current = ref({ ...props.encounter });
const mapRows = ref([...(props.path ?? [])]);
const mapInfo = ref({ ...props.map });
const vitals = ref({ ...props.vitals });
const blessing = ref(props.blessing ?? 0);
const bestFloor = ref(props.dungeon.best_floor);
const runFloor = ref(props.dungeon.run_floor);
const grace = ref(props.player.grace ?? 0);
const history = ref([...props.history]);
const selectedBranch = ref(props.dungeon.route ?? 0);
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
const skipRequested = ref(false);
const submitting = ref(false);
const choosing = ref(false);
const stepping = ref(false);
const teamSaving = ref(false);
const logBox = ref(null);
const rosterPanel = ref(null);
const rosterOpen = ref(props.teamIds.length === 0);
const walking = ref(false);

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
const currentRowIndex = computed(() => {
    const index = mapRows.value.findIndex((row) => row.status === 'current');

    return index >= 0 ? index : 0;
});
const currentRow = computed(() => mapRows.value[currentRowIndex.value] ?? null);
const branchCount = computed(() => currentRow.value?.options?.length ?? 1);
const selectedOptionIndex = computed(() => {
    const row = currentRow.value;

    if (!row) {
        return 0;
    }

    const index = row.options.findIndex((option) => option.branch === selectedBranch.value);

    return index >= 0 ? index : 0;
});

const roomsLeft = computed(() => Math.max(0, (mapInfo.value.length ?? 0) - (mapInfo.value.room ?? 0)));

const boardHeight = computed(
    () => BOARD_TOP + Math.max(0, mapRows.value.length - 1) * ROW_HEIGHT + NODE_HALF + BOARD_BOTTOM,
);

const currentPosition = computed(() => {
    const row = currentRow.value;

    if (!row) {
        return { x: 50, y: BOARD_TOP };
    }

    return {
        x: nodeX(selectedOptionIndex.value, row.options.length),
        y: rowY(currentRowIndex.value),
    };
});

const walkerX = computed(() => currentPosition.value.x);

const walkerY = computed(() => currentPosition.value.y);

const connectors = computed(() => {
    const segments = [];

    for (let index = 1; index < mapRows.value.length; index++) {
        const previous = mapRows.value[index - 1];
        const row = mapRows.value[index];
        const previousXs = rowXs(previous);
        const currentXs = rowXs(row);
        const midY = (rowY(index - 1) + rowY(index)) / 2;
        const allXs = [...new Set([...previousXs, ...currentXs])];
        const minX = Math.min(...allXs);
        const maxX = Math.max(...allXs);

        if (maxX - minX > 0.01) {
            segments.push({ x1: minX, y1: midY, x2: maxX, y2: midY });
        }

        for (const x of previousXs) {
            segments.push({ x1: x, y1: rowY(index - 1) + NODE_HALF, x2: x, y2: midY });
        }

        for (const x of currentXs) {
            segments.push({ x1: x, y1: midY, x2: x, y2: rowY(index) - NODE_HALF });
        }
    }

    return segments;
});

const statusText = computed(() => {
    if (phase.value === 'running') {
        return round.value > 0 ? `Ronda ${round.value}` : 'El descenso comienza…';
    }

    if (stepping.value) {
        return 'Resolviendo el paso…';
    }

    if (result.value?.result === 'lose') {
        return 'La oscuridad te ha vencido';
    }

    switch (current.value.kind) {
        case 'camp':
            return 'Campamento seguro';
        case 'altar':
            return 'Altar de la Virgen';
        case 'trap':
            return 'Trampa en el camino';
        case 'boss':
            return 'El jefe aguarda';
        case 'battle':
            return 'Criaturas al acecho';
        default:
            return branchCount.value > 1 ? 'Elige uno de los caminos' : 'Camino único';
    }
});

const mapHint = computed(() => {
    if (isCamp.value) {
        return 'Descansa y ajusta tu equipo antes de seguir bajando.';
    }

    if (current.value.kind === 'altar') {
        return 'La Virgen ofrece curación o una bendición para el próximo combate.';
    }

    if (current.value.kind === 'trap') {
        return 'Un paso en falso puede costarte vida o Puntos de Fe.';
    }

    if (branchCount.value > 1) {
        return 'Toca uno de los caminos del plano para que tus santos lo recorran.';
    }

    return roomsLeft.value > 0
        ? `Sigue el corredor: el jefe aguarda al fondo, a ${roomsLeft.value} salas.`
        : 'El jefe aguarda al fondo del piso.';
});

function nodeX(index, count) {
    if (count <= 1) {
        return 50;
    }

    return index === 0 ? 32 : 68;
}

function rowY(index) {
    return BOARD_TOP + index * ROW_HEIGHT;
}

function rowXs(row) {
    const count = row?.options?.length ?? 1;

    return count <= 1 ? [50] : [32, 68];
}

function nodeTag(option, row) {
    if (row.status === 'current') {
        if (option.kind === 'camp' && row.offset === 0) {
            return 'Entrada';
        }

        return row.options.length > 1 ? 'Camino' : 'Aquí';
    }

    if (option.kind === 'camp') {
        return 'Camp';
    }

    if (option.kind === 'boss') {
        return 'Jefe';
    }

    return '';
}

function nodeGlyph(kind) {
    switch (kind) {
        case 'camp':
            return '✦';
        case 'altar':
            return '✚';
        case 'trap':
            return '⚠';
        case 'boss':
            return '☠';
        default:
            return '⚔';
    }
}

function nodeLabel(kind) {
    switch (kind) {
        case 'camp':
            return 'Campamento';
        case 'altar':
            return 'Altar';
        case 'trap':
            return 'Trampa';
        case 'boss':
            return 'Jefe';
        case 'battle':
            return 'Combate';
        default:
            return 'Descenso';
    }
}

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
    skipRequested.value = false;
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

    const selected = typeof data.route === 'number' ? data.route : data.branch;

    if (typeof selected === 'number') {
        selectedBranch.value = selected;
    }

    if (data.encounter) {
        current.value = { ...data.encounter };
    }

    if (Array.isArray(data.path)) {
        mapRows.value = data.path;
    }

    if (data.map) {
        mapInfo.value = { ...data.map };
    }
}

function focusRoster() {
    if (!canEditTeam.value) {
        toast.info('Solo puedes cambiar el equipo en un campamento.');
        return;
    }

    rosterOpen.value = true;
    nextTick(() => {
        rosterPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
        play('error');
        toast.error(error.firstError?.() ?? error.message);

        return false;
    } finally {
        teamSaving.value = false;
    }
}

async function chooseBranch(option) {
    if (!canChoose.value) {
        return;
    }

    if (option.branch === selectedBranch.value && !teamDirty.value) {
        return;
    }

    if (teamDirty.value && !(await saveTeam(true))) {
        return;
    }

    choosing.value = true;

    try {
        const data = await post(routeEndpoint.value, { branch: option.branch });
        applyState(data);
        result.value = null;
        lastOutcome.value = null;
        phase.value = 'idle';
        reset();
        markWalking(900);
        play('click');
    } catch (error) {
        play('error');
        toast.error(error.firstError?.() ?? error.message);
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
        play('error');
        toast.error(error.firstError?.() ?? error.message);
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
            route: data.route,
            encounter: data.next_encounter,
            path: data.path,
            map: data.map,
            player: data.player,
        });
        lastOutcome.value = null;
        history.value = [data.history_entry, ...history.value].slice(0, 8);
        markWalking(1000);
        play(data.result === 'win' ? 'win' : 'lose');

        if (data.result === 'win') {
            toast.success(`Piso ${data.map_index ?? mapInfo.value.index} · sala ${data.room ?? mapInfo.value.room} conquistada. +${data.rewards.grace} Puntos de Fe.`);
        } else {
            toast.error('Has caído. El equipo recupera el aliento para reintentar.');
        }

        for (const level of data.levels ?? []) {
            toast.success(`${level.name} sube al nivel ${level.to}.`);
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

function dismissResult() {
    result.value = null;
    phase.value = 'idle';
    play('click');
}

function retry() {
    result.value = null;
    phase.value = 'idle';
    play('click');
    startFight();
}

function restartRun() {
    if (phase.value === 'running') {
        return;
    }

    const confirmed = window.confirm('¿Reiniciar el descenso desde el campamento de entrada? El récord se conserva.');

    if (!confirmed) {
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

    <header class="run-head">
        <div class="run-head-title">
            <Link href="/mazmorras" class="run-back" aria-label="Volver a las mazmorras" @click="play('click')">←</Link>
            <div>
                <span class="run-kicker">Descenso procedural</span>
                <h1 class="run-title">{{ dungeon.name }}</h1>
            </div>
        </div>

        <div class="run-head-actions">
            <div class="run-stats">
                <span class="run-stat"><strong>{{ mapInfo.index ?? 1 }}</strong> piso</span>
                <span class="run-stat"><strong>{{ bestFloor }}</strong> récord</span>
                <span class="run-stat run-stat--gold"><strong>{{ grace }}</strong> fe</span>
                <span v-if="blessing > 0" class="run-stat run-stat--blessing">
                    <strong>+{{ blessing }}%</strong> ataque
                </span>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" :disabled="phase === 'running'" @click="restartRun">
                Reiniciar descenso
            </button>
        </div>
    </header>

    <div
        class="run-layout"
        :style="{ '--dungeon-accent': dungeon.accent, '--dungeon-accent2': dungeon.accent2 }"
    >
        <section class="panel run-map">
            <div class="run-map-head">
                <h2 class="panel-title">Plano del piso {{ mapInfo.index ?? 1 }}</h2>
                <span class="run-map-status">{{ statusText }}</span>
            </div>

            <div class="map-board" :style="{ height: `${boardHeight}px` }">
                <svg
                    class="map-links"
                    :viewBox="`0 0 100 ${boardHeight}`"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <line
                        v-for="(segment, index) in connectors"
                        :key="index"
                        :x1="segment.x1"
                        :y1="segment.y1"
                        :x2="segment.x2"
                        :y2="segment.y2"
                    />
                </svg>

                <span class="map-board-label">Entrada</span>

                <div v-for="(row, rowIndex) in mapRows" :key="row.floor" class="map-row">
                    <button
                        v-for="(option, optionIndex) in row.options"
                        :key="option.branch"
                        type="button"
                        class="mini-node"
                        :class="{
                            'is-current': row.status === 'current',
                            'is-visited': row.status === 'visited',
                            'is-future': row.status === 'future',
                            'is-selected': row.status === 'current' && option.branch === selectedBranch,
                            'is-locked': row.status === 'current' && !canChoose,
                            [`is-${option.kind}`]: true,
                        }"
                        :style="{ left: `${nodeX(optionIndex, row.options.length)}%`, top: `${rowY(rowIndex)}px` }"
                        :disabled="row.status !== 'current' || !canChoose || row.options.length < 2"
                        :aria-label="`${nodeLabel(option.kind)}: ${option.name}`"
                        :title="`${nodeLabel(option.kind)} · ${option.name}`"
                        @click="chooseBranch(option)"
                    >
                        <span class="mini-node-glyph" aria-hidden="true">{{ nodeGlyph(option.kind) }}</span>
                        <span v-if="nodeTag(option, row)" class="mini-node-tag">{{ nodeTag(option, row) }}</span>
                    </button>
                </div>

                <div
                    class="map-party"
                    :class="{ 'is-walking': walking }"
                    :style="{ left: `${walkerX}%`, top: `${walkerY}px` }"
                    aria-hidden="true"
                >
                    <span v-for="card in teamCards" :key="card.id" class="map-walker">
                        <img :src="card.art" :alt="card.name" draggable="false" />
                    </span>
                    <span v-if="teamCards.length === 0" class="map-walker map-walker--empty">+</span>
                </div>
            </div>

            <div class="run-map-foot">
                <div class="map-legend">
                    <span class="map-legend-item is-battle"><span aria-hidden="true">⚔</span> Combate</span>
                    <span class="map-legend-item is-camp"><span aria-hidden="true">✦</span> Campamento</span>
                    <span class="map-legend-item is-altar"><span aria-hidden="true">✚</span> Altar</span>
                    <span class="map-legend-item is-trap"><span aria-hidden="true">⚠</span> Trampa</span>
                    <span class="map-legend-item is-boss"><span aria-hidden="true">☠</span> Jefe</span>
                </div>

                <p class="run-map-hint">{{ mapHint }}</p>

                <button
                    v-if="phase === 'running'"
                    type="button"
                    class="btn btn--ghost btn--sm"
                    @click="skipAnimation"
                >
                    Saltar animación
                </button>
            </div>
        </section>

        <aside class="run-side">
            <section class="panel run-target">
                <div class="panel-head">
                    <h2 class="panel-title">Encuentro</h2>
                    <span class="panel-note">{{ nodeLabel(current.kind) }} · Sala {{ mapInfo.room }}/{{ mapInfo.length }}</span>
                </div>
                <div class="panel-body run-target-body">
                    <div class="run-target-head">
                        <span class="run-target-art" :class="`is-${current.kind}`">
                            <img v-if="current.sprite" :src="current.sprite" :alt="current.name" draggable="false" />
                            <span v-else class="run-target-glyph" aria-hidden="true">{{ nodeGlyph(current.kind) }}</span>
                        </span>
                        <div class="run-target-copy">
                            <h3>{{ current.name }}</h3>
                            <p>{{ current.subtitle }}</p>
                        </div>
                    </div>

                    <div v-if="isBattle" class="run-target-stats">
                        <span>Vida <strong>{{ current.hp }}</strong></span>
                        <span v-if="current.mob_count > 1">Criaturas <strong>{{ current.mob_count }}</strong></span>
                        <span>Ataque <strong>{{ current.atk }}</strong></span>
                        <span>Fe <strong>+{{ current.grace }}</strong></span>
                        <span>XP <strong>+{{ current.xp }}</strong></span>
                    </div>

                    <div v-else-if="isCamp" class="run-target-stats">
                        <span>Descanso <strong>{{ current.rest }}%</strong></span>
                        <span v-if="blessing > 0">Bendición <strong>+{{ blessing }}%</strong></span>
                    </div>

                    <div v-else-if="current.kind === 'altar'" class="run-target-stats">
                        <span>Curación <strong>{{ current.heal }}%</strong></span>
                        <span>Bendición <strong>+{{ current.blessing }}%</strong></span>
                    </div>

                    <div v-else-if="current.kind === 'trap'" class="run-target-stats">
                        <span>{{ current.effect === 'grace' ? 'Robo de Fe' : 'Daño' }} <strong>{{ current.power }}%</strong></span>
                    </div>

                    <button
                        v-if="isBattle"
                        type="button"
                        class="btn btn--gold btn--block"
                        :disabled="!canFight || submitting"
                        @click="startFight"
                    >
                        {{ phase === 'running' ? 'Batalla en curso…' : (result?.result === 'lose' ? 'Reintentar batalla' : 'Presentar batalla') }}
                    </button>

                    <div v-else-if="isCamp" class="run-event-actions">
                        <button type="button" class="btn btn--gold" :disabled="stepping" @click="resolveAction('rest')">
                            Descansar
                        </button>
                        <button type="button" class="btn btn--ghost" :disabled="!canEditTeam" @click="focusRoster">
                            Cambiar equipo
                        </button>
                    </div>

                    <div v-else-if="current.kind === 'altar'" class="run-event-actions">
                        <button type="button" class="btn btn--gold" :disabled="stepping" @click="resolveAction('heal')">
                            Curar
                        </button>
                        <button type="button" class="btn btn--ghost" :disabled="stepping" @click="resolveAction('bless')">
                            Bendecir
                        </button>
                    </div>

                    <div v-else-if="current.kind === 'trap'" class="run-event-actions">
                        <button type="button" class="btn btn--gold btn--block" :disabled="stepping" @click="resolveAction('cross')">
                            Cruzar la trampa
                        </button>
                    </div>

                    <p v-if="lastOutcome" class="run-outcome">{{ lastOutcome.text }}</p>

                    <p v-if="team.length === 0" class="run-target-note">
                        Elige al menos un santo para descender.
                    </p>
                    <p v-else-if="isCamp && teamDirty" class="run-target-note">
                        Tienes cambios sin guardar en el equipo.
                    </p>
                </div>
            </section>

            <section ref="rosterPanel" class="panel run-team">
                <div class="panel-head">
                    <h2 class="panel-title">Tu equipo</h2>
                    <span class="panel-note">{{ team.length }} / 3</span>
                </div>
                <div class="panel-body">
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
                                    v-if="canEditTeam"
                                    type="button"
                                    class="slot-remove"
                                    :aria-label="`Quitar a ${slot.name}`"
                                    @click.stop="toggle(slot)"
                                >
                                    ✕
                                </button>
                                <img :src="slot.art" :alt="slot.name" draggable="false" />
                                <span class="slot-name">{{ shortName(slot.name) }}</span>
                                <span class="slot-vitals" :class="vitalClass(slot)">
                                    <span class="slot-vital-bar">
                                        <span :style="{ width: `${vitalPercent(slot)}%` }"></span>
                                    </span>
                                    <span class="slot-vital-text">{{ vitalFor(slot.id).hp }} / {{ vitalFor(slot.id).max }}</span>
                                </span>
                            </template>
                            <template v-else>
                                <span class="slot-empty">
                                    <span class="plus" aria-hidden="true">+</span>
                                    Añadir
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="run-team-foot">
                        <span class="run-team-power"><strong>{{ localPower }}</strong> poder</span>
                        <span v-if="blessing > 0" class="run-blessing">+{{ blessing }}% ataque</span>
                        <button
                            v-if="canEditTeam"
                            type="button"
                            class="btn btn--ghost btn--sm"
                            :disabled="phase === 'running'"
                            @click="rosterOpen = !rosterOpen"
                        >
                            {{ rosterOpen ? 'Cerrar' : 'Cambiar' }}
                        </button>
                        <span v-else class="run-team-lock">Equipo fijado</span>
                    </div>

                    <button
                        v-if="canEditTeam && teamDirty"
                        type="button"
                        class="btn btn--gold btn--sm btn--block"
                        :disabled="teamSaving"
                        @click="saveTeam(false)"
                    >
                        {{ teamSaving ? 'Guardando…' : 'Guardar equipo' }}
                    </button>

                    <div v-if="rosterOpen && canEditTeam" class="camp-roster run-roster">
                        <SaintCard
                            v-for="saint in roster"
                            :key="saint.id"
                            :saint="saint"
                            :selected="team.includes(saint.id)"
                            :interactive="canEditTeam"
                            @select="toggle"
                        />
                    </div>

                    <p v-if="!canEditTeam" class="run-target-note">
                        Solo puedes cambiar el equipo al detenerte en un campamento.
                    </p>

                    <div v-if="!roster.length" class="empty run-empty">
                        <strong>Tu santoral está vacío</strong>
                        <Link href="/" class="btn btn--gold btn--sm" @click="play('click')">Ir al santuario</Link>
                    </div>

                    <button type="button" class="run-rift" :disabled="phase === 'running'" @click="restartRun">
                        Reiniciar el descenso
                    </button>
                </div>
            </section>

            <details v-if="history.length" class="panel run-history">
                <summary>Crónicas recientes</summary>
                <div class="run-history-body">
                    <div v-for="(entry, index) in history.slice(0, 5)" :key="index" class="history-row">
                        <span class="res" :class="entry.result === 'win' ? 'res--win' : 'res--lose'">
                            {{ entry.result === 'win' ? 'V' : 'D' }}
                        </span>
                        <span>{{ entry.floor }}. {{ entry.name }}</span>
                        <span class="spacer">{{ entry.at }}</span>
                    </div>
                </div>
            </details>
        </aside>
    </div>

    <section v-if="battleVisible" class="panel run-battle">
        <div class="run-battle-head">
            <h2 class="panel-title">Combate</h2>
            <span class="run-battle-status">{{ statusText }}</span>
            <button v-if="phase === 'running'" type="button" class="btn btn--ghost btn--sm" @click="skipAnimation">
                Saltar
            </button>
        </div>

        <div class="arena">
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

                <div class="arena-side arena-side--foe" :class="{ 'is-crowded': foes.length > 2 }">
                    <div
                        v-for="(unit, index) in foes"
                        :key="unit.id"
                        class="fighter fighter--foe"
                        :class="fighterClass(unit)"
                    >
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
                        <span class="fighter-lv">{{ isBoss && index === 0 ? 'Jefe' : `Nv. ${unit.level}` }}</span>
                        <div class="hp-wrap">
                            <div class="hp-bar" :class="hpClass(unit)">
                                <span :style="{ width: `${hpPercent(unit)}%` }"></span>
                                <span v-if="shields[unit.id]" class="shield-bar" :style="{ width: `${shieldPercent(unit)}%` }"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="phase === 'done' && result" class="run-result" :class="{ 'is-lose': result.result !== 'win' }">
            <div class="run-result-copy">
                <strong>{{ result.result === 'win' ? '¡Victoria!' : 'Derrota' }}</strong>
                <span>Piso {{ result.map_index ?? mapInfo.index }} · Sala {{ result.room ?? mapInfo.room }} · {{ result.stage_name }}</span>
            </div>

            <div class="run-result-rewards">
                <span>Fe <strong>+{{ result.rewards.grace }}</strong></span>
                <span>XP <strong>+{{ result.rewards.xp }}</strong></span>
                <span v-if="result.levels?.length" class="run-levels">
                    Suben: {{ result.levels.map((level) => level.name).join(', ') }}
                </span>
            </div>

            <div class="btn-row">
                <button v-if="result.result !== 'win'" type="button" class="btn btn--ghost btn--sm" @click="retry">
                    Reintentar
                </button>
                <button v-else type="button" class="btn btn--gold btn--sm" @click="dismissResult">
                    Continuar
                </button>
            </div>
        </div>

        <details class="run-battle-log">
            <summary>Crónica del combate</summary>
            <div ref="logBox" class="battle-log" aria-live="polite">
                <p v-if="logEntries.length === 0" class="panel-note">La crónica aparecerá al presentar batalla.</p>
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
        </details>
    </section>
</template>
