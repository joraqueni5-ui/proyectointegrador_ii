<template>
  <div class="reportes-resumen-section">
    <div class="reportes-section-head">
      <div>
        <h2>{{ reporte?.titulo || 'Resumen del reporte' }}</h2>
        <p>
          {{ totalFilas }} registros encontrados según los filtros aplicados.
        </p>
      </div>

      <span class="reportes-count-badge">
        {{ totalFilas }} registros
      </span>
    </div>

    <div class="reportes-resumen-grid">
      <div
        v-for="(valor, key) in reporte?.resumen || {}"
        :key="key"
        class="reporte-resumen-card"
      >
        <div class="reporte-resumen-icon">
          <i class="ti ti-chart-bar"></i>
        </div>

        <div>
          <span>{{ formatearEtiqueta(key) }}</span>
          <strong>{{ formatearValorResumen(valor) }}</strong>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  reporte: {
    type: Object,
    default: null,
  },
  totalFilas: {
    type: Number,
    default: 0,
  },
});

function formatearEtiqueta(key) {
  return String(key || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letra) => letra.toUpperCase());
}

function formatearValorResumen(valor) {
  if (typeof valor === 'number') {
    return valor.toLocaleString('es-BO', {
      minimumFractionDigits: valor % 1 === 0 ? 0 : 2,
      maximumFractionDigits: 2,
    });
  }

  return valor ?? '—';
}
</script>