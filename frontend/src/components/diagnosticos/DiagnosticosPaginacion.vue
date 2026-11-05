<template>
  <div
    v-if="pagination && pagination.last_page > 1"
    class="diagnosticos-pagination"
  >
    <button
      class="btn btn-s"
      type="button"
      :disabled="pagination.current_page <= 1"
      @click="$emit('change-page', pagination.current_page - 1)"
    >
      <i class="ti ti-chevron-left"></i>
      Anterior
    </button>

    <div class="diagnosticos-pagination-info">
      <strong>
        Página {{ pagination.current_page }} de {{ pagination.last_page }}
      </strong>
      <span>
        {{ pagination.total }} diagnósticos registrados
      </span>
    </div>

    <button
      class="btn btn-s"
      type="button"
      :disabled="pagination.current_page >= pagination.last_page"
      @click="$emit('change-page', pagination.current_page + 1)"
    >
      Siguiente
      <i class="ti ti-chevron-right"></i>
    </button>
  </div>
</template>

<script setup>
defineProps({
  pagination: {
    type: Object,
    default: null,
  },
});

defineEmits(['change-page']);
</script>