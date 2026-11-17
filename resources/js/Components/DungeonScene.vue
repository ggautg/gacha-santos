<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { prefersReducedMotion } from '../lib/motion';
import { nodeKinds } from '../lib/dungeonNodes';

const props = defineProps({
    map: { type: Object, default: () => ({}) },
    team: { type: Array, default: () => [] },
    accent: { type: String, default: '#0ac8b9' },
    accent2: { type: String, default: '#c8aa6e' },
    walking: { type: Boolean, default: false },
    canChoose: { type: Boolean, default: false },
});

const emit = defineEmits(['move', 'unsupported']);

const host = ref(null);
const failed = ref(false);
const hoveredRoom = ref(null);
const firstPerson = ref(readCameraMode());

const CELL = 2.6;
const ORBIT_WALL_HEIGHT = 0.95;
const FIRST_WALL_HEIGHT = 2.7;
const EYE_HEIGHT = 1.42;
const RENDER_SCALE = 0.8;
const ART_RATIO = 24 / 28;
const CAMERA_KEY = 'sanctorum:dungeon-camera';
const reduceMotion = prefersReducedMotion();

const DIRECTIONS = [
    { dx: 0, dz: -1 },
    { dx: 1, dz: 0 },
    { dx: 0, dz: 1 },
    { dx: -1, dz: 0 },
];

const facing = ref(0);

function readCameraMode() {
    try {
        return window.localStorage.getItem(CAMERA_KEY) !== 'orbit';
    } catch {
        return true;
    }
}

const KINDS = nodeKinds();
const KIND_COLORS = Object.fromEntries(KINDS.map((kind) => [kind.key, Number.parseInt(kind.color.slice(1), 16)]));
const KIND_LABELS = Object.fromEntries(KINDS.map((kind) => [kind.key, kind.label]));
const MINI_FILL = Object.fromEntries(KINDS.map((kind) => [kind.key, kind.color]));

const FACING_SHORT = ['N', 'E', 'S', 'O'];

/**
 * Dibuja un icono pixel-art de 16x16 para cada tipo de sala.
 */
