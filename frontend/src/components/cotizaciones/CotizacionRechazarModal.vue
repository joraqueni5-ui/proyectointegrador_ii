<template>
  <div class="modal-overlay cotizacion-modal-overlay" @click.self="close">
    <div class="modal-card cotizacion-rechazar-modal">
      <div class="cotizacion-rechazar-header">
        <div class="cotizacion-rechazar-icon">
          <i class="ti ti-circle-x"></i>
        </div>

        <div>
          <span>Acción comercial</span>
          <h3>Rechazar cotización</h3>
          <p>
            Registra el motivo por el cual esta cotización no será aprobada.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="cotizacion-rechazar-body">
        <div class="cotizacion-rechazar-summary">
          <span>Cotización seleccionada</span>
          <strong>{{ cotizacion?.nro_cotizacion || `Cotización #${cotizacion?.id || '—'}` }}</strong>
          <p>
            Productor:
            {{ cotizacion?.productor?.nombre_completo || '—' }}
          </p>
        </div>

        <div class="form-group">
          <label>Motivo del rechazo *</label>
          <textarea
            v-model.trim="motivo"
            maxlength="500"
            placeholder="Ej. Stock insuficiente, datos incompletos, solicitud cancelada por el productor..."
          ></textarea>

          <small class="cotizacion-help">
            Máximo 500 caracteres.
          </small>
        </div>

        <div v-if="localError || error" class="cotizacion-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button
          class="btn btn-d"
          type="button"
          :disabled="saving || !motivo.trim()"
          @click="submit"
        >
          <i class="ti ti-circle-x"></i>
          {{ saving ? 'Rechazando...' : 'Rechazar cotización' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  cotizacion: {
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

const motivo = ref('');
const localError = ref('');

watch(
  () => props.cotizacion,
  () => {
    motivo.value = '';
    localError.value = '';
  },
  { immediate: true }
);

function submit() {
  localError.value = '';

  if (!motivo.value.trim()) {
    localError.value = 'Debe ingresar el motivo del rechazo.';
    return;
  }

  emit('confirm', motivo.value.trim());
}

function close() {
  emit('close');
}
</script>