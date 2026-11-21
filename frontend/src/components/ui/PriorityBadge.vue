<template>
  <span
    v-if="nivel"
    class="ui-prioridad"
    :style="{ background: nivel.fondo, color: nivel.texto }"
    :title="`Prioridad ${nivel.nivel.toLowerCase()}: ${puntaje} de 100`"
  >
    <template v-if="soloNumero">{{ puntaje }}</template>
    <template v-else>{{ nivel.nivel }} · {{ puntaje }}</template>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { nivelPrioridad } from '@/config/estados';

// Insignia con el puntaje de la alerta: Urgente ≥ 80, Alta 60–79, Media 40–59, Baja < 40.
const props = defineProps({
  puntaje: { type: [Number, String], default: null },
  soloNumero: { type: Boolean, default: false },
});

const nivel = computed(() => (props.puntaje === null || props.puntaje === '' ? null : nivelPrioridad(props.puntaje)));
</script>

<style scoped>
.ui-prioridad {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 800;
  line-height: 1.3;
  white-space: nowrap;
}
</style>
