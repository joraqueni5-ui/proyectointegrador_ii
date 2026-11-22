<template>
  <button type="button" class="pc" :class="{ 'is-seleccionada': seleccionada }" :aria-pressed="seleccionada" @click="$emit('seleccionar', parcela)">
    <span class="pc__foto" :class="{ 'sin-foto': !foto }">
      <img v-if="foto && !falloFoto" :src="foto" :alt="`Último diagnóstico de ${parcela.nombre_visible}`" loading="lazy" @error="falloFoto = true" />
      <i v-else class="ti ti-leaf" aria-hidden="true"></i>
      <span class="pc__punto" :style="{ background: sanidad.color }" :title="sanidad.etiqueta"></span>
    </span>

    <span class="pc__cuerpo">
      <span class="pc__fila">
        <strong class="pc__nombre">{{ parcela.nombre_visible }}</strong>
        <span class="pc__sup">{{ hectareas(parcela.superficie_ha) }}</span>
      </span>
      <span class="pc__sec">
        {{ productor }}
        <span class="codigo">{{ parcela.codigo_parcela }}</span>
        <span v-if="parcela.sin_nombre" class="pc__sin-nombre">sin nombre</span>
      </span>
      <span class="pc__estado" :style="{ color: sanidad.clave === 'alerta' ? '#9a3412' : undefined }">{{ lineaEstado(parcela) }}</span>
    </span>
  </button>
</template>

<script setup>
import { computed, ref } from 'vue';
import { urlArchivo } from '@/services/api';
import { hectareas } from '@/utils/formato';
import { lineaEstado, sanidadDe } from '@/utils/sanidad';

defineEmits(['seleccionar']);

const props = defineProps({
  parcela: { type: Object, required: true },
  seleccionada: { type: Boolean, default: false },
});

const falloFoto = ref(false);
const sanidad = computed(() => sanidadDe(props.parcela));
const foto = computed(() => (props.parcela.ultima_foto ? urlArchivo(props.parcela.ultima_foto) : ''));
const productor = computed(() => {
  const p = props.parcela.productor;

  return p ? [String(p.nombres || '').split(' ')[0], p.apellido_paterno].filter(Boolean).join(' ') || p.nombre_completo : '';
});
</script>

<style scoped>
.pc {
  display: flex;
  gap: 12px;
  width: 100%;
  padding: 10px;
  background: #fff;
  border: 2px solid #eef2f6;
  border-radius: 16px;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pc:hover {
  border-color: #cbd5e1;
}

.pc.is-seleccionada {
  border-color: #0d3d2b;
  box-shadow: 0 4px 16px rgba(13, 61, 43, 0.12);
}

.pc:focus-visible {
  outline: 3px solid #3da87a;
  outline-offset: 2px;
}

.pc__foto {
  position: relative;
  flex: none;
  width: 64px;
  height: 64px;
  border-radius: 12px;
  background: #edfaf4;
  display: grid;
  place-items: center;
  color: #2e7d5a;
  font-size: 26px;
}

.pc__foto img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 12px;
}

.pc__punto {
  position: absolute;
  right: -4px;
  bottom: -4px;
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 3px solid #fff;
}

.pc__cuerpo {
  display: grid;
  align-content: center;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.pc__fila {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.pc__nombre {
  font-size: 15px;
  font-weight: 800;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pc__sup {
  flex: none;
  font-size: 13px;
  font-weight: 700;
  color: #4a6070;
}

.pc__sec {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  color: #4a6070;
}

.pc__sin-nombre {
  padding: 1px 8px;
  border-radius: 999px;
  background: #fffbeb;
  color: #92400e;
  font-weight: 700;
}

.pc__estado {
  font-size: 12px;
  font-weight: 600;
  color: #4a6070;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
