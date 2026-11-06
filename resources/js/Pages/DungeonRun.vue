<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SaintCard from '../Components/SaintCard.vue';
import { post } from '../lib/api';
import { patchPlayer } from '../lib/player';
import { play } from '../lib/sfx';
import { applyTheme } from '../lib/theme';
import { toast } from '../lib/toast';

const DungeonScene = defineAsyncComponent(() => import('../Components/DungeonScene.vue'));

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
const skipRequested = ref(false);
const submitting = ref(false);
const choosing = ref(false);
const stepping = ref(false);
const teamSaving = ref(false);
const logBox = ref(null);
const rosterPanel = ref(null);
const rosterOpen = ref(props.teamIds.length === 0);
const walking = ref(false);
const journalTab = ref('equipo');

const ZOOM_MIN = 1;
const ZOOM_MAX = 2.5;
const zoom = ref(1);
const pan = reactive({ x: 0, y: 0 });
const drag = reactive({ active: false, x: 0, y: 0 });
const view = ref(readSavedView());

function readSavedView() {
    try {
        return window.localStorage.getItem('sanctorum:dungeon-view') === '2d' ? '2d' : '3d';
    } catch (error) {
        return '3d';
    }
}

function setView(mode) {
    if (view.value === mode) {
        return;
    }

    view.value = mode;

    try {
        window.localStorage.setItem('sanctorum:dungeon-view', mode);
    } catch (error) {
        // Sin almacenamiento: la preferencia vive solo en esta visita.
    }

    play('click');
}

function fallbackToMap() {
    view.value = '2d';
    toast.info('La vista 3D no está disponible; se muestra el mapa plano.');
}

