<template>
  <div class="modal-overlay cotizacion-modal-overlay" @click.self="close">
    <div class="modal-card cotizacion-confirm-modal">
      <div class="cotizacion-confirm-header" :class="config.className">
        <div class="cotizacion-confirm-icon">
          <i :class="config.icon"></i>
        </div>

        <div>
          <span>{{ config.kicker }}</span>
          <h3>{{ config.title }}</h3>
          <p>{{ config.description }}</p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="cotizacion-confirm-body">
        <div class="cotizacion-confirm-summary">
          <span>Cotización seleccionada</span>

          <strong>
            {{ cotizacion?.nro_cotizacion || `Cotización #${cotizacion?.id || '—'}` }}
          </strong>

          <p>
            Productor:
            {{ cotizacion?.productor?.nombre_completo || '—' }}
          </p>

          <div class="cotizacion-confirm-total">
            <small>Total cotizado</small>
            <b>Bs {{ numero(cotizacion?.monto_total) }}</b>
          </div>
        </div>

        <div class="cotizacion-confirm-warning" :class="config.className">
          <i :class="config.warningIcon"></i>
          <span>{{ config.warning }}</span>
        </div>

        <div v-if="error" class="cotizacion-form-error">
          {{ error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button
          class="btn"
          :class="config.buttonClass"
          type="button"
          :disabled="saving"
          @click="$emit('confirm')"
        >
          <i :class="config.icon"></i>
          {{ saving ? 'Procesando...' : config.confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  cotizacion: {
    type: Object,
    default: null,
  },
  action: {
    type: String,
    required: true,
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

const config = computed(() => {
  const configs = {
    aprobar: {
      className: 'aprobar',
      kicker: 'Validación comercial',
      title: 'Aprobar cotización',
      description: 'La cotización quedará aprobada y lista para convertirse en venta.',
      warning: 'Aprobar no descuenta stock todavía. El descuento se realizará al convertir a venta.',
      icon: 'ti ti-thumb-up',
      warningIcon: 'ti ti-info-circle',
      confirmText: 'Sí, aprobar',
      buttonClass: 'btn-p',
    },

    convertir: {
      className: 'convertir',
      kicker: 'Conversión a venta',
      title: 'Convertir cotización en venta',
      description: 'Se generará la venta a partir de esta cotización aprobada.',
      warning: 'Esta acción sí puede descontar stock y registrar la salida correspondiente del inventario.',
      icon: 'ti ti-receipt',
      warningIcon: 'ti ti-alert-triangle',
      confirmText: 'Sí, convertir a venta',
      buttonClass: 'btn-p',
    },

    anular: {
      className: 'anular',
      kicker: 'Anulación comercial',
      title: 'Anular cotización',
      description: 'La cotización quedará anulada y ya no podrá continuar su flujo comercial.',
      warning: 'Esta acción no elimina la cotización, solo cambia su estado para mantener trazabilidad.',
      icon: 'ti ti-ban',
      warningIcon: 'ti ti-alert-circle',
      confirmText: 'Sí, anular',
      buttonClass: 'btn-d',
    },
  };

  return configs[props.action] || configs.aprobar;
});

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function close() {
  emit('close');
}
</script>