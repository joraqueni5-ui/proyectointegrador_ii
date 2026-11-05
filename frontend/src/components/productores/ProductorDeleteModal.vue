<template>
  <div class="modal-overlay productor-modal-overlay" @click.self="close">
    <div class="modal-card productor-delete-modal">
      <div class="productor-delete-icon">
        <i class="ti ti-user-off"></i>
      </div>

      <h3>Desactivar productor</h3>

      <p>
        ¿Deseas desactivar a
        <strong>{{ nombreProductor }}</strong>?
      </p>

      <span class="productor-delete-help">
        El productor dejará de mostrarse como activo. Sus parcelas no serán eliminadas.
      </span>

      <div v-if="error" class="productor-delete-error">
        {{ error }}
      </div>

      <div class="productor-delete-actions">
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
import { computed } from 'vue';

const props = defineProps({
  productor: {
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

const nombreProductor = computed(() => {
  if (!props.productor) return '';

  if (props.productor.nombre_completo) {
    return props.productor.nombre_completo;
  }

  return [
    props.productor.nombres,
    props.productor.apellido_paterno,
    props.productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ');
});

function close() {
  emit('close');
}

function confirm() {
  emit('confirm');
}
</script>