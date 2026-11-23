<script setup>
import { computed, reactive, ref } from 'vue';
import { nodeGlyph, nodeLabel } from '../lib/dungeonNodes';

const props = defineProps({
    map: { type: Object, required: true },
    team: { type: Array, default: () => [] },
    walking: { type: Boolean, default: false },
    canChoose: { type: Boolean, default: true },
});

const emit = defineEmits(['move']);

const ZOOM_MIN = 1;
const ZOOM_MAX = 2.5;

const zoom = ref(1);
const pan = reactive({ x: 0, y: 0 });
const drag = reactive({ active: false, x: 0, y: 0 });

const rooms = computed(() => props.map.rooms ?? []);
const width = computed(() => Math.max(1, props.map.width ?? 9));
const height = computed(() => Math.max(1, props.map.height ?? 3));

const roomById = computed(() => new Map(rooms.value.map((room) => [room.id, room])));
const currentRoom = computed(
    () => roomById.value.get(props.map.room) ?? rooms.value.find((room) => room.current) ?? null,
);

const boardStyle = computed(() => ({
    aspectRatio: `${width.value} / ${height.value}`,
    transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom.value})`,
}));

const corridors = computed(() => {
    const segments = [];
    const drawn = new Set();

    for (const room of rooms.value) {
        for (const otherId of room.links ?? []) {
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

function cellX(x) {
    return ((x + 0.5) / width.value) * 100;
}

function cellY(y) {
    return ((y + 0.5) / height.value) * 100;
}

function roomLabel(room) {
    return room.known ? nodeLabel(room.kind) : 'Sin explorar';
}

function roomGlyph(room) {
    return room.known ? nodeGlyph(room.kind) : '?';
}

function setZoom(value) {
    zoom.value = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, Math.round(value * 100) / 100));

    if (zoom.value <= ZOOM_MIN) {
        pan.x = 0;
        pan.y = 0;
    }
}

function zoomBy(step) {
    setZoom(zoom.value + step);
}

function resetView() {
    setZoom(ZOOM_MIN);
}

function onWheel(event) {
    if (!event.ctrlKey && !event.metaKey) {
        return;
    }

    event.preventDefault();
    zoomBy(event.deltaY < 0 ? 0.2 : -0.2);
}

function onDragStart(event) {
    if (zoom.value <= ZOOM_MIN || event.button !== 0) {
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
</script>

<template>
    <div
        class="mz-viewport"
        :class="{ 'is-zoomed': zoom > ZOOM_MIN, 'is-dragging': drag.active }"
        @wheel="onWheel"
        @pointerdown="onDragStart"
        @pointermove="onDragMove"
        @pointerup="onDragEnd"
        @pointercancel="onDragEnd"
    >
        <div class="mz-board" :class="{ 'is-dragging': drag.active }" :style="boardStyle">
            <svg class="mz-links" :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none" aria-hidden="true">
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
                v-for="room in rooms"
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
                :aria-label="roomLabel(room)"
                :title="roomLabel(room)"
                @click="emit('move', room)"
            >
                <span class="mz-node-glyph" aria-hidden="true">{{ roomGlyph(room) }}</span>
                <span v-if="room.current" class="mz-node-label is-here">Aquí</span>
                <span v-else-if="room.known" class="mz-node-label">{{ roomLabel(room) }}</span>
                <span v-if="room.reachable && canChoose" class="mz-node-tag">Ir</span>
            </button>

            <div
                class="mz-party"
                :class="{ 'is-walking': walking }"
                :style="{ left: `${cellX(currentRoom?.x ?? 0)}%`, top: `${cellY(currentRoom?.y ?? 0)}%` }"
                aria-hidden="true"
            >
                <span v-for="card in team" :key="card.id" class="mz-walker">
                    <img :src="card.art" :alt="card.name" draggable="false" />
                </span>
                <span v-if="team.length === 0" class="mz-walker mz-walker--empty">+</span>
            </div>
        </div>

        <div class="mz-map-zoom mz-zoom" role="group" aria-label="Zoom del laberinto">
            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" aria-label="Alejar" :disabled="zoom <= ZOOM_MIN" @click="zoomBy(-0.2)">−</button>
            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm mz-zoom-level" title="Centrar el mapa" @click="resetView">
                {{ Math.round(zoom * 100) }}%
            </button>
            <button type="button" class="mz-btn mz-btn--ghost mz-btn--sm" aria-label="Acercar" :disabled="zoom >= ZOOM_MAX" @click="zoomBy(0.2)">+</button>
        </div>
    </div>
</template>
