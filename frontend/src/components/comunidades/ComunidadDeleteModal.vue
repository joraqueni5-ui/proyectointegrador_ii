<template>
  <div class="modal-overlay comunidad-modal-overlay" @click.self="close">
    <div class="modal-card comunidad-delete-modal">
      <div class="comunidad-delete-icon">
        <i class="ti ti-building-community"></i>
      </div>

      <h3>Desactivar comunidad</h3>

      <p>
        ¿Deseas desactivar
        <strong>{{ comunidad?.nombre_comunidad }}</strong>?
      </p>

      <span class="comunidad-delete-help">
        La comunidad dejará de mostrarse como activa. Los productores asociados no serán eliminados.
      </span>

      <div v-if="error" class="comunidad-delete-error">
        {{ error }}
      </div>

      <div class="comunidad-delete-actions">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-d" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-trash"></i>
          {{ saving ? 'Desactivando...' : 'Sí, desactivar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  comunidad: {
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