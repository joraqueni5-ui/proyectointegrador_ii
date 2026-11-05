<template>
  <div class="modal-overlay cultivo-modal-overlay" @click.self="close">
    <div class="modal-card cultivo-estado-modal">
      <div class="modal-header">
        <div>
          <div class="modal-title">Cambiar estado del cultivo</div>
          <p class="cultivo-modal-subtitle">
            Variedad: <strong>{{ cultivo?.variedad_papa }}</strong>
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div class="cultivo-estado-info">
          <i class="ti ti-refresh"></i>
          <p>
            Cambia el estado del cultivo sin eliminar el historial de inspecciones o cosechas.
          </p>
        </div>

        <div class="form-group">
          <label>Nuevo estado</label>
          <select v-model="estadoLocal">
            <option value="Activo">Activo</option>
            <option value="Cosechado">Cosechado</option>
            <option value="Perdido">Perdido</option>
            <option value="Abandonado">Abandonado</option>
          </select>
        </div>

        <div v-if="error" class="cultivo-form-error">
          {{ error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : 'Cambiar estado' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  cultivo: {
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

const estadoLocal = ref('Activo');

watch(
  () => props.cultivo,
  () => {
    estadoLocal.value = props.cultivo?.estado || 'Activo';
  },
  { immediate: true }
);

function close() {
  emit('close');
}

function confirm() {
  emit('confirm', estadoLocal.value);
}
</script>