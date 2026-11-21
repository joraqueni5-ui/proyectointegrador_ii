<template>
  <svg
    v-if="camino"
    class="ui-spark"
    :width="ancho"
    :height="alto"
    :viewBox="`0 0 ${ancho} ${alto}`"
    role="img"
    :aria-label="descripcion"
  >
    <path :d="area" :fill="color" fill-opacity="0.12" />
    <path :d="camino" fill="none" :stroke="color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    <circle :cx="ultimo[0]" :cy="ultimo[1]" r="2.8" :fill="color" />
  </svg>
</template>

<script setup>
import { computed } from 'vue';

// Mini gráfico de tendencia (SVG de 96×34) para las tarjetas KPI.
const props = defineProps({
  valores: { type: Array, default: () => [] },
  ancho: { type: Number, default: 96 },
  alto: { type: Number, default: 34 },
  color: { type: String, default: '#1a5c3e' },
  descripcion: { type: String, default: 'Tendencia reciente' },
});

const puntos = computed(() => {
  const v = props.valores.map(Number).filter(Number.isFinite);

  if (v.length < 2) return [];

  const min = Math.min(...v);
  const max = Math.max(...v);
  const rango = max - min || 1;
  const margen = 4;

  return v.map((n, i) => [
    margen + (i * (props.ancho - margen * 2)) / (v.length - 1),
    props.alto - margen - ((n - min) / rango) * (props.alto - margen * 2),
  ]);
});

const camino = computed(() => (puntos.value.length ? puntos.value.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ') : ''));
const area = computed(() => (camino.value ? `${camino.value} L${puntos.value.at(-1)[0]},${props.alto} L${puntos.value[0][0]},${props.alto} Z` : ''));
const ultimo = computed(() => puntos.value.at(-1) || [0, 0]);
</script>

<style scoped>
.ui-spark {
  display: block;
  flex: none;
}
</style>
