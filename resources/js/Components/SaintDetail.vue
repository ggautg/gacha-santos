<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import SaintCard from './SaintCard.vue';
import { usePlayer } from '../lib/player';

const props = defineProps({
    saint: { type: Object, required: true },
    maxLevel: { type: Number, default: 20 },
    costBase: { type: Number, default: 45 },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'level-up']);

const player = usePlayer();

const mystery = computed(() => Boolean(props.saint.mystery));
const owned = computed(() => props.saint.owned);
const maxed = computed(() => owned.value && owned.value.level >= props.maxLevel);
const cost = computed(() => (owned.value ? props.costBase * owned.value.level : 0));
const affordable = computed(() => player.grace >= cost.value);
const xpPercent = computed(() => {
    if (!owned.value?.xp_next) {
        return 100;
    }

    return Math.min(100, Math.round((owned.value.xp / owned.value.xp_next) * 100));
});

const baseStats = computed(() => [
    { label: 'Virtud', value: props.saint.stats?.virtud ?? 0, meter: 'meter--red' },
    { label: 'Fe', value: props.saint.stats?.fe ?? 0, meter: '' },
    { label: 'Caridad', value: props.saint.stats?.caridad ?? 0, meter: 'meter--violet' },
    { label: 'Milagro', value: props.saint.stats?.milagro ?? 0, meter: 'meter--cyan' },
]);

const battleStats = computed(() => {
    if (!owned.value) {
        return [];
    }

    return [
        { label: 'Vida', value: owned.value.stats.hp },
        { label: 'Ataque', value: owned.value.stats.atk },
        { label: 'Defensa', value: owned.value.stats.def },
        { label: 'Velocidad', value: owned.value.stats.spd },
    ];
});

const levelUp = () => {
    if (owned.value && !maxed.value && affordable.value && !props.busy) {
        emit('level-up', props.saint);
    }
};

const close = () => emit('close');

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

onMounted(() => {
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div class="overlay" role="dialog" aria-modal="true" :aria-label="saint.name" @click.self="close">
            <div class="modal" :class="{ 'modal--mystery': mystery }" :style="{ '--rarity': saint.rarity_color }">
                <button type="button" class="icon-btn modal-close" aria-label="Cerrar" @click="close">✕</button>

                <div class="modal-grid">
                    <div class="modal-art" :style="{ '--rarity': saint.rarity_color }">
                        <SaintCard :saint="saint" :interactive="false" />
                    </div>

                    <div class="modal-body">
                        <template v-if="mystery">
                            <div>
                                <span class="eyebrow">Identidad oculta</span>
                                <h2 class="modal-name">Santo velado</h2>
                                <p class="modal-epithet">El velo aún no se ha rasgado</p>
                            </div>

                            <p class="quote-block">«Su rostro aguarda en el santuario.»</p>
                            <p class="bio">
                                Un santo que todavía no se te ha revelado. Ni su nombre, ni su rareza, ni su
                                historia pueden leerse mientras el velo cubra su retrato.
                            </p>

                            <div class="mystery-box">
                                <span class="mystery-mark" aria-hidden="true">?</span>
                                <div>
                                    <strong>El velo se rompe al orar</strong>
                                    <p>
                                        Cada oración puede revelar a este santo. Hasta entonces, su
                                        identidad permanece en secreto.
                                    </p>
                                </div>
                            </div>

                            <div class="owned-locked">
                                <p>Invócalo para descubrir su nombre, su historia y su poder.</p>
                                <Link href="/" class="btn btn--gold btn--sm" style="margin-top: 10px" @click="close">
                                    Ir al santuario
                                </Link>
                            </div>
                        </template>

                        <template v-else>
                            <div>
                                <span class="eyebrow">{{ saint.rarity_label }} · {{ saint.role }}</span>
                                <h2 class="modal-name">{{ saint.name }}</h2>
                                <p class="modal-epithet">{{ saint.epithet }}</p>
                            </div>

                            <div class="modal-tags">
                                <span class="tag">Fiesta <strong>{{ saint.feast_day }}</strong></span>
                                <span class="tag">Época <strong>{{ saint.era }}</strong></span>
                                <span class="tag">Región <strong>{{ saint.region }}</strong></span>
                            </div>

                            <p class="quote-block">«{{ saint.quote }}»</p>
                            <p class="bio">{{ saint.bio }}</p>

                            <div>
                                <span class="eyebrow">Patronazgo</span>
                                <p class="bio">{{ saint.patronage }}</p>
                            </div>

                            <div class="stat-list">
                                <div v-for="stat in baseStats" :key="stat.label" class="stat-row">
                                    <span class="stat-label">{{ stat.label }}</span>
                                    <div class="meter meter--thin" :class="stat.meter">
                                        <span :style="{ width: `${stat.value}%` }"></span>
                                    </div>
                                    <span class="stat-value">{{ stat.value }}</span>
                                </div>
                            </div>

                            <div class="skill-box">
                                <span class="skill-name">
                                    <span aria-hidden="true">✦</span>
                                    {{ saint.skill.name }}
                                </span>
                                <p>{{ saint.skill.text }}</p>
                            </div>

                            <div v-if="owned" class="owned-box">
                                <div class="owned-head">
                                    <span class="owned-level">Nivel {{ owned.level }} / {{ maxLevel }}</span>
                                    <span class="owned-power">Poder <strong>{{ owned.power }}</strong></span>
                                </div>

                                <div class="meter meter--thin" :class="maxed ? 'meter--cyan' : ''">
                                    <span :style="{ width: `${xpPercent}%` }"></span>
                                </div>
                                <p class="panel-note" style="margin-top: 6px">
                                    <template v-if="maxed">Santidad plena alcanzada.</template>
                                    <template v-else>{{ owned.xp }} / {{ owned.xp_next }} de experiencia · {{ owned.copies }} copia(s)</template>
                                </p>

                                <div class="modal-tags" style="margin-top: 10px">
                                    <span v-for="stat in battleStats" :key="stat.label" class="tag">
                                        {{ stat.label }} <strong>{{ stat.value }}</strong>
                                    </span>
                                </div>

                                <div class="owned-actions">
                                    <span class="panel-note">
                                        <template v-if="maxed">No necesita más luz.</template>
                                        <template v-else>
                                            Iluminar cuesta <strong class="gold">{{ cost }}</strong> Puntos de Fe.
                                        </template>
                                    </span>
                                    <button
                                        type="button"
                                        class="btn btn--gold btn--sm"
                                        :disabled="maxed || !affordable || busy"
                                        @click="levelUp"
                                    >
                                        <span v-if="busy">Iluminando…</span>
                                        <span v-else-if="maxed">Nivel máximo</span>
                                        <span v-else-if="!affordable">Faltan Puntos de Fe</span>
                                        <span v-else>Iluminar +1</span>
                                    </button>
                                </div>
                            </div>

                            <div v-else class="owned-locked">
                                <p>Aún no se te ha revelado este santo.</p>
                                <Link href="/" class="btn btn--ghost btn--sm" style="margin-top: 10px">
                                    Ir al santuario
                                </Link>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
