<template>
  <div class="modal-overlay enfermedad-modal-overlay" @click.self="close">
    <div class="modal-card enfermedad-form-modal">
      <div class="modal-header enfermedad-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nueva enfermedad' : 'Editar enfermedad' }}
          </div>
          <p class="enfermedad-modal-subtitle">
            Registra enfermedades agrícolas, síntomas, riesgo y medidas de control.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="enfermedad-form-section">
          <div class="enfermedad-section-title">
            <i class="ti ti-bug"></i>
            Identificación de la enfermedad
          </div>

          <div class="enfermedad-form-grid">
            <div class="form-group">
              <label>Nombre común *</label>
              <input
                v-model.trim="form.nombre_comun"
                maxlength="150"
                placeholder="Ej. Tizón tardío"
              />
            </div>

            <div class="form-group">
              <label>Código *</label>
              <input
                v-model.trim="form.codigo_enfermedad"
                maxlength="20"
                placeholder="Ej. ENF-TT"
                @input="form.codigo_enfermedad = form.codigo_enfermedad.toUpperCase()"
              />
            </div>

            <div class="form-group">
              <label>Nombre científico</label>
              <input
                v-model.trim="form.nombre_cientifico"
                maxlength="200"
                placeholder="Ej. Phytophthora infestans"
              />
            </div>

            <div class="form-group">
              <label>Tipo *</label>
              <select v-model="form.tipo">
                <option value="">Selecciona tipo</option>
                <option value="Hongo">Hongo</option>
                <option value="Bacteria">Bacteria</option>
                <option value="Virus">Virus</option>
                <option value="Nematodo">Nematodo</option>
                <option value="Otro">Otro</option>
              </select>
            </div>

            <div class="form-group">
              <label>Nivel de riesgo *</label>
              <select v-model="form.nivel_riesgo_base">
                <option value="">Selecciona riesgo</option>
                <option value="Bajo">Bajo</option>
                <option value="Medio">Medio</option>
                <option value="Alto">Alto</option>
                <option value="Critico">Crítico</option>
              </select>
            </div>

            <div class="form-group">
              <label>Imagen de referencia</label>
              <input
                v-model.trim="form.imagen_referencia"
                maxlength="255"
                placeholder="URL o ruta de imagen"
              />
            </div>
          </div>
        </div>

        <div class="enfermedad-form-section">
          <div class="enfermedad-section-title">
            <i class="ti ti-notes"></i>
            Descripción técnica
          </div>

          <div class="enfermedad-form-grid">
            <div class="form-group span-3">
              <label>Síntomas</label>
              <textarea
                v-model.trim="form.sintomas"
                placeholder="Describe manchas, marchitez, decoloración, lesiones u otros signos visibles..."
              ></textarea>
            </div>

            <div class="form-group span-3">
              <label>Descripción</label>
              <textarea
                v-model.trim="form.descripcion"
                placeholder="Descripción general de la enfermedad, condiciones en las que aparece, cultivo afectado..."
              ></textarea>
            </div>

            <div class="form-group span-3">
              <label>Medidas de control</label>
              <textarea
                v-model.trim="form.medidas_control"
                placeholder="Recomendaciones de manejo, prevención, control químico/biológico/cultural..."
              ></textarea>
            </div>
          </div>
        </div>

        <div v-if="localError || error" class="enfermedad-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear enfermedad' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  // Error devuelto por el servidor al guardar.
  error: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'create',
  },
  enfermedad: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const form = reactive(defaultForm());

watch(
  () => [props.mode, props.enfermedad],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    nombre_comun: '',
    nombre_cientifico: '',
    codigo_enfermedad: '',
    tipo: '',
    sintomas: '',
    descripcion: '',
    medidas_control: '',
    nivel_riesgo_base: '',
    imagen_referencia: '',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.enfermedad) {
    form.nombre_comun = props.enfermedad.nombre_comun || '';
    form.nombre_cientifico = props.enfermedad.nombre_cientifico || '';
    form.codigo_enfermedad = props.enfermedad.codigo_enfermedad || '';
    form.tipo = props.enfermedad.tipo || '';
    form.sintomas = props.enfermedad.sintomas || '';
    form.descripcion = props.enfermedad.descripcion || '';
    form.medidas_control = props.enfermedad.medidas_control || '';
    form.nivel_riesgo_base = props.enfermedad.nivel_riesgo_base || '';
    form.imagen_referencia = props.enfermedad.imagen_referencia || '';
    return;
  }

  Object.assign(form, defaultForm());
}

function submit() {
  localError.value = '';

  if (!form.nombre_comun.trim()) {
    return showError('El nombre común es obligatorio.');
  }

  if (!form.codigo_enfermedad.trim()) {
    return showError('El código de enfermedad es obligatorio.');
  }

  if (!form.tipo) {
    return showError('Debe seleccionar el tipo de enfermedad.');
  }

  if (!form.nivel_riesgo_base) {
    return showError('Debe seleccionar el nivel de riesgo.');
  }

  emit('save', {
    nombre_comun: form.nombre_comun.trim(),
    nombre_cientifico: form.nombre_cientifico || null,
    codigo_enfermedad: form.codigo_enfermedad.trim().toUpperCase(),
    tipo: form.tipo,
    sintomas: form.sintomas || null,
    descripcion: form.descripcion || null,
    medidas_control: form.medidas_control || null,
    nivel_riesgo_base: form.nivel_riesgo_base,
    imagen_referencia: form.imagen_referencia || null,
  });
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>