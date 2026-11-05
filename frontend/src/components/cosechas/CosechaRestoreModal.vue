<template>
  <div class="modal-overlay cosecha-modal-overlay" @click.self="close">
    <div class="modal-card cosecha-restore-modal">
      <div class="cosecha-restore-icon">
        <i class="ti ti-restore"></i>
      </div>

      <h3>Restaurar cosecha</h3>

      <p>
        ¿Deseas restaurar la cosecha de la parcela
        <strong>{{ cosecha?.parcela?.codigo_parcela || '—' }}</strong>?
      </p>

      <span class="cosecha-delete-help">
        La cosecha volverá al listado principal y podrá usarse nuevamente en reportes.
      </span>

      <div v-if="error" class="cosecha-form-error">
        {{ error }}
      </div>

      <div class="cosecha-delete-actions">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-restore"></i>
          {{ saving ? 'Restaurando...' : 'Sí, restaurar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  cosecha: {
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