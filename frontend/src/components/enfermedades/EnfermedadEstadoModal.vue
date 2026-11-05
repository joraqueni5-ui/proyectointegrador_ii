<template>
  <div class="modal-overlay enfermedad-modal-overlay" @click.self="close">
    <div class="modal-card enfermedad-estado-modal">
      <div class="enfermedad-estado-icon" :class="enfermedad?.activo ? 'danger' : 'success'">
        <i :class="enfermedad?.activo ? 'ti ti-ban' : 'ti ti-circle-check'"></i>
      </div>

      <h3>
        {{ enfermedad?.activo ? 'Desactivar enfermedad' : 'Activar enfermedad' }}
      </h3>

      <p>
        ¿Deseas
        <strong>{{ enfermedad?.activo ? 'desactivar' : 'activar' }}</strong>
        la enfermedad
        <strong>{{ enfermedad?.nombre_comun || '—' }}</strong>?
      </p>

      <span class="enfermedad-estado-help">
        No se eliminará el historial de diagnósticos asociados.
      </span>

      <div v-if="error" class="enfermedad-form-error">
        {{ error }}
      </div>

      <div class="enfermedad-estado-actions">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button
          class="btn"
          :class="enfermedad?.activo ? 'btn-d' : 'btn-p'"
          type="button"
          :disabled="saving"
          @click="confirm"
        >
          <i :class="enfermedad?.activo ? 'ti ti-ban' : 'ti ti-check'"></i>
          {{ saving ? 'Procesando...' : enfermedad?.activo ? 'Sí, desactivar' : 'Sí, activar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  enfermedad: {
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