<template>
  <section class="ui-fs">
    <h3 v-if="titulo" class="ui-fs__titulo">{{ titulo }}</h3>
    <div class="ui-fs__cuerpo"><slot /></div>
  </section>
</template>

<script setup>
// Sección de un formulario con un título pequeño. `columnas` reparte los campos en una rejilla.
defineProps({
  titulo: { type: String, default: '' },
  columnas: { type: Number, default: 1 },
});
</script>

<style scoped>
.ui-fs {
  display: grid;
  gap: 12px;
}

.ui-fs__titulo {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.ui-fs__cuerpo {
  display: grid;
  grid-template-columns: repeat(v-bind(columnas), minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 560px) {
  .ui-fs__cuerpo {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
