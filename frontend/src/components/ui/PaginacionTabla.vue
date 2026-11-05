<template>
  <div v-if="pagination && pagination.last_page > 1" class="paginacion-tabla">
    <button
      type="button"
      class="btn btn-s"
      :disabled="disabled || pagination.current_page <= 1"
      @click="$emit('change-page', pagination.current_page - 1)"
    >
      <i class="ti ti-chevron-left"></i>
      Anterior
    </button>

    <div class="paginacion-tabla-info">
      <strong>Página {{ pagination.current_page }} de {{ pagination.last_page }}</strong>
      <span>{{ pagination.total }} {{ etiqueta }}</span>
    </div>

    <button
      type="button"
      class="btn btn-s"
      :disabled="disabled || pagination.current_page >= pagination.last_page"
      @click="$emit('change-page', pagination.current_page + 1)"
    >
      Siguiente
      <i class="ti ti-chevron-right"></i>
    </button>
  </div>
</template>

<script setup>
defineProps({
  // { current_page, last_page, total } de una respuesta paginada de Laravel.
  pagination: {
    type: Object,
    default: null,
  },
  etiqueta: {
    type: String,
    default: 'registros',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['change-page']);
</script>

<style scoped>
.paginacion-tabla {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 4px 4px;
  flex-wrap: wrap;
}

.paginacion-tabla-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  font-size: 13px;
}

.paginacion-tabla-info span {
  color: var(--gris-500, #6b7280);
  font-size: 12px;
}
</style>