function makePixelTexture(kind) {
    const size = 16;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = false;

    const px = (x, y, w, h, color) => {
        ctx.fillStyle = color;
        ctx.fillRect(x, y, w, h);
    };

    const box = (x, y, w, h, fill, border = '#0a0d12') => {
        px(x, y, w, h, border);
        px(x + 1, y + 1, w - 2, h - 2, fill);
    };

    const C = {
        dark: '#0a0d12',
        stone: '#5f6b78',
        stoneHi: '#8d99a6',
        bone: '#f3ead2',
        gold: '#ffd76a',
        goldHi: '#ffe9a8',
        orange: '#f39c3d',
        flame: '#ffe9a8',
        brown: '#7a4a25',
        brownDark: '#4a2c16',
        teal: '#0ac8b9',
        blue: '#4aa3ff',
        steel: '#c7d3df',
        steelDark: '#8d99a6',
    };

    switch (kind) {
        case 'treasure': {
            box(3, 4, 10, 4, C.gold);
            box(3, 8, 10, 6, C.brown);
            px(3, 7, 10, 1, C.dark);
            px(4, 5, 8, 1, C.goldHi);
            px(7, 9, 2, 3, C.dark);
            px(7, 10, 2, 1, C.goldHi);
            break;
        }
        case 'camp': {
            px(3, 12, 10, 2, C.brown);
            px(5, 10, 6, 2, C.brownDark);
            px(7, 2, 2, 1, C.flame);
            px(6, 3, 4, 1, C.flame);
            px(5, 4, 6, 2, C.orange);
            px(4, 6, 8, 3, C.orange);
            px(5, 9, 6, 2, C.gold);
            px(7, 6, 2, 4, C.flame);
            break;
        }
        case 'altar': {
            box(3, 10, 10, 3, C.stone);
            box(5, 6, 6, 4, C.stone);
            px(4, 5, 8, 1, C.stoneHi);
            px(7, 1, 2, 3, C.teal);
            px(6, 3, 4, 1, C.teal);
            px(7, 4, 2, 1, C.goldHi);
            break;
        }
        case 'battle': {
            for (let i = 0; i < 9; i++) {
                px(3 + i, 3 + i, 1, 1, C.steel);
                px(12 - i, 3 + i, 1, 1, C.steel);
            }
            px(2, 2, 2, 2, C.brown);
            px(12, 2, 2, 2, C.brown);
            px(4, 4, 1, 1, C.gold);
            px(11, 4, 1, 1, C.gold);
            break;
        }
        case 'boss': {
            box(4, 3, 8, 7, C.bone);
            box(5, 10, 6, 2, C.bone);
            px(5, 5, 2, 2, C.dark);
            px(9, 5, 2, 2, C.dark);
            px(7, 8, 2, 1, C.dark);
            px(6, 10, 1, 2, C.dark);
            px(8, 10, 1, 2, C.dark);
            px(10, 10, 1, 2, C.dark);
            break;
        }
        case 'trap': {
            px(2, 11, 12, 3, C.stone);
            px(2, 10, 12, 1, C.stoneHi);

            for (const bx of [3, 6, 9, 12]) {
                px(bx + 1, 4, 1, 1, C.steel);
                px(bx + 1, 5, 1, 5, C.steel);
                px(bx, 7, 3, 3, C.steelDark);
            }

            break;
        }
        case 'gospel': {
            box(3, 3, 10, 10, C.blue);
            px(5, 5, 6, 6, C.bone);
            px(7, 5, 2, 6, C.gold);
            px(5, 7, 6, 2, C.gold);
            break;
        }
        default: {
            box(4, 4, 8, 8, C.stoneHi);
        }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.magFilter = THREE.NearestFilter;
    texture.minFilter = THREE.NearestFilter;
    texture.generateMipmaps = false;

    return texture;
}

/**
 * Textura de piedra/ladrillo en gris para multiplicar por el color de cada material.
 */
function makeStoneTexture(kind) {
    const size = 16;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d');
    const shades = kind === 'floor' ? [196, 214, 232] : [156, 176, 198];

    ctx.fillStyle = '#3a3f46';
    ctx.fillRect(0, 0, size, size);

    if (kind === 'floor') {
        for (let by = 0; by < 2; by++) {
            for (let bx = 0; bx < 2; bx++) {
                const shade = shades[(bx + by * 2 + 1) % shades.length];
                ctx.fillStyle = `rgb(${shade}, ${shade}, ${shade})`;
                ctx.fillRect(bx * 8 + 1, by * 8 + 1, 6, 6);
                ctx.fillStyle = 'rgba(255, 255, 255, 0.18)';
                ctx.fillRect(bx * 8 + 1, by * 8 + 1, 6, 1);
            }
        }
    } else {
        for (let row = 0; row < 4; row++) {
            const offset = row % 2 === 0 ? 0 : 4;

            for (let col = -1; col < 3; col++) {
                const shade = shades[(row + col + 3) % shades.length];
                ctx.fillStyle = `rgb(${shade}, ${shade}, ${shade})`;
                ctx.fillRect(col * 8 + offset + 1, row * 4 + 1, 6, 2);
            }
        }
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(2, 2);
    texture.magFilter = THREE.NearestFilter;
    texture.minFilter = THREE.NearestFilter;
    texture.generateMipmaps = false;
    texture.colorSpace = THREE.SRGBColorSpace;

    return texture;
}

let renderer = null;
let scene = null;
let camera = null;
let controls = null;
let mapGroup = null;
let partyGroup = null;
let currentLight = null;
let resizeObserver = null;
let raf = 0;
let disposed = false;
let walkTime = 0;
let follow = true;
let cameraTween = null;
let hoveredId = null;
let pointerDown = null;
let dragDistance = 0;
let yaw = 0;
let yawTarget = 0;
let eyeLight = null;
let initialisedFacing = false;

const timer = new THREE.Timer();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const partyTarget = new THREE.Vector3();
const focusPoint = new THREE.Vector3();
const mapCenter = new THREE.Vector3();

const roomMeshes = [];
const pickables = [];
const markers = new Map();
const rings = [];
const partySprites = [];

const onPointerDown = (event) => {
    if (event.button !== 0) {
        return;
    }

    pointerDown = { x: event.clientX, y: event.clientY };
    dragDistance = 0;
    host.value?.focus();

    if (renderer) {
        renderer.domElement.style.cursor = 'grabbing';
    }
};

const onPointerUp = (event) => {
    if (renderer) {
        renderer.domElement.style.cursor = hoveredRoom.value?.reachable && props.canChoose ? 'pointer' : 'grab';
    }

    if (pointerDown === null) {
        return;
    }

    dragDistance = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y);
    pointerDown = null;
};

const onClick = (event) => {
    if (dragDistance > 7) {
        return;
    }

    const room = pickRoom(event);

    if (room && room.reachable && props.canChoose) {
        faceRoom(room);
        emit('move', room);
    }
};

const onPointerMove = (event) => {
    if (renderer === null || pointerDown !== null) {
        return;
    }

    const room = pickRoom(event);
    const nextId = room ? room.id : null;

    if (nextId !== hoveredId) {
        hoveredId = nextId;
        hoveredRoom.value = room && room.known ? room : null;
        applyHover();
    }

    if (renderer) {
        renderer.domElement.style.cursor = room && room.reachable && props.canChoose ? 'pointer' : 'grab';
    }
};

const onPointerLeave = () => {
    if (renderer === null) {
        return;
    }

    hoveredId = null;
    hoveredRoom.value = null;
    applyHover();
};

const onKeyDown = (event) => {
    if (!firstPerson.value || failed.value || event.repeat) {
        return;
    }

    if (!host.value?.contains(document.activeElement)) {
        return;
    }

    const target = event.target;

    if (target instanceof HTMLElement && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable)) {
        return;
    }

    switch (event.key) {
        case 'ArrowUp':
        case 'w':
        case 'W':
            event.preventDefault();
            stepForward();
            break;
        case 'ArrowDown':
        case 's':
        case 'S':
            event.preventDefault();
            stepBackward();
            break;
        case 'ArrowLeft':
        case 'a':
        case 'A':
            event.preventDefault();
            turn(-1);
            break;
        case 'ArrowRight':
        case 'd':
        case 'D':
            event.preventDefault();
            turn(1);
            break;
    }
};

function worldX(x) {
    const width = Math.max(1, props.map?.width ?? 1);

    return (x - (width - 1) / 2) * CELL;
}

function worldZ(y) {
    const height = Math.max(1, props.map?.height ?? 1);

    return (y - (height - 1) / 2) * CELL;
}

function applyHover() {
    for (const tile of roomMeshes) {
        const base = tile.userData.baseEmissive ?? 0;
        tile.material.emissiveIntensity = tile.userData.room.id === hoveredId ? base + 0.5 : base;
    }
}

