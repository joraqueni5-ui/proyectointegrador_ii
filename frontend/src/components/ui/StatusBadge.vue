<template>
  <span class="ui-estado" :style="{ background: colores.fondo, color: colores.texto }">
    <span class="ui-estado__punto" :style="{ background: colores.punto }" aria-hidden="true"></span>
    {{ texto || etiquetaDeEstado(estado) }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import { TONOS, etiquetaDeEstado, tonoDeEstado } from '@/config/estados';

// Un color fijo por estado en todo el sistema (ver config/estados.js).
// `tono` fuerza un color; `texto` cambia lo que se muestra (por ejemplo "Con alerta").
const props = defineProps({
  estado: { type: String, default: '' },
  tono: { type: String, default: '' },
  texto: { type: String, default: '' },
});

const colores = computed(() => TONOS[props.tono] || TONOS[tonoDeEstado(props.estado)]);
</script>

<style scoped>
.ui-estado {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.3;
  white-space: nowrap;
}

.ui-estado__punto {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  flex: none;
}
</style>
