<template>
  <div class="ui-fb">
    <div class="ui-fb__buscador">
      <i class="ti ti-search" aria-hidden="true"></i>
      <label class="ui-fb__sr" :for="idBusqueda">{{ placeholder }}</label>
      <input
        :id="idBusqueda"
        type="search"
        autocomplete="off"
        maxlength="80"
        :value="busqueda"
        :placeholder="placeholder"
        @input="$emit('update:busqueda', $event.target.value)"
      />
    </div>

    <div v-if="filtros.length" class="ui-fb__pildoras" role="group" aria-label="Filtros rápidos">
      <button
        v-for="f in filtros"
        :key="f.valor"
        type="button"
        class="ui-fb__pildora"
        :class="{ 'is-activa': f.valor === activo }"
        :aria-pressed="f.valor === activo"
        @click="$emit('update:activo', f.valor)"
      >
        {{ f.etiqueta }}<template v-if="f.conteo !== undefined && f.conteo !== null"> · {{ f.conteo }}</template>
      </button>
    </div>

    <div v-if="$slots.mas" ref="raizMas" class="ui-fb__mas">
      <button type="button" class="ui-fb__boton-mas" :aria-expanded="masAbierto" @click="masAbierto = !masAbierto">
        <i class="ti ti-adjustments-horizontal" aria-hidden="true"></i> Más filtros
        <b v-if="filtrosExtra > 0" class="ui-fb__cuenta">{{ filtrosExtra }}</b>
      </button>
      <div v-if="masAbierto" class="ui-fb__panel" role="dialog" aria-label="Más filtros">
        <slot name="mas" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

// Barra de filtros en una sola línea: buscador amplio, filtros rápidos en píldoras con conteo
// (la activa en #0d3d2b) y "Más filtros" con un panel (slot `mas`).
defineProps({
  busqueda: { type: String, default: '' },
  placeholder: { type: String, default: 'Buscar…' },
  filtros: { type: Array, default: () => [] }, // [{ valor, etiqueta, conteo? }]
  activo: { type: [String, Number, null], default: null },
  filtrosExtra: { type: Number, default: 0 },
});

defineEmits(['update:busqueda', 'update:activo']);

const idBusqueda = `fb-${useId()}`;
const masAbierto = ref(false);
const raizMas = ref(null);

function clicFuera(e) {
  if (raizMas.value && !raizMas.value.contains(e.target)) masAbierto.value = false;
}

onMounted(() => document.addEventListener('mousedown', clicFuera));
onBeforeUnmount(() => document.removeEventListener('mousedown', clicFuera));
</script>

<style scoped>
.ui-fb {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.ui-fb__buscador {
  flex: 1 1 280px;
  min-width: 200px;
  max-width: 480px;
  display: flex;
  align-items: center;
  gap: 10px;
  height: 46px;
  padding: 0 14px;
  background: #fff;
  border: 2px solid #dce6ed;
  border-radius: 12px;
}

.ui-fb__buscador:focus-within {
  border-color: #3da87a;
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.14);
}

.ui-fb__buscador i {
  color: #4a6070;
  font-size: 18px;
}

.ui-fb__buscador input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-family: inherit;
  font-size: 14px;
  color: #111827;
}

.ui-fb__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.ui-fb__pildoras {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding: 2px;
}

.ui-fb__pildoras::-webkit-scrollbar {
  display: none;
}

.ui-fb__pildora {
  flex: none;
  min-height: 40px;
  padding: 0 16px;
  background: #fff;
  border: 2px solid #dce6ed;
  border-radius: 999px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.ui-fb__pildora:hover {
  border-color: #8ba3b5;
}

.ui-fb__pildora.is-activa {
  background: #0d3d2b;
  border-color: #0d3d2b;
  color: #fff;
}

.ui-fb__pildora:focus-visible,
.ui-fb__boton-mas:focus-visible {
  outline: 3px solid #3da87a;
  outline-offset: 2px;
}

.ui-fb__mas {
  position: relative;
  margin-left: auto;
}

.ui-fb__boton-mas {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  background: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 12px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.ui-fb__cuenta {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: #f07a2a;
  color: #111827;
  font-size: 11px;
  display: grid;
  place-items: center;
}

.ui-fb__panel {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  z-index: 30;
  width: min(360px, 90vw);
  padding: 16px;
  display: grid;
  gap: 14px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
}

@media (max-width: 767px) {
  .ui-fb {
    flex-wrap: wrap;
  }

  .ui-fb__buscador {
    flex-basis: 100%;
    max-width: none;
  }

  .ui-fb__pildoras {
    flex-basis: 100%;
  }
}
</style>
