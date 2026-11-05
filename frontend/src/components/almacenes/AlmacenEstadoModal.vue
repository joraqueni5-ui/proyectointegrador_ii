<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal almacenes-estado-modal">
      <div
        class="almacenes-estado-icon"
        :class="almacen?.estado === 'Activo' ? 'danger' : 'success'"
      >
        <i :class="almacen?.estado === 'Activo' ? 'ti ti-ban' : 'ti ti-circle-check'"></i>
      </div>

      <h2>
        {{ almacen?.estado === 'Activo' ? 'Desactivar almacén' : 'Activar almacén' }}
      </h2>

      <p>
        ¿Seguro que deseas
        <strong>{{ almacen?.estado === 'Activo' ? 'desactivar' : 'activar' }}</strong>
        el almacén
        <strong>{{ almacen?.nombre_almacen || '—' }}</strong>?
      </p>

      <span>
        No se eliminará físicamente. Solo cambiará su estado operativo.
      </span>

      <div
        v-if="almacen?.estado === 'Activo' && stockTotal(almacen) > 0"
        class="almacenes-warning"
      >
        <i class="ti ti-alert-triangle"></i>
        Este almacén tiene stock registrado. El backend puede bloquear la desactivación
        hasta regularizar o mover el inventario.
      </div>

      <p v-if="error" class="inventario-error almacenes-form-error">
        {{ error }}
      </p>

      <div class="inventario-modal-actions almacenes-estado-actions">
        <button type="button" @click="close">
          Cancelar
        </button>

        <button
          class="inventario-btn-primary"
          type="button"
          :class="almacen?.estado === 'Activo' ? 'inventario-danger-soft' : ''"
          :disabled="saving"
          @click="confirm"
        >
          <i :class="almacen?.estado === 'Activo' ? 'ti ti-ban' : 'ti ti-check'"></i>
          {{ saving ? 'Procesando...' : almacen?.estado === 'Activo' ? 'Desactivar' : 'Activar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  almacen: {
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

function stockTotal(almacen) {
  if (!almacen) {
    return 0;
  }

  if (almacen.stock_total !== null && almacen.stock_total !== undefined) {
    return Number(almacen.stock_total) || 0;
  }

  if (Array.isArray(almacen.inventarios)) {
    return almacen.inventarios.reduce(
      (sum, item) => sum + (Number(item.stock_actual) || 0),
      0
    );
  }

  return 0;
}
</script>