function pickRoom(event) {
    if (renderer === null || camera === null || roomMeshes.length === 0 || host.value === null) {
        return null;
    }

    const rect = renderer.domElement.getBoundingClientRect();

    if (rect.width === 0 || rect.height === 0) {
        return null;
    }

    pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(pointer, camera);

    const hit = raycaster.intersectObjects([...roomMeshes, ...pickables], false)[0];

    return hit?.object?.userData?.room ?? null;
}

function clearGroup(group) {
    if (group === null) {
        return;
    }

    for (const child of [...group.children]) {
        group.remove(child);
        disposeObject(child);
    }
}

function disposeObject(object) {
    object.traverse((node) => {
        // THREE.Sprite comparte una geometría interna entre todas las instancias.
        if (!node.isSprite) {
            node.geometry?.dispose?.();
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];

        for (const material of materials) {
            if (material === null || material === undefined) {
                continue;
            }

            material.map?.dispose?.();
            material.dispose?.();
        }
    });
}

function currentRoom() {
    return (props.map?.rooms ?? []).find((room) => room.current) ?? null;
}

function wallHeight() {
    return firstPerson.value ? FIRST_WALL_HEIGHT : ORBIT_WALL_HEIGHT;
}

function roomAtDirection(step = 1) {
    const room = currentRoom();

    if (room === null) {
        return null;
    }

    const direction = DIRECTIONS[facing.value];

    return (props.map?.rooms ?? []).find(
        (candidate) => candidate.x === room.x + direction.dx * step && candidate.y === room.y + direction.dz * step,
    ) ?? null;
}

const aheadRoom = computed(() => roomAtDirection(1));
const behindRoom = computed(() => roomAtDirection(-1));
const canStepForward = computed(() => aheadRoom.value !== null && aheadRoom.value.reachable && props.canChoose);
const canStepBackward = computed(() => behindRoom.value !== null && behindRoom.value.reachable && props.canChoose);

const currentRoomData = computed(() => currentRoom());
const facingShort = computed(() => FACING_SHORT[facing.value]);

const aheadInfo = computed(() => {
    const current = currentRoomData.value;
    const room = aheadRoom.value;

    if (current === null) {
        return { label: '—', kind: null };
    }

    if (room === null || !current.links.includes(room.id)) {
        return { label: 'Muro', kind: null };
    }

    return {
        label: room.known ? (KIND_LABELS[room.kind] ?? 'Sala') : 'Sin explorar',
        kind: room.kind ?? null,
    };
});

const miniRooms = computed(() => (props.map?.rooms ?? []).filter((room) => room.known));
const miniWidth = computed(() => Math.max(1, props.map?.width ?? 9));
const miniHeight = computed(() => Math.max(1, props.map?.height ?? 3));

function miniFill(room) {
    if (room.current) {
        return '#ffe9a8';
    }

    if (room.reachable) {
        return '#0ac8b9';
    }

    if (room.cleared) {
        return '#2f4a5a';
    }

    return MINI_FILL[room.kind] ?? '#2a4a63';
}

function nearestYaw(target) {
    const twoPi = Math.PI * 2;

    return yawTarget + ((((target - yawTarget) % twoPi) + 3 * Math.PI) % twoPi - Math.PI);
}

function faceDirection(index, instant = false) {
    facing.value = ((index % 4) + 4) % 4;
    yawTarget = nearestYaw(facing.value * (Math.PI / 2));

    if (instant) {
        yaw = yawTarget;
    }
}

function faceRoom(room) {
    const current = currentRoom();

    if (current === null || !room) {
        return;
    }

    const index = DIRECTIONS.findIndex(
        (direction) => direction.dx === room.x - current.x && direction.dz === room.y - current.y,
    );

    if (index >= 0) {
        faceDirection(index);
    }
}

function turn(quarters) {
    faceDirection(facing.value + quarters);
}

function ensureFacing() {
    const current = currentRoom();

    if (current === null) {
        return;
    }

    const ahead = roomAtDirection(1);

    if (ahead !== null && current.links.includes(ahead.id)) {
        return;
    }

    const option = DIRECTIONS.findIndex((direction) => {
        const candidate = (props.map?.rooms ?? []).find(
            (room) => room.x === current.x + direction.dx && room.y === current.y + direction.dz,
        );

        return candidate !== undefined && current.links.includes(candidate.id);
    });

    faceDirection(option >= 0 ? option : 0, true);
}

function stepForward() {
    if (canStepForward.value) {
        emit('move', aheadRoom.value);
    }
}

function stepBackward() {
    if (canStepBackward.value) {
        emit('move', behindRoom.value);
    }
}

function applyFog() {
    if (scene !== null && scene.fog !== null) {
        scene.fog.near = firstPerson.value ? 2.2 : 18;
        scene.fog.far = firstPerson.value ? 20 : 58;
    }
}

function applyLighting() {
    if (currentLight !== null) {
        currentLight.intensity = firstPerson.value ? 8 : 42;
    }

    if (eyeLight !== null) {
        eyeLight.intensity = firstPerson.value ? 3.5 : 0;
        eyeLight.visible = firstPerson.value;
    }
}

