<template>
  <header class="ui-ph">
    <div class="ui-ph__texto">
      <nav v-if="migas.length" class="ui-ph__migas" aria-label="Ruta de navegación">
        <template v-for="(m, i) in migas" :key="m.texto">
          <RouterLink v-if="m.to && i < migas.length - 1" :to="m.to">{{ m.texto }}</RouterLink>
          <span v-else :aria-current="i === migas.length - 1 ? 'page' : undefined">{{ m.texto }}</span>
          <i v-if="i < migas.length - 1" class="ti ti-chevron-right" aria-hidden="true"></i>
        </template>
      </nav>
      <h1 class="ui-ph__titulo">{{ titulo }}</h1>
      <p v-if="resumen" class="ui-ph__resumen">{{ resumen }}</p>
    </div>

    <div v-if="$slots.default" class="ui-ph__accion"><slot /></div>
  </header>
</template>

<script setup>
// Encabezado de página: miga de pan pequeña, título, línea de resumen con números reales y UN botón
// principal a la derecha (slot).
defineProps({
  titulo: { type: String, required: true },
  resumen: { type: String, default: '' },
  migas: { type: Array, default: () => [] },
});
</script>

<style scoped>
.ui-ph {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.ui-ph__migas {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 12px;
  color: #4a6070;
}

.ui-ph__migas a {
  color: #4a6070;
  text-decoration: none;
}

.ui-ph__migas a:hover {
  color: #1a5c3e;
  text-decoration: underline;
}

.ui-ph__migas span[aria-current] {
  font-weight: 700;
  color: #111827;
}

.ui-ph__titulo {
  margin: 0;
  font-size: clamp(26px, 3vw, 30px);
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #111827;
}

.ui-ph__resumen {
  margin: 4px 0 0;
  font-size: 14px;
  color: #4a6070;
}

@media (max-width: 767px) {
  .ui-ph__accion {
    width: 100%;
  }

  .ui-ph__accion :deep(.ui-btn) {
    width: 100%;
  }
}
</style>
