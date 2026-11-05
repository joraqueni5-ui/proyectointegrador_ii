<template>
  <div class="modal-overlay venta-modal-overlay" @click.self="close">
    <div class="modal-card venta-anular-modal">
      <div class="venta-anular-header">
        <div class="venta-anular-icon">
          <i class="ti ti-ban"></i>
        </div>

        <div>
          <span>Anulación comercial</span>
          <h3>Anular venta</h3>
          <p>
            La venta será anulada y el sistema intentará devolver el stock mediante movimiento inverso.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="venta-anular-body">
        <div class="venta-anular-summary">
          <span>Venta seleccionada</span>

          <strong>
            Factura #{{ venta?.nro_factura || venta?.id || '—' }}
          </strong>

          <p>
            Productor:
            {{ venta?.productor?.nombre_completo || '—' }}
          </p>

          <div class="venta-anular-total">
            <small>Total registrado</small>
            <b>Bs {{ numero(venta?.monto_total) }}</b>
          </div>
        </div>

        <div class="venta-anular-warning">
          <i class="ti ti-alert-triangle"></i>
          <span>
            Esta acción no elimina la venta. Cambia su estado a Anulada y mantiene trazabilidad.
          </span>
        </div>

        <div v-if="error" class="venta-form-error">
          {{ error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button
          class="btn btn-d"
          type="button"
          :disabled="saving"
          @click="$emit('confirm')"
        >
          <i class="ti ti-ban"></i>
          {{ saving ? 'Anulando...' : 'Sí, anular venta' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  venta: {
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

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>