function applyFirstPersonCamera(delta) {
    if (camera === null || partyGroup === null) {
        return;
    }

    let diff = yawTarget - yaw;
    diff = Math.atan2(Math.sin(diff), Math.cos(diff));
    yaw += reduceMotion ? diff : diff * Math.min(1, delta * 10);

    const bob = props.walking && !reduceMotion ? Math.sin(walkTime * 2) * 0.03 : 0;
    const x = partyGroup.position.x;
    const z = partyGroup.position.z;
    const eyeY = EYE_HEIGHT + bob;

    camera.position.set(x, eyeY, z);
    camera.lookAt(x + Math.sin(yaw), eyeY - 0.08, z - Math.cos(yaw));

    if (eyeLight !== null) {
        eyeLight.position.set(x, eyeY + 0.15, z);
    }
}

function setCamera(mode) {
    const next = mode === 'first';

    if (firstPerson.value === next) {
        return;
    }

    firstPerson.value = next;

    try {
        window.localStorage.setItem(CAMERA_KEY, next ? 'first' : 'orbit');
    } catch {
        // Sin almacenamiento: la preferencia vive solo en esta visita.
    }

    if (next) {
        ensureFacing();
        focusCurrentRoom(true);
        yaw = yawTarget;
        controls.enabled = false;
    } else {
        controls.enabled = true;
        snapView();
    }

    if (partyGroup !== null) {
        partyGroup.visible = !next;
    }

    if (eyeLight !== null) {
        eyeLight.visible = next;
    }

    applyFog();
    applyLighting();
    rebuildMap();
    rebuildDecor();

    if (next) {
        applyFirstPersonCamera(1);
    }
}

function focusCurrentRoom(snap = false) {
    const room = currentRoom();

    if (room === null) {
        return;
    }

    partyTarget.set(worldX(room.x), 0, worldZ(room.y));

    if (snap && partyGroup !== null) {
        partyGroup.position.copy(partyTarget);
    }

    focusPoint.set(partyTarget.x, 0.55, partyTarget.z);
    follow = true;
    cameraTween = null;

    if (currentLight !== null) {
        currentLight.position.set(partyTarget.x, 2.6, partyTarget.z);
    }
}

function cameraOffset(distance) {
    return new THREE.Vector3(0, distance * 0.62, distance * 0.82);
}

function viewDistance() {
    const width = Math.max(1, props.map?.width ?? 9);
    const height = Math.max(1, props.map?.height ?? 3);
    const extent = Math.max(width, height) * CELL;

    return Math.max(9.5, Math.min(17, extent * 0.58));
}

function snapView() {
    if (camera === null || controls === null) {
        return;
    }

    focusCurrentRoom(true);

    const distance = viewDistance();
    controls.target.copy(focusPoint);
    camera.position.copy(focusPoint).add(cameraOffset(distance));
}

function resetView() {
    if (camera === null || controls === null) {
        return;
    }

    const distance = viewDistance();
    const target = focusPoint.set(partyTarget.x, 0.55, partyTarget.z);

    tweenCamera(target.clone(), target.clone().add(cameraOffset(distance)), true);
}

function overview() {
    if (camera === null || controls === null) {
        return;
    }

    const width = Math.max(1, props.map?.width ?? 9);
    const height = Math.max(1, props.map?.height ?? 3);
    const extent = Math.max(width, height) * CELL;
    const distance = Math.max(15, Math.min(34, extent * 1.02 + 6));
    const target = mapCenter.set(0, 0.4, 0);

    tweenCamera(target.clone(), target.clone().add(cameraOffset(distance)), false);
}

function tweenCamera(target, position, followAfter) {
    cameraTween = {
        fromTarget: controls.target.clone(),
        toTarget: target,
        fromPosition: camera.position.clone(),
        toPosition: position,
        progress: 0,
        followAfter,
    };
    follow = false;
}

function addMarker(room, wx, wz) {
    const texture = makePixelTexture(room.kind);
    const dim = room.cleared && !room.current;
    const material = new THREE.SpriteMaterial({
        map: texture,
        transparent: true,
        depthWrite: false,
        alphaTest: 0.08,
        opacity: dim ? 0.55 : 1,
    });

    const sprite = new THREE.Sprite(material);
    const size = firstPerson.value ? 0.92 : 1.4;
    sprite.scale.set(size, size, 1);
    sprite.position.set(wx, firstPerson.value ? 1.12 : 1.35, wz);
    sprite.userData.room = room;
    mapGroup.add(sprite);
    pickables.push(sprite);

    markers.set(room.id, {
        group: sprite,
        spinner: sprite,
        baseY: sprite.position.y,
        phase: room.id * 1.7,
        speed: 0,
    });
}

function addReachableRing(room, wx, wz, accent) {
    const material = new THREE.MeshBasicMaterial({
        color: accent,
        transparent: true,
        opacity: 0.55,
        depthWrite: false,
    });
    const ring = new THREE.Mesh(new THREE.TorusGeometry(0.62, 0.045, 8, 32), material);
    ring.rotation.x = -Math.PI / 2;
    ring.position.set(wx, 0.05, wz);
    mapGroup.add(ring);
    rings.push({ mesh: ring, phase: room.id * 1.3 });
}

function addCurrentRune(wx, wz, accent) {
    const material = new THREE.MeshBasicMaterial({
        color: accent,
        transparent: true,
        opacity: 0.45,
        depthWrite: false,
    });
    const rune = new THREE.Mesh(new THREE.RingGeometry(0.72, 0.9, 6), material);
    rune.rotation.x = -Math.PI / 2;
    rune.position.set(wx, 0.06, wz);
    mapGroup.add(rune);
    rings.push({ mesh: rune, phase: 0, spin: 0.25 });
}

