<template>
  <div class="modal-overlay cosecha-modal-overlay" @click.self="close">
    <div class="modal-card cosecha-delete-modal">
      <div class="cosecha-delete-icon">
        <i class="ti ti-trash"></i>
      </div>

      <h3>Eliminar cosecha</h3>

      <p>
        ¿Deseas eliminar la cosecha de la parcela
        <strong>{{ cosecha?.parcela?.codigo_parcela || '—' }}</strong>?
      </p>

      <span class="cosecha-delete-help">
        Esta acción no se puede deshacer.
      </span>

      <div v-if="error" class="cosecha-form-error">
        {{ error }}
      </div>

      <div class="cosecha-delete-actions">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-d" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-trash"></i>
          {{ saving ? 'Eliminando...' : 'Sí, eliminar' }}
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