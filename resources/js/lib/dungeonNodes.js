// Metadatos de los nodos del mapa, compartidos por el centro de mazmorras y el descenso.
const NODE_META = {
    camp: {
        color: '#d7b878',
        label: 'Campamento',
        glyph: '✦',
        status: 'Campamento seguro',
        hint: 'Descansa y ajusta tu equipo. Desde aquí puedes seguir explorando.',
    },
    altar: {
        color: '#7fe3d8',
        label: 'Altar',
        glyph: '✚',
        status: 'Altar de la Virgen',
        hint: 'La Virgen ofrece curación o una bendición para el próximo combate.',
    },
    trap: {
        color: '#e0a45c',
        label: 'Trampa',
        glyph: '⚠',
        status: 'Trampa en el camino',
        hint: 'Puedes cruzar y encajar el daño, o sortearla pagando Puntos de Fe.',
    },
    treasure: {
        color: '#f0d27a',
        label: 'Tesoro',
        glyph: '◆',
        status: 'Arca de limosnas',
        hint: 'Abre el arca para recoger los Puntos de Fe que guarda.',
    },
    gospel: {
        color: '#bfe5ff',
        label: 'Fragmento',
        glyph: '✞',
        status: 'Página de los Evangelios',
        hint: 'Lee el fragmento: recibes Puntos de Fe y una bendición para la próxima batalla.',
    },
    boss: { color: '#ff4d6a', label: 'Jefe', glyph: '☠', status: 'El jefe aguarda' },
    battle: { color: '#e84057', label: 'Combate', glyph: '⚔', status: 'Criaturas al acecho' },
};

const NODE_KINDS = ['camp', 'altar', 'battle', 'boss', 'trap', 'treasure', 'gospel'];

export function nodeKinds() {
    return NODE_KINDS.map((key) => ({ key, ...NODE_META[key] }));
}

const DEFAULT_META = { label: 'Sala', glyph: '⚔', status: 'Sala' };

export function nodeMeta(kind) {
    return NODE_META[kind] ?? DEFAULT_META;
}

export function nodeLabel(kind, fallback = DEFAULT_META.label) {
    return NODE_META[kind]?.label ?? fallback;
}

export function nodeGlyph(kind) {
    return nodeMeta(kind).glyph;
}

export function nodeStatus(kind) {
    return nodeMeta(kind).status;
}

export function nodeHint(kind) {
    return NODE_META[kind]?.hint ?? null;
}