const boardStyle = computed(() => ({
    aspectRatio: boardRatio.value,
    transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom.value})`,
}));

function setZoom(value) {
    zoom.value = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(value * 100) / 100));

    if (zoom.value <= 1) {
        pan.x = 0;
        pan.y = 0;
    }
}

function zoomBy(step) {
    setZoom(zoom.value + step);
}

function resetView() {
    zoom.value = 1;
    pan.x = 0;
    pan.y = 0;
}

function onWheel(event) {
    zoomBy(event.deltaY < 0 ? 0.15 : -0.15);
}

function onDragStart(event) {
    if (zoom.value <= 1 || event.button !== 0) {
        return;
    }

    drag.active = true;
    drag.x = event.clientX - pan.x;
    drag.y = event.clientY - pan.y;
    event.currentTarget.setPointerCapture(event.pointerId);
}

function onDragMove(event) {
    if (!drag.active) {
        return;
    }

    pan.x = event.clientX - drag.x;
    pan.y = event.clientY - drag.y;
}

function onDragEnd() {
    drag.active = false;
}

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
const roomById = computed(() => new Map(mapRooms.value.map((room) => [room.id, room])));
const currentRoom = computed(() => roomById.value.get(mapState.value.room) ?? null);
const clearedCount = computed(() => mapRooms.value.filter((room) => room.cleared).length);
const currentCleared = computed(() => mapRooms.value.find((room) => room.current)?.cleared ?? false);
const boardRatio = computed(() => `${mapState.value.width ?? 9} / ${mapState.value.height ?? 3}`);

const exploredPercent = computed(() => {
    const total = mapRooms.value.length;

    return total > 0 ? Math.round((clearedCount.value / total) * 100) : 0;
});

const walkerX = computed(() => cellX(currentRoom.value?.x ?? 0));
const walkerY = computed(() => cellY(currentRoom.value?.y ?? 0));

const corridors = computed(() => {
    const segments = [];
    const drawn = new Set();

    for (const room of mapRooms.value) {
        for (const otherId of room.links) {
            const other = roomById.value.get(otherId);
            const key = `${Math.min(room.id, otherId)}-${Math.max(room.id, otherId)}`;

            if (!other || drawn.has(key)) {
                continue;
            }

            drawn.add(key);
            segments.push({
                x1: room.x + 0.5,
                y1: room.y + 0.5,
                x2: other.x + 0.5,
                y2: other.y + 0.5,
                fog: !other.known,
            });
        }
    }

    return segments;
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

    switch (current.value.kind) {
        case 'camp':
            return 'Campamento seguro';
        case 'altar':
            return 'Altar de la Virgen';
        case 'trap':
            return 'Trampa en el camino';
        case 'treasure':
            return 'Arca de limosnas';
        case 'gospel':
            return 'Página de los Evangelios';
        case 'boss':
            return 'El jefe aguarda';
        case 'battle':
            return 'Criaturas al acecho';
        default:
            return 'Sala';
    }
});

const mapHint = computed(() => {
    if (isCamp.value) {
        return 'Descansa y ajusta tu equipo. Desde aquí puedes seguir explorando.';
    }

    if (current.value.kind === 'altar') {
        return 'La Virgen ofrece curación o una bendición para el próximo combate.';
    }

    if (current.value.kind === 'trap') {
        return 'Puedes cruzar y encajar el daño, o sortearla pagando Puntos de Fe.';
    }

    if (current.value.kind === 'treasure') {
        return 'Abre el arca para recoger los Puntos de Fe que guarda.';
    }

    if (current.value.kind === 'gospel') {
        return 'Lee el fragmento: recibes Puntos de Fe y una bendición para la próxima batalla.';
    }

    if (!mapState.value.resolved) {
        return 'Presenta batalla para despejar la sala y poder seguir explorando.';
    }

    return 'Elige una sala conectada. El jefe se esconde en algún rincón del laberinto.';
});

function cellX(x) {
    return ((x + 0.5) / (mapState.value.width || 1)) * 100;
}

function cellY(y) {
    return ((y + 0.5) / (mapState.value.height || 1)) * 100;
}

function roomLabel(room) {
    return room.known ? nodeLabel(room.kind) : 'Sin explorar';
}

function roomGlyph(room) {
    return room.known ? nodeGlyph(room.kind) : '?';
}

function nodeLabel(kind) {
    switch (kind) {
        case 'camp':
            return 'Campamento';
        case 'altar':
            return 'Altar';
        case 'trap':
            return 'Trampa';
        case 'treasure':
            return 'Tesoro';
        case 'gospel':
            return 'Fragmento';
        case 'boss':
            return 'Jefe';
        case 'battle':
            return 'Combate';
        default:
            return 'Sala';
    }
}

function nodeGlyph(kind) {
    switch (kind) {
        case 'treasure':
            return '◆';
        case 'gospel':
            return '✞';
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
                    <span class="mz-stat"><strong>{{ runFloor }}</strong> piso</span>
                    <span class="mz-stat"><strong>{{ bestFloor }}</strong> récord</span>
                    <span class="mz-stat mz-stat--gold">
                        <span class="coin" aria-hidden="true"></span>
                        <strong>{{ grace }}</strong>
                    </span>
                    <span v-if="blessing > 0" class="mz-stat mz-stat--teal">
                        <strong>+{{ blessing }}%</strong> ataque
                    </span>
                </div>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" :disabled="phase === 'running'" @click="restartRun">
                    Reiniciar descenso
                </button>
            </div>
        </header>

        <div class="mz-layout">
            <section class="mz-panel mz-map">
                <header class="mz-panel-head">
                    <h2 class="mz-panel-title">Laberinto del piso {{ runFloor }}</h2>
                    <span class="mz-panel-note">{{ statusText }}</span>
                    <div class="mz-map-tools">
                        <div class="mz-view-toggle" role="group" aria-label="Vista del laberinto">
                            <button
                                type="button"
                                class="mz-btn mz-btn--ghost mz-btn--sm mz-view-btn"
                                :class="{ 'is-active': view === '3d' }"
                                :aria-pressed="view === '3d'"
                                title="Vista 3D del laberinto"
                                @click="setView('3d')"
                            >
                                3D
                            </button>
                            <button
                                type="button"
                                class="mz-btn mz-btn--ghost mz-btn--sm mz-view-btn"
                                :class="{ 'is-active': view === '2d' }"
                                :aria-pressed="view === '2d'"
                                title="Mapa plano del laberinto"
                                @click="setView('2d')"
                            >
                                Mapa
                            </button>
                        </div>
                        <div v-if="view === '2d'" class="mz-zoom" role="group" aria-label="Zoom del laberinto">
                            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" aria-label="Alejar" :disabled="zoom <= ZOOM_MIN" @click="zoomBy(-0.2)">−</button>
                            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-zoom-level" :title="'Restablecer vista'" @click="resetView">
                                {{ Math.round(zoom * 100) }}%
                            </button>
                            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" aria-label="Acercar" :disabled="zoom >= ZOOM_MAX" @click="zoomBy(0.2)">+</button>
                        </div>
                    </div>
                </header>

                <DungeonScene
                    v-if="view === '3d'"
                    :map="mapState"
                    :team="teamCards"
                    :accent="dungeon.accent"
                    :accent2="dungeon.accent2"
                    :walking="walking"
                    :can-choose="canChoose"
                    @move="moveTo"
                    @unsupported="fallbackToMap"
                />

                <div
                    v-else
                    class="mz-viewport"
                    :class="{ 'is-zoomed': zoom > 1, 'is-dragging': drag.active }"
                    @wheel.prevent="onWheel"
                    @pointerdown="onDragStart"
                    @pointermove="onDragMove"
                    @pointerup="onDragEnd"
                    @pointercancel="onDragEnd"
                >
                <div class="mz-board" :class="{ 'is-dragging': drag.active }" :style="boardStyle">
                    <svg class="mz-links" :viewBox="`0 0 ${mapState.width} ${mapState.height}`" preserveAspectRatio="none" aria-hidden="true">
                        <line
                            v-for="(segment, index) in corridors"
                            :key="index"
                            :class="{ 'is-fog': segment.fog }"
                            :x1="segment.x1"
                            :y1="segment.y1"
                            :x2="segment.x2"
                            :y2="segment.y2"
                        />
                    </svg>

                    <button
                        v-for="room in mapRooms"
                        :key="room.id"
                        type="button"
                        class="mz-node"
                        :class="{
                            'is-current': room.current,
                            'is-cleared': room.cleared,
                            'is-fog': !room.known,
                            'is-reachable': room.reachable,
                            [`is-${room.kind}`]: room.known,
                        }"
                        :style="{ left: `${cellX(room.x)}%`, top: `${cellY(room.y)}%` }"
                        :disabled="!room.reachable || !canChoose"
                        :aria-label="`${roomLabel(room)}`"
                        :title="roomLabel(room)"
                        @click="moveTo(room)"
                    >
                        <span class="mz-node-glyph" aria-hidden="true">{{ roomGlyph(room) }}</span>
                        <span v-if="room.reachable" class="mz-node-tag">Ir</span>
                    </button>

                    <div
                        class="mz-party"
                        :class="{ 'is-walking': walking }"
                        :style="{ left: `${walkerX}%`, top: `${walkerY}%` }"
                        aria-hidden="true"
                    >
                        <span v-for="card in teamCards" :key="card.id" class="mz-walker">
                            <img :src="card.art" :alt="card.name" draggable="false" />
                        </span>
                        <span v-if="teamCards.length === 0" class="mz-walker mz-walker--empty">+</span>
                    </div>
                </div>
                </div>

                <footer class="mz-map-foot">
                    <div class="mz-legend">
                        <span class="mz-legend-item is-battle"><span aria-hidden="true">⚔</span> Combate</span>
                        <span class="mz-legend-item is-camp"><span aria-hidden="true">✦</span> Campamento</span>
                        <span class="mz-legend-item is-altar"><span aria-hidden="true">✚</span> Altar</span>
                        <span class="mz-legend-item is-trap"><span aria-hidden="true">⚠</span> Trampa</span>
                        <span class="mz-legend-item is-treasure"><span aria-hidden="true">◆</span> Tesoro</span>
                        <span class="mz-legend-item is-gospel"><span aria-hidden="true">✞</span> Fragmento</span>
                        <span class="mz-legend-item is-boss"><span aria-hidden="true">☠</span> Jefe</span>
                        <span class="mz-legend-item is-fog"><span aria-hidden="true">?</span> Sin explorar</span>
                    </div>

                    <div class="mz-explore">
                        <span class="mz-explore-label">Despejado</span>
                        <div class="mz-explore-bar">
                            <span :style="{ width: `${exploredPercent}%` }"></span>
                        </div>
                        <strong>{{ clearedCount }}/{{ mapRooms.length }}</strong>
                    </div>

                    <p class="mz-hint">{{ mapHint }}</p>

                    <button v-if="phase === 'running'" type="button" class="mz-btn mz-btn--ghost mz-btn--sm" @click="skipAnimation">
                        Saltar animación
                    </button>
                </footer>
            </section>

            <aside class="mz-side">
                <template v-if="battleVisible">
        <section v-if="battleVisible" class="mz-panel mz-battle">
            <header class="mz-panel-head">
                <h2 class="mz-panel-title">Combate</h2>
                <span class="mz-panel-note">{{ statusText }}</span>
                <button v-if="phase === 'running'" type="button" class="mz-btn mz-btn--ghost mz-btn--sm" @click="skipAnimation">
                    Saltar
                </button>
            </header>

            <div class="mz-arena">
                <div class="mz-arena-vs" aria-hidden="true">VS</div>

                <div class="mz-field">
                    <div class="mz-side-units">
                        <div v-for="unit in allies" :key="unit.id" class="mz-fighter" :class="fighterClass(unit)">
                            <span
                                v-if="floatFor(unit.id)"
                                :key="floatFor(unit.id).key"
                                class="mz-float"
                                :class="{ 'is-heal': floatFor(unit.id).type === 'heal', 'is-shield': floatFor(unit.id).type === 'shield', 'is-crit': floatFor(unit.id).type === 'crit' }"
                            >
                                {{ floatFor(unit.id).text }}
                            </span>
                            <img :src="unit.sprite" :alt="unit.name" draggable="false" />
                            <span class="mz-fighter-name">{{ unit.short }}</span>
                            <span class="mz-fighter-lv">Nv. {{ unit.level }}</span>
                            <div class="mz-hp" :class="hpClass(unit)">
                                <span class="mz-hp-bar" :style="{ width: `${hpPercent(unit)}%` }"></span>
                                <span v-if="shields[unit.id]" class="mz-shield" :style="{ width: `${shieldPercent(unit)}%` }"></span>
                            </div>
                        </div>
                    </div>

                    <div class="mz-side-units mz-side-units--foe" :class="{ 'is-crowded': foes.length > 2 }">
                        <div
                            v-for="(unit, index) in foes"
                            :key="unit.id"
                            class="mz-fighter mz-fighter--foe"
                            :class="fighterClass(unit)"
                        >
                            <span
                                v-if="floatFor(unit.id)"
                                :key="floatFor(unit.id).key"
                                class="mz-float"
                                :class="{ 'is-heal': floatFor(unit.id).type === 'heal', 'is-shield': floatFor(unit.id).type === 'shield', 'is-crit': floatFor(unit.id).type === 'crit' }"
                            >
                                {{ floatFor(unit.id).text }}
                            </span>
                            <img :src="unit.sprite" :alt="unit.name" draggable="false" />
                            <span class="mz-fighter-name">{{ unit.short }}</span>
                            <span class="mz-fighter-lv">{{ isBoss && index === 0 ? 'Jefe' : `Nv. ${unit.level}` }}</span>
                            <div class="mz-hp" :class="hpClass(unit)">
                                <span class="mz-hp-bar" :style="{ width: `${hpPercent(unit)}%` }"></span>
                                <span v-if="shields[unit.id]" class="mz-shield" :style="{ width: `${shieldPercent(unit)}%` }"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="phase === 'done' && result" class="mz-result" :class="{ 'is-lose': result.result !== 'win' }">
                <div class="mz-result-copy">
                    <strong>{{ result.result === 'win' ? '¡Victoria!' : 'Derrota' }}</strong>
                    <span>Piso {{ result.floor }} · {{ result.stage_name }}</span>
                </div>

                <div class="mz-result-rewards">
                    <span>Fe <strong>+{{ result.rewards.grace }}</strong></span>
                    <span>XP <strong>+{{ result.rewards.xp }}</strong></span>
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
                <section class="mz-panel mz-combat-log">
                    <header class="mz-panel-head">
                        <h2 class="mz-panel-title">Crónica del combate</h2>
                    </header>
                    <div class="mz-panel-body">
                    <div ref="logBox" class="mz-log" aria-live="polite">
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
                </template>
                <template v-else>
                <section class="mz-panel mz-encounter">
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
                            <span>Sortear <strong>{{ current.avoid_cost }} Fe</strong></span>
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
                                :title="`Rodear la trampa cuesta ${current.avoid_cost} Puntos de Fe`"
                                @click="resolveAction('avoid')"
                            >
                                Sortear ({{ current.avoid_cost }} Fe)
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
                <section class="mz-panel mz-combat-log">
                    <header class="mz-panel-head">
                        <h2 class="mz-panel-title">Crónica del combate</h2>
                    </header>
                    <div class="mz-panel-body">
                    <div ref="logBox" class="mz-log" aria-live="polite">
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
                </template>
            </aside>
        </div>

        <section class="mz-panel mz-journal">
            <header class="mz-panel-head">
                <h2 class="mz-panel-title">Diario</h2>
                <div class="mz-tabs" role="tablist" aria-label="Diario del descenso">
                    <button
                        type="button"
                        class="mz-tab"
                        role="tab"
                        :class="{ 'is-active': journalTab === 'equipo' }"
                        :aria-selected="journalTab === 'equipo'"
                        @click="journalTab = 'equipo'"
                    >
                        Equipo
                        <span class="mz-tab-count">{{ team.length }}/3</span>
                    </button>
                    <button
                        type="button"
                        class="mz-tab"
                        role="tab"
                        :class="{ 'is-active': journalTab === 'cronicas' }"
                        :aria-selected="journalTab === 'cronicas'"
                        @click="journalTab = 'cronicas'"
                    >
                        Crónicas recientes
                        <span class="mz-tab-count">{{ history.length }}</span>
                    </button>
                </div>
            </header>

        <div v-show="journalTab === 'equipo'" ref="rosterPanel" class="mz-journal-body mz-team-pane">

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

                        <button type="button" class="mz-rift" :disabled="phase === 'running'" @click="restartRun">
                            Reiniciar el descenso
                        </button>
                    </div>
        </div>

            <div v-show="journalTab === 'cronicas'" class="mz-journal-body mz-history-body">
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

    </div>
</template>
