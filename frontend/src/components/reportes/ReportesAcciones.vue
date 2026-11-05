<template>
  <div class="reportes-acciones">
    <button
      class="btn btn-s"
      type="button"
      @click="$emit('limpiar')"
    >
      <i class="ti ti-eraser"></i>
      Limpiar
    </button>

    <button
      class="btn btn-p"
      type="button"
      :disabled="loading"
      @click="$emit('generar')"
    >
      <i class="ti ti-report-search"></i>
      {{ loading ? 'Generando...' : 'Generar reporte' }}
    </button>

    <button
      class="btn btn-s"
      type="button"
      :disabled="exporting || !hasReporte"
      @click="$emit('excel')"
    >
      <i class="ti ti-file-spreadsheet"></i>
      {{ exporting ? 'Exportando...' : 'Excel' }}
    </button>

    <button
      class="btn btn-d"
      type="button"
      :disabled="exporting || !hasReporte"
      @click="$emit('pdf')"
    >
      <i class="ti ti-file-type-pdf"></i>
      {{ exporting ? 'Exportando...' : 'PDF' }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  exporting: {
    type: Boolean,
    default: false,
  },
  hasReporte: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'limpiar',
  'generar',
  'excel',
  'pdf',
]);
</script>