function rebuildMap() {
    if (scene === null || mapGroup === null) {
        return;
    }

    clearGroup(mapGroup);
    markers.clear();
    rings.length = 0;
    roomMeshes.length = 0;
    pickables.length = 0;
    hoveredId = null;
    hoveredRoom.value = null;

    const rooms = props.map?.rooms ?? [];

    if (rooms.length === 0) {
        focusCurrentRoom(false);
        return;
    }

    const byCoord = new Map();

    for (const room of rooms) {
        byCoord.set(`${room.x}:${room.y}`, room);
    }

    const accent = new THREE.Color(props.accent || '#0ac8b9');
    const accent2 = new THREE.Color(props.accent2 || '#c8aa6e');
    const height = wallHeight();
    const floorTexture = makeStoneTexture('floor');
    const wallTexture = makeStoneTexture('wall');

    const floorGeometry = new THREE.BoxGeometry(CELL * 0.96, 0.24, CELL * 0.96);
    const wallXGeometry = new THREE.BoxGeometry(CELL + 0.2, height, 0.18);
    const wallZGeometry = new THREE.BoxGeometry(0.18, height, CELL + 0.2);
    const wallMaterial = new THREE.MeshStandardMaterial({
        color: 0x5a6b7d,
        map: wallTexture,
        roughness: 0.92,
        metalness: 0.06,
    });
    const corridorMaterial = new THREE.MeshStandardMaterial({
        color: 0x2a4454,
        map: floorTexture,
        emissive: accent,
        emissiveIntensity: 0.12,
        roughness: 0.9,
        metalness: 0.1,
    });
    const stubMaterial = new THREE.MeshBasicMaterial({
        color: accent,
        transparent: true,
        opacity: 0.18,
        depthWrite: false,
    });

    const drawnLinks = new Set();
    const drawnWalls = new Set();

    for (const room of rooms) {
        if (!room.known) {
            continue;
        }

        const wx = worldX(room.x);
        const wz = worldZ(room.y);
        const kindColor = new THREE.Color(KIND_COLORS[room.kind] ?? 0x2a4a63);

        const baseEmissive = room.current ? 0.55 : room.reachable ? 0.26 : room.cleared ? 0.06 : 0.13;
        const tileMaterial = new THREE.MeshStandardMaterial({
            color: new THREE.Color(0x0d1a26).lerp(kindColor, room.current ? 0.3 : 0.15),
            map: floorTexture,
            emissive: kindColor.clone(),
            emissiveIntensity: baseEmissive,
            metalness: 0.22,
            roughness: 0.78,
        });
        const tile = new THREE.Mesh(floorGeometry, tileMaterial);
        tile.position.set(wx, -0.12, wz);
        tile.receiveShadow = true;
        tile.userData.room = room;
        tile.userData.baseEmissive = baseEmissive;
        mapGroup.add(tile);
        roomMeshes.push(tile);

        for (const [dx, dz] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
            const nx = room.x + dx;
            const ny = room.y + dz;
            const neighbour = byCoord.get(`${nx}:${ny}`);
            const linked = neighbour !== undefined && room.links.includes(neighbour.id);
            const edgeKey = [`${room.x}:${room.y}`, `${nx}:${ny}`].sort().join('|');

            if (linked) {
                if (drawnLinks.has(edgeKey)) {
                    continue;
                }

                drawnLinks.add(edgeKey);

                if (neighbour.known) {
                    const alongX = dz === 0;
                    const midX = (wx + worldX(nx)) / 2;
                    const midZ = (wz + worldZ(ny)) / 2;
                    const geometry = alongX
                        ? new THREE.BoxGeometry(CELL + 0.16, 0.16, 1.15)
                        : new THREE.BoxGeometry(1.15, 0.16, CELL + 0.16);
                    const corridor = new THREE.Mesh(geometry, corridorMaterial);
                    corridor.position.set(midX, -0.08, midZ);
                    corridor.receiveShadow = true;
                    mapGroup.add(corridor);

                    const sideGeometry = alongX
                        ? new THREE.BoxGeometry(CELL + 0.16, height, 0.18)
                        : new THREE.BoxGeometry(0.18, height, CELL + 0.16);
                    const sideOffset = 1.15 / 2 + 0.09;

                    for (const sign of [-1, 1]) {
                        const side = new THREE.Mesh(sideGeometry, wallMaterial);
                        side.position.set(
                            midX + (alongX ? 0 : sign * sideOffset),
                            height / 2,
                            midZ + (alongX ? sign * sideOffset : 0),
                        );
                        side.castShadow = true;
                        side.receiveShadow = true;
                        mapGroup.add(side);
                    }
                } else {
                    const alongX = dz === 0;
                    const stubLength = CELL * 0.44;
                    const geometry = alongX
                        ? new THREE.BoxGeometry(stubLength, 0.08, 0.9)
                        : new THREE.BoxGeometry(0.9, 0.08, stubLength);
                    const stub = new THREE.Mesh(geometry, stubMaterial);
                    stub.position.set(
                        wx + dx * (CELL / 2 + stubLength / 2 - 0.04),
                        0.02,
                        wz + dz * (CELL / 2 + stubLength / 2 - 0.04),
                    );
                    mapGroup.add(stub);
                }

                continue;
            }

            if (drawnWalls.has(edgeKey)) {
                continue;
            }

            drawnWalls.add(edgeKey);

            const alongX = dz === 0;
            const wall = new THREE.Mesh(alongX ? wallXGeometry : wallZGeometry, wallMaterial);
            wall.position.set(wx + dx * (CELL / 2), height / 2, wz + dz * (CELL / 2));
            wall.castShadow = true;
            wall.receiveShadow = true;
            mapGroup.add(wall);
        }

        if (room.kind && (!firstPerson.value || !room.current)) {
            addMarker(room, wx, wz);
        }

        if (room.reachable) {
            addReachableRing(room, wx, wz, accent);
        }

        if (room.current) {
            addCurrentRune(wx, wz, accent2);
        }
    }

    if (!initialisedFacing) {
        initialisedFacing = true;
        ensureFacing();
        yaw = yawTarget;
    }

    focusCurrentRoom(false);
}

