<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

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

const CELL = 2.6;
const WALL_HEIGHT = 0.95;
const ART_RATIO = 24 / 28;

const KIND_COLORS = {
    camp: 0xd7b878,
    altar: 0x7fe3d8,
    battle: 0xe84057,
    boss: 0xff4d6a,
    trap: 0xe0a45c,
    treasure: 0xf0d27a,
    gospel: 0xbfe5ff,
};

const KIND_LABELS = {
    camp: 'Campamento',
    altar: 'Altar',
    battle: 'Combate',
    boss: 'Jefe',
    trap: 'Trampa',
    treasure: 'Tesoro',
    gospel: 'Fragmento',
};

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

const timer = new THREE.Timer();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const partyTarget = new THREE.Vector3();
const focusPoint = new THREE.Vector3();
const mapCenter = new THREE.Vector3();

const roomMeshes = [];
const markers = new Map();
const rings = [];
const partySprites = [];

const onPointerDown = (event) => {
    if (event.button !== 0) {
        return;
    }

    pointerDown = { x: event.clientX, y: event.clientY };
    dragDistance = 0;

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

    const hit = raycaster.intersectObjects(roomMeshes, false)[0];

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
        node.geometry?.dispose?.();

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

function addMarker(room, wx, wz, color) {
    const dim = room.cleared && !room.current;
    const material = new THREE.MeshStandardMaterial({
        color,
        emissive: color,
        emissiveIntensity: dim ? 0.35 : 1.15,
        metalness: 0.55,
        roughness: 0.35,
        transparent: dim,
        opacity: dim ? 0.6 : 1,
    });

    const spinner = new THREE.Group();
    let spinSpeed = 0.8;

    switch (room.kind) {
        case 'camp': {
            const ring = new THREE.Mesh(new THREE.TorusGeometry(0.3, 0.065, 8, 22), material);
            ring.rotation.x = Math.PI / 2;
            const flame = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.3, 5), material);
            flame.position.y = 0.08;
            spinner.add(ring, flame);
            spinner.rotation.x = 0.3;
            spinSpeed = 0.55;
            break;
        }
        case 'altar': {
            const column = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.29, 0.62, 6), material);
            const halo = new THREE.Mesh(new THREE.TorusGeometry(0.24, 0.035, 8, 20), material);
            halo.position.y = 0.43;
            halo.rotation.x = Math.PI / 2;
            spinner.add(column, halo);
            spinSpeed = 0.45;
            break;
        }
        case 'trap': {
            const spike = new THREE.Mesh(new THREE.ConeGeometry(0.27, 0.5, 4), material);
            spike.rotation.y = Math.PI / 4;
            spinner.add(spike);
            spinSpeed = 0.6;
            break;
        }
        case 'treasure': {
            const chest = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.32, 0.44), material);
            const lid = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.08, 0.5), material);
            lid.position.y = 0.2;
            spinner.add(chest, lid);
            spinSpeed = 0.7;
            break;
        }
        case 'gospel': {
            const spine = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.46, 0.09), material);
            const crossbar = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.09, 0.09), material);
            crossbar.position.y = 0.08;
            spinner.add(spine, crossbar);
            spinSpeed = 0.5;
            break;
        }
        case 'boss': {
            const core = new THREE.Mesh(new THREE.OctahedronGeometry(0.42, 0), material);
            const crown = new THREE.Mesh(new THREE.TorusGeometry(0.52, 0.035, 8, 28), material);
            crown.rotation.x = Math.PI / 2;
            spinner.add(core, crown);
            spinSpeed = 1.5;
            break;
        }
        default: {
            const blade = new THREE.Mesh(new THREE.OctahedronGeometry(0.3, 0), material);
            spinner.add(blade);
            spinSpeed = 1;
        }
    }

    const group = new THREE.Group();
    group.position.set(wx, 0.95, wz);
    group.add(spinner);
    mapGroup.add(group);

    markers.set(room.id, {
        group,
        spinner,
        baseY: 0.95,
        phase: room.id * 1.7,
        speed: spinSpeed,
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

    const floorGeometry = new THREE.BoxGeometry(CELL * 0.96, 0.24, CELL * 0.96);
    const wallXGeometry = new THREE.BoxGeometry(CELL + 0.2, WALL_HEIGHT, 0.18);
    const wallZGeometry = new THREE.BoxGeometry(0.18, WALL_HEIGHT, CELL + 0.2);
    const wallMaterial = new THREE.MeshStandardMaterial({
        color: 0x1b2a3a,
        roughness: 0.92,
        metalness: 0.06,
    });
    const corridorMaterial = new THREE.MeshStandardMaterial({
        color: 0x122430,
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
                    const geometry = alongX
                        ? new THREE.BoxGeometry(CELL + 0.16, 0.16, 1.15)
                        : new THREE.BoxGeometry(1.15, 0.16, CELL + 0.16);
                    const corridor = new THREE.Mesh(geometry, corridorMaterial);
                    corridor.position.set(
                        (wx + worldX(nx)) / 2,
                        -0.08,
                        (wz + worldZ(ny)) / 2,
                    );
                    corridor.receiveShadow = true;
                    mapGroup.add(corridor);
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
            wall.position.set(wx + dx * (CELL / 2), WALL_HEIGHT / 2, wz + dz * (CELL / 2));
            wall.castShadow = true;
            wall.receiveShadow = true;
            mapGroup.add(wall);
        }

        if (room.kind) {
            addMarker(room, wx, wz, kindColor);
        }

        if (room.reachable) {
            addReachableRing(room, wx, wz, accent);
        }

        if (room.current) {
            addCurrentRune(wx, wz, accent2);
        }
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

    camera = new THREE.PerspectiveCamera(50, 1, 0.1, 220);

    renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(host.value.clientWidth || 1, host.value.clientHeight || 1);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.08;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.cursor = 'grab';
    renderer.domElement.style.touchAction = 'none';
    host.value.appendChild(renderer.domElement);

    timer.connect(document);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.075;
    controls.enablePan = false;
    controls.minDistance = 4;
    controls.maxDistance = 42;
    controls.minPolarAngle = 0.32;
    controls.maxPolarAngle = Math.PI * 0.46;
    controls.rotateSpeed = 0.75;
    controls.zoomSpeed = 0.9;
    controls.target.set(0, 0.5, 0);

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

    mapGroup = new THREE.Group();
    scene.add(mapGroup);

    partyGroup = new THREE.Group();
    scene.add(partyGroup);

    renderer.domElement.addEventListener('pointerdown', onPointerDown);
    renderer.domElement.addEventListener('pointerup', onPointerUp);
    renderer.domElement.addEventListener('pointermove', onPointerMove);
    renderer.domElement.addEventListener('pointerleave', onPointerLeave);
    renderer.domElement.addEventListener('click', onClick);

    resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(host.value);

    resize();
}

function resize() {
    if (renderer === null || camera === null || host.value === null) {
        return;
    }

    const width = host.value.clientWidth || 1;
    const height = host.value.clientHeight || 1;

    renderer.setSize(width, height, false);
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
        marker.spinner.rotation.y += delta * marker.speed;
        marker.group.position.y = marker.baseY + Math.sin(time * 1.8 + marker.phase) * 0.07;
    }

    for (const ring of rings) {
        ring.mesh.material.opacity = 0.35 + Math.sin(time * 2.8 + ring.phase) * 0.2;

        if (ring.spin) {
            ring.mesh.rotation.z += delta * ring.spin;
        }
    }

    if (cameraTween !== null) {
        cameraTween.progress = Math.min(1, cameraTween.progress + delta / 0.55);
        const eased = 1 - Math.pow(1 - cameraTween.progress, 3);
        controls.target.lerpVectors(cameraTween.fromTarget, cameraTween.toTarget, eased);
        camera.position.lerpVectors(cameraTween.fromPosition, cameraTween.toPosition, eased);

        if (cameraTween.progress >= 1) {
            follow = cameraTween.followAfter;
            cameraTween = null;
        }
    } else if (follow) {
        const before = controls.target.clone();
        controls.target.lerp(focusPoint, Math.min(1, delta * 4));
        camera.position.add(controls.target.clone().sub(before));
    }

    if (partyGroup !== null) {
        partyGroup.position.lerp(partyTarget, Math.min(1, delta * 5.5));
    }

    if (props.walking) {
        walkTime += delta * 9;
    }

    for (const { sprite, phase } of partySprites) {
        const base = sprite.userData.baseY ?? 0.66;
        const bob = props.walking ? Math.abs(Math.sin(walkTime + phase)) * 0.1 : 0;
        sprite.position.y += (base + bob - sprite.position.y) * Math.min(1, delta * 10);
    }

    controls.update();
    renderer.render(scene, camera);
}

function cleanup() {
    disposed = true;
    cancelAnimationFrame(raf);

    timer.disconnect();
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
    roomMeshes.length = 0;
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
        snapView();
        tick();
    } catch (error) {
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
    <div ref="host" class="mz-scene" :class="{ 'is-failed': failed }">
        <div v-if="failed" class="mz-scene-fallback">
            <strong>No se pudo iniciar la vista 3D</strong>
            <span>Tu navegador no tiene WebGL disponible. Puedes seguir usando el mapa plano.</span>
        </div>

        <template v-else>
            <div class="mz-scene-tools">
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" title="Centrar en el equipo" @click="resetView">
                    Centrar
                </button>
                <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" title="Ver todo el piso" @click="overview">
                    Piso
                </button>
            </div>

            <p class="mz-scene-hint">Arrastra para girar · Rueda para acercar · Pulsa una sala conectada</p>

            <p v-if="hoveredRoom" class="mz-scene-hover">
                {{ KIND_LABELS[hoveredRoom.kind] ?? 'Sala' }}
                <span v-if="hoveredRoom.cleared"> · despejada</span>
                <span v-else-if="hoveredRoom.reachable" class="mz-scene-hover-go"> · ir</span>
            </p>
        </template>
    </div>
</template>
