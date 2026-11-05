<template>
  <div class="modal-overlay inspeccion-modal-overlay" @click.self="close">
    <div class="modal-card inspeccion-estado-modal">
      <div class="modal-header">
        <div>
          <div class="modal-title">Cambiar estado</div>
          <p class="inspeccion-modal-subtitle">
            Inspección #{{ inspeccion?.id }} —
            <strong>{{ inspeccion?.parcela?.codigo_parcela || '—' }}</strong>
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="inspeccion-estado-info">
          <i class="ti ti-refresh"></i>
          <p>
            Actualiza el estado operativo de la inspección sin eliminar su historial.
          </p>
        </div>

        <div class="form-group">
          <label>Nuevo estado</label>
          <select v-model="form.estado">
            <option value="Pendiente">Pendiente</option>
            <option value="En_proceso">En proceso</option>
            <option value="Completada">Completada</option>
            <option value="Validada">Validada</option>
            <option value="No_realizada">No realizada</option>
          </select>
        </div>

        <div v-if="form.estado === 'No_realizada'" class="inspeccion-form-grid">
          <div class="form-group span-2">
            <label>Motivo de no realización *</label>
            <textarea
              v-model.trim="form.motivo_no_realizada"
              placeholder="Ej. No se encontró al productor, lluvia intensa, parcela inaccesible..."
            ></textarea>
          </div>

          <div class="form-group">
            <label>Fecha de reprogramación</label>
            <input v-model="form.fecha_reprogramada" type="date" />
          </div>

          <div class="form-group span-2">
            <label>Recomendación para reprogramar</label>
            <textarea
              v-model.trim="form.recomendacion_reprogramacion"
              placeholder="Ej. Reprogramar la visita para la siguiente semana..."
            ></textarea>
          </div>
        </div>

        <div v-if="error || localError" class="inspeccion-form-error">
          {{ error || localError }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">Cancelar</button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="confirm">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : 'Cambiar estado' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  inspeccion: {
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

const localError = ref('');

const form = reactive({
  estado: 'Pendiente',
  motivo_no_realizada: '',
  fecha_reprogramada: '',
  recomendacion_reprogramacion: '',
});

watch(
  () => props.inspeccion,
  () => {
    form.estado = props.inspeccion?.estado || 'Pendiente';
    form.motivo_no_realizada = props.inspeccion?.motivo_no_realizada || '';
    form.fecha_reprogramada = props.inspeccion?.fecha_reprogramada
      ? String(props.inspeccion.fecha_reprogramada).slice(0, 10)
      : '';
    form.recomendacion_reprogramacion =
      props.inspeccion?.recomendacion_reprogramacion || '';
    localError.value = '';
  },
  { immediate: true }
);

function close() {
  emit('close');
}

function confirm() {
  localError.value = '';

  if (form.estado === 'No_realizada' && !form.motivo_no_realizada.trim()) {
    localError.value = 'Debe registrar el motivo cuando la inspección no fue realizada.';
    return;
  }

  emit('confirm', {
    estado: form.estado,
    motivo_no_realizada:
      form.estado === 'No_realizada' ? form.motivo_no_realizada : null,
    fecha_reprogramada:
      form.estado === 'No_realizada' && form.fecha_reprogramada
        ? form.fecha_reprogramada
        : null,
    recomendacion_reprogramacion:
      form.estado === 'No_realizada'
        ? form.recomendacion_reprogramacion || null
        : null,
  });
}
</script>