function loadTexture(url, onReady) {
    if (!url) {
        return;
    }

    const loader = new THREE.TextureLoader();
    loader.load(
        url,
        (texture) => {
            if (disposed) {
                texture.dispose();

                return;
            }

            texture.colorSpace = THREE.SRGBColorSpace;
            texture.magFilter = THREE.NearestFilter;
            texture.minFilter = THREE.LinearMipmapLinearFilter;
            texture.anisotropy = Math.min(4, renderer?.capabilities.getMaxAnisotropy() ?? 1);
            onReady(texture);
        },
        undefined,
        () => {
            // El retrato no cargó: el santo queda oculto, sin romper la escena.
        },
    );
}

function rebuildParty() {
    if (partyGroup === null) {
        return;
    }

    clearGroup(partyGroup);
    partySprites.length = 0;

    const slots = [
        { x: -0.62, z: 0.22 },
        { x: 0.62, z: 0.22 },
        { x: 0, z: -0.48 },
    ];

    props.team.slice(0, 3).forEach((card, index) => {
        const slot = new THREE.Group();
        const offset = slots[index] ?? slots[2];
        slot.position.set(offset.x, 0, offset.z);

        const rarity = new THREE.Color(card.rarity_color || '#c8aa6e');
        const aura = new THREE.Mesh(
            new THREE.RingGeometry(0.26, 0.4, 24),
            new THREE.MeshBasicMaterial({
                color: rarity,
                transparent: true,
                opacity: 0.45,
                side: THREE.DoubleSide,
                depthWrite: false,
            }),
        );
        aura.rotation.x = -Math.PI / 2;
        aura.position.y = 0.035;

        const material = new THREE.SpriteMaterial({
            color: 0xffffff,
            transparent: true,
            alphaTest: 0.08,
            depthWrite: true,
        });
        const sprite = new THREE.Sprite(material);
        const spriteHeight = 1.22;
        sprite.scale.set(spriteHeight * ART_RATIO, spriteHeight, 1);
        sprite.position.y = spriteHeight / 2 + 0.05;
        sprite.visible = false;
        sprite.userData.baseY = sprite.position.y;

        loadTexture(card.art, (texture) => {
            material.map = texture;
            material.needsUpdate = true;
            sprite.visible = true;
        });

        slot.add(aura, sprite);
        partyGroup.add(slot);
        partySprites.push({ sprite, phase: index * 1.9 });
    });
}

function rebuildDecor() {
    const map = props.map ?? {};
    const width = Math.max(1, map.width ?? 9);
    const height = Math.max(1, map.height ?? 3);
    const extent = Math.max(width, height) * CELL;
    const accent = new THREE.Color(props.accent2 || '#c8aa6e');

    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(extent * 2.2 + 20, extent * 2.2 + 20),
        new THREE.MeshStandardMaterial({
            color: 0x03070d,
            roughness: 1,
            metalness: 0,
        }),
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.31;
    ground.receiveShadow = true;
    mapGroup.add(ground);

    const grid = new THREE.GridHelper(extent * 2 + 18, Math.round((extent * 2 + 18) / CELL), accent, 0x1b3242);
    grid.position.y = -0.295;
    grid.material.transparent = true;
    grid.material.opacity = 0.13;
    grid.material.depthWrite = false;
    mapGroup.add(grid);
}

