<template>
  <div class="modal-overlay user-modal-overlay" @click.self="close">
    <div class="modal-card user-delete-modal">
      <div class="user-delete-icon">
        <i class="ti ti-user-off"></i>
      </div>

      <h3>Desactivar usuario</h3>

      <p>
        ¿Deseas desactivar a
        <strong>{{ user?.name }} {{ user?.paterno }}</strong>?
      </p>

      <span class="user-delete-help">
        El usuario no podrá acceder al sistema hasta que sea activado nuevamente.
      </span>

      <div class="user-delete-actions">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-d" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-user-off"></i>
          {{ saving ? 'Desactivando...' : 'Sí, desactivar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  user: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
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