<template>
  <ol class="ui-pasos" aria-label="Pasos del formulario">
    <li
      v-for="(p, i) in pasos"
      :key="p"
      class="ui-paso"
      :class="{ 'is-actual': i + 1 === actual, 'is-hecho': i + 1 < actual }"
      :aria-current="i + 1 === actual ? 'step' : undefined"
    >
      <span class="ui-paso__num">
        <i v-if="i + 1 < actual" class="ti ti-check" aria-hidden="true"></i>
        <template v-else>{{ i + 1 }}</template>
      </span>
      <span class="ui-paso__texto">{{ p }}</span>
    </li>
  </ol>
</template>

<script setup>
// Indicador numerado de pasos para formularios largos.
defineProps({
  pasos: { type: Array, required: true }, // ['Productor y nombre', 'Terreno', 'Ubicación']
  actual: { type: Number, default: 1 }, // empieza en 1
});
</script>

<style scoped>
.ui-pasos {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ui-paso {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding-top: 10px;
  border-top: 4px solid #dce6ed;
  color: #4a6070;
}

.ui-paso.is-actual,
.ui-paso.is-hecho {
  border-top-color: #1a5c3e;
}

.ui-paso__num {
  flex: none;
  width: 26px;
  height: 26px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #dce6ed;
  color: #4a6070;
  font-size: 13px;
  font-weight: 800;
}

.is-actual .ui-paso__num {
  background: #1a5c3e;
  color: #fff;
}

.is-hecho .ui-paso__num {
  background: #a8e6c6;
  color: #0d3d2b;
}

.ui-paso__texto {
  font-size: 13px;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.is-actual .ui-paso__texto {
  color: #111827;
}

@media (max-width: 560px) {
  .ui-paso__texto {
    display: none;
  }

  .is-actual .ui-paso__texto {
    display: inline;
  }
}
</style>