function init() {
    if (host.value === null) {
        return;
    }

    const probe = document.createElement('canvas');
    const context = probe.getContext('webgl2') ?? probe.getContext('webgl');

    if (context === null) {
        throw new Error('WebGL no disponible');
    }

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x04080f);
    scene.fog = new THREE.Fog(0x04080f, 18, 58);
    applyFog();

    camera = new THREE.PerspectiveCamera(50, 1, 0.1, 220);

    renderer = new THREE.WebGLRenderer({ antialias: false, powerPreference: 'high-performance' });
    renderer.setPixelRatio(1);
    sizeRenderer(host.value.clientWidth || 1, host.value.clientHeight || 1);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.08;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.cursor = 'grab';
    renderer.domElement.style.imageRendering = 'pixelated';
    renderer.domElement.style.touchAction = 'none';
    host.value.appendChild(renderer.domElement);

    timer.connect(document);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = !reduceMotion;
    controls.dampingFactor = 0.075;
    controls.enablePan = false;
    controls.minDistance = 4;
    controls.maxDistance = 42;
    controls.minPolarAngle = 0.32;
    controls.maxPolarAngle = Math.PI * 0.46;
    controls.rotateSpeed = 0.75;
    controls.zoomSpeed = 0.9;
    controls.target.set(0, 0.5, 0);
    controls.enabled = !firstPerson.value;

    const hemisphere = new THREE.HemisphereLight(0x8fb6d9, 0x03060b, 1.15);
    scene.add(hemisphere);

    const ambient = new THREE.AmbientLight(0x37506b, 0.55);
    scene.add(ambient);

    const key = new THREE.DirectionalLight(0xcfe8ff, 2.2);
    key.position.set(9, 17, 13);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    key.shadow.camera.left = -28;
    key.shadow.camera.right = 28;
    key.shadow.camera.top = 28;
    key.shadow.camera.bottom = -28;
    key.shadow.camera.near = 1;
    key.shadow.camera.far = 70;
    key.shadow.bias = -0.0008;
    scene.add(key);
    scene.add(key.target);

    const rim = new THREE.DirectionalLight(0x2b7a8f, 0.9);
    rim.position.set(-12, 8, -10);
    scene.add(rim);

    currentLight = new THREE.PointLight(new THREE.Color(props.accent), 42, 12, 2);
    currentLight.position.set(0, 2.6, 0);
    scene.add(currentLight);

    eyeLight = new THREE.PointLight(0xffd9a0, 3.5, 9, 2);
    eyeLight.visible = firstPerson.value;
    eyeLight.position.set(0, EYE_HEIGHT + 0.15, 0);
    scene.add(eyeLight);

    applyLighting();

    mapGroup = new THREE.Group();
    scene.add(mapGroup);

    partyGroup = new THREE.Group();
    partyGroup.visible = !firstPerson.value;
    scene.add(partyGroup);

    window.addEventListener('keydown', onKeyDown);

    renderer.domElement.addEventListener('pointerdown', onPointerDown);
    renderer.domElement.addEventListener('pointerup', onPointerUp);
    renderer.domElement.addEventListener('pointermove', onPointerMove);
    renderer.domElement.addEventListener('pointerleave', onPointerLeave);
    renderer.domElement.addEventListener('click', onClick);

    resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host.value);

    resize();
}

function sizeRenderer(width, height) {
    renderer.setSize(
        Math.max(1, Math.round(width * RENDER_SCALE)),
        Math.max(1, Math.round(height * RENDER_SCALE)),
        false,
    );
}

