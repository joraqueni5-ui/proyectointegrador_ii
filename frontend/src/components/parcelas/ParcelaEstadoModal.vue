<template>
  <div class="modal-overlay parcela-modal-overlay" @click.self="close">
    <div class="modal-card parcela-estado-modal">
      <div class="modal-header">
        <div>
          <div class="modal-title">Cambiar estado de parcela</div>
          <p class="parcela-modal-subtitle">
            Parcela: <strong>{{ parcela?.codigo_parcela }}</strong>
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div class="parcela-estado-info">
          <i class="ti ti-refresh"></i>
          <p>
            Selecciona el nuevo estado de la parcela. Este cambio no elimina registros asociados.
          </p>
        </div>

        <div class="form-group">
          <label>Nuevo estado</label>
          <select v-model="estadoLocal">
            <option value="Activa">Activa</option>
            <option value="En_descanso">En descanso</option>
            <option value="Inactiva">Inactiva</option>
          </select>
        </div>

        <div v-if="error" class="parcela-form-error">
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
  parcela: {
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

const estadoLocal = ref('Activa');

watch(
  () => props.parcela,
  () => {
    estadoLocal.value = props.parcela?.estado || 'Activa';
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