<template>
  <section class="ui-db">
    <h3 v-if="titulo" class="ui-db__titulo">{{ titulo }}</h3>
    <dl class="ui-db__datos">
      <div v-for="d in datos" :key="d.etiqueta" class="ui-db__dato" :class="{ 'ancho-completo': d.completo }">
        <dt>{{ d.etiqueta }}</dt>
        <dd>{{ d.valor === null || d.valor === undefined || d.valor === '' ? '—' : d.valor }}</dd>
      </div>
    </dl>
    <slot />
  </section>
</template>

<script setup>
// Bloque de datos en 2 columnas para el DetailDrawer. datos: [{ etiqueta, valor, completo? }].
defineProps({
  titulo: { type: String, default: '' },
  datos: { type: Array, default: () => [] },
});
</script>

<style scoped>
.ui-db {
  display: grid;
  gap: 12px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 16px;
}

.ui-db__titulo {
  margin: 0;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.ui-db__datos {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px 20px;
  margin: 0;
}

.ui-db__dato {
  min-width: 0;
}

.ui-db__dato.ancho-completo {
  grid-column: 1 / -1;
}

.ui-db__dato dt {
  font-size: 12px;
  color: #4a6070;
}

.ui-db__dato dd {
  margin: 2px 0 0;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  overflow-wrap: anywhere;
}

@media (max-width: 480px) {
  .ui-db__datos {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