function resize() {
    if (renderer === null || camera === null || host.value === null) {
        return;
    }

    const width = host.value.clientWidth || 1;
    const height = host.value.clientHeight || 1;

    sizeRenderer(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
}

function tick() {
    if (disposed) {
        return;
    }

    raf = requestAnimationFrame(tick);

    timer.update();

    const delta = Math.min(timer.getDelta(), 0.05);
    const time = timer.getElapsed();

    for (const marker of markers.values()) {
        if (reduceMotion) {
            marker.group.position.y = marker.baseY;
        } else {
            marker.spinner.rotation.y += delta * marker.speed;
            marker.group.position.y = marker.baseY + Math.sin(time * 1.8 + marker.phase) * 0.07;
        }
    }

    for (const ring of rings) {
        ring.mesh.material.opacity = reduceMotion ? 0.4 : 0.35 + Math.sin(time * 2.8 + ring.phase) * 0.2;

        if (ring.spin && !reduceMotion) {
            ring.mesh.rotation.z += delta * ring.spin;
        }
    }

    if (partyGroup !== null) {
        if (reduceMotion) {
            partyGroup.position.copy(partyTarget);
        } else {
            partyGroup.position.lerp(partyTarget, Math.min(1, delta * 5.5));
        }
    }

    if (props.walking && !reduceMotion) {
        walkTime += delta * 9;
    }

    for (const { sprite, phase } of partySprites) {
        const base = sprite.userData.baseY ?? 0.66;
        const bob = props.walking && !reduceMotion ? Math.abs(Math.sin(walkTime + phase)) * 0.1 : 0;
        const nextY = base + bob;

        if (reduceMotion) {
            sprite.position.y = nextY;
        } else {
            sprite.position.y += (nextY - sprite.position.y) * Math.min(1, delta * 10);
        }
    }

    if (firstPerson.value) {
        applyFirstPersonCamera(delta);
    } else {
        if (cameraTween !== null) {
            cameraTween.progress = reduceMotion ? 1 : Math.min(1, cameraTween.progress + delta / 0.55);
            const eased = reduceMotion ? 1 : 1 - Math.pow(1 - cameraTween.progress, 3);
            controls.target.lerpVectors(cameraTween.fromTarget, cameraTween.toTarget, eased);
            camera.position.lerpVectors(cameraTween.fromPosition, cameraTween.toPosition, eased);

            if (cameraTween.progress >= 1) {
                follow = cameraTween.followAfter;
                cameraTween = null;
            }
        } else if (follow) {
            const before = controls.target.clone();

            if (reduceMotion) {
                controls.target.copy(focusPoint);
            } else {
                controls.target.lerp(focusPoint, Math.min(1, delta * 4));
            }

            camera.position.add(controls.target.clone().sub(before));
        }

        controls.update();
    }

    renderer.render(scene, camera);
}

function cleanup() {
    disposed = true;
    cancelAnimationFrame(raf);

    timer.disconnect();
    window.removeEventListener('keydown', onKeyDown);
    resizeObserver?.disconnect();
    resizeObserver = null;

    if (renderer !== null) {
        renderer.domElement.removeEventListener('pointerdown', onPointerDown);
        renderer.domElement.removeEventListener('pointerup', onPointerUp);
        renderer.domElement.removeEventListener('pointermove', onPointerMove);
        renderer.domElement.removeEventListener('pointerleave', onPointerLeave);
        renderer.domElement.removeEventListener('click', onClick);
    }

    if (scene !== null) {
        disposeObject(scene);
    }

    controls?.dispose();
    renderer?.dispose();
    renderer?.domElement?.remove();

    controls = null;
    renderer = null;
    camera = null;
    scene = null;
    mapGroup = null;
    partyGroup = null;
    currentLight = null;
    eyeLight = null;
    roomMeshes.length = 0;
    pickables.length = 0;
    markers.clear();
    rings.length = 0;
    partySprites.length = 0;
}

onMounted(() => {
    try {
        init();
        rebuildMap();
        rebuildDecor();
        rebuildParty();

        if (firstPerson.value) {
            focusCurrentRoom(true);
            applyFirstPersonCamera(1);
        } else {
            snapView();
        }

        tick();
    } catch {
        failed.value = true;
        cleanup();
        emit('unsupported');
    }
});

watch(
    () => props.map,
    () => {
        if (scene === null) {
            return;
        }

        rebuildMap();
        rebuildDecor();
        applyHover();
    },
    { deep: false },
);

watch(
    () => props.team,
    () => {
        rebuildParty();
    },
    { deep: false },
);

onBeforeUnmount(cleanup);
</script>

<template>
    <div ref="host" class="mz-scene" :class="{ 'is-failed': failed }" tabindex="0" aria-label="Escena del laberinto">
        <div v-if="failed" class="mz-scene-fallback">
            <strong>No se pudo iniciar la vista 3D</strong>
            <span>Tu navegador no tiene WebGL disponible. Puedes seguir usando el mapa plano.</span>
        </div>

        <template v-else>
            <div class="mz-scene-tools">
                <div class="mz-view-toggle" role="group" aria-label="Cámara del laberinto">
                    <button
                        type="button"
                        class="mz-btn mz-btn--ghost mz-btn--sm mz-view-btn"
                        :class="{ 'is-active': firstPerson }"
                        :aria-pressed="firstPerson"
                        title="Vista en primera persona"
                        @click="setCamera('first')"
                    >
                        1ª persona
                    </button>
                    <button
                        type="button"
                        class="mz-btn mz-btn--ghost mz-btn--sm mz-view-btn"
                        :class="{ 'is-active': !firstPerson }"
                        :aria-pressed="!firstPerson"
                        title="Vista orbital del laberinto"
                        @click="setCamera('orbit')"
                    >
                        Órbita
                    </button>
                </div>

                <template v-if="!firstPerson">
                    <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" title="Centrar en el equipo" @click="resetView">
                        Centrar
                    </button>
                    <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" title="Ver todo el piso" @click="overview">
                        Piso
                    </button>
                </template>
            </div>

            <div v-if="firstPerson" class="mz-mini" aria-hidden="true">
                <div class="mz-mini-compass">
                    <span
                        v-for="(label, index) in FACING_SHORT"
                        :key="label"
                        class="mz-mini-dir"
                        :class="{ 'is-active': index === facing }"
                    >
                        {{ label }}
                    </span>
                </div>

                <div
                    class="mz-mini-grid"
                    :style="{
                        gridTemplateColumns: `repeat(${miniWidth}, 10px)`,
                        gridTemplateRows: `repeat(${miniHeight}, 10px)`,
                    }"
                >
                    <span
                        v-for="room in miniRooms"
                        :key="room.id"
                        class="mz-mini-cell"
                        :class="{ 'is-current': room.current, 'is-reachable': room.reachable }"
                        :style="{ gridColumn: room.x + 1, gridRow: room.y + 1, background: miniFill(room) }"
                    >
                        <b
                            v-if="room.current"
                            class="mz-mini-arrow"
                            :style="{ transform: `rotate(${facing * 90}deg)` }"
                        >
                            ▲
                        </b>
                    </span>
                </div>

                <p class="mz-mini-ahead">
                    <span class="mz-mini-face">{{ facingShort }}</span>
                    {{ aheadInfo.label }}
                </p>
            </div>

            <p class="mz-scene-hint">
                {{ firstPerson
                    ? 'W/↑ avanzar · S/↓ retroceder · A/D o ←/→ girar · Pulsa una sala conectada'
                    : 'Arrastra para girar · Rueda para acercar · Pulsa una sala conectada' }}
            </p>

            <div v-if="firstPerson" class="mz-scene-pad" role="group" aria-label="Movimiento en primera persona">
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-scene-pad-btn" title="Girar a la izquierda" aria-label="Girar a la izquierda" @click="turn(-1)">
                    ↺
                </button>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-scene-pad-btn" title="Avanzar" aria-label="Avanzar" :disabled="!canStepForward" @click="stepForward">
                    ↑
                </button>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-scene-pad-btn" title="Retroceder" aria-label="Retroceder" :disabled="!canStepBackward" @click="stepBackward">
                    ↓
                </button>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-scene-pad-btn" title="Girar a la derecha" aria-label="Girar a la derecha" @click="turn(1)">
                    ↻
                </button>
            </div>

            <p v-if="hoveredRoom" class="mz-scene-hover">
                {{ KIND_LABELS[hoveredRoom.kind] ?? 'Sala' }}
                <span v-if="hoveredRoom.cleared"> · despejada</span>
                <span v-else-if="hoveredRoom.reachable" class="mz-scene-hover-go"> · ir</span>
            </p>
        </template>
    </div>
</template>
