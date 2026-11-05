<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal productos-delete-modal">
      <div class="productos-delete-icon" :class="producto?.activo ? 'danger' : 'success'">
        <i :class="producto?.activo ? 'ti ti-ban' : 'ti ti-circle-check'"></i>
      </div>

      <h2>
        {{ producto?.activo ? 'Desactivar producto' : 'Activar producto' }}
      </h2>

      <p>
        ¿Seguro que deseas
        <strong>{{ producto?.activo ? 'desactivar' : 'activar' }}</strong>
        el producto
        <strong>{{ producto?.nombre_producto || '—' }}</strong>?
      </p>

      <span>
        No se eliminará físicamente. Solo cambiará su estado dentro del catálogo.
      </span>

      <p v-if="error" class="inventario-error productos-form-error">
        {{ error }}
      </p>

      <div class="inventario-modal-actions productos-delete-actions">
        <button type="button" @click="close">
          Cancelar
        </button>

        <button
          class="inventario-btn-primary"
          type="button"
          :class="producto?.activo ? 'inventario-danger-soft' : ''"
          :disabled="saving"
          @click="confirm"
        >
          <i :class="producto?.activo ? 'ti ti-ban' : 'ti ti-check'"></i>
          {{ saving ? 'Procesando...' : producto?.activo ? 'Desactivar' : 'Activar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  producto: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['close', 'confirm']);

function close() {
  emit('close');
}

function confirm() {
  emit('confirm');
}
</script>