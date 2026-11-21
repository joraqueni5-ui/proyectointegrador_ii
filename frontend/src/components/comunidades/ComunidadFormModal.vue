<template>
  <div class="modal-overlay comunidad-modal-overlay" @click.self="close">
    <div class="modal-card comunidad-form-modal">
      <div class="modal-header comunidad-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nueva comunidad' : 'Editar comunidad' }}
          </div>
          <p class="comunidad-modal-subtitle">
            Registra la información territorial y geográfica de la comunidad agrícola.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="comunidad-form-section">
          <div class="comunidad-section-title">
            <i class="ti ti-building-community"></i>
            Datos generales
          </div>

          <div class="comunidad-form-grid">
            <div class="form-group span-2">
              <label>Nombre de la comunidad *</label>
              <input
                v-model="form.nombre_comunidad"
                maxlength="120"
                placeholder="Ej. Chiaraque"
                @input="form.nombre_comunidad = limpiarTextoGeneral(form.nombre_comunidad)"
              />
              <small class="field-help">Se permiten letras, números, espacios y guiones.</small>
            </div>

            <div class="form-group">
              <label>Municipio *</label>
              <input
                v-model="form.municipio"
                maxlength="100"
                placeholder="Ej. Viacha"
                @input="form.municipio = soloLetras(form.municipio)"
              />
            </div>

            <div class="form-group">
              <label>Provincia</label>
              <input
                v-model="form.provincia"
                maxlength="100"
                placeholder="Ej. Ingavi"
                @input="form.provincia = soloLetras(form.provincia)"
              />
            </div>

            <div class="form-group">
              <label>Departamento *</label>
              <select v-model="form.departamento">
                <option value="La Paz">La Paz</option>
                <option value="Cochabamba">Cochabamba</option>
                <option value="Potosí">Potosí</option>
                <option value="Oruro">Oruro</option>
                <option value="Santa Cruz">Santa Cruz</option>
                <option value="Tarija">Tarija</option>
                <option value="Beni">Beni</option>
                <option value="Pando">Pando</option>
                <option value="Chuquisaca">Chuquisaca</option>
              </select>
            </div>

            <div class="form-group">
              <label>Altitud m.s.n.m.</label>
              <input
                v-model="form.altitud_msnm"
                inputmode="numeric"
                maxlength="5"
                placeholder="Ej. 3800"
                @input="form.altitud_msnm = soloNumeros(form.altitud_msnm, 5)"
              />
              <small class="field-help">Opcional.</small>
            </div>

            <div v-if="mode === 'edit'" class="form-group">
              <label>Estado</label>
              <select v-model="form.activo">
                <option :value="true">Activa</option>
                <option :value="false">Inactiva</option>
              </select>
            </div>
          </div>
        </div>

        <div class="comunidad-form-section">
          <div class="comunidad-section-title">
            <i class="ti ti-map-pin"></i>
            Coordenadas GPS
          </div>

          <div class="comunidad-form-grid">
            <div class="form-group">
              <label>Latitud</label>
              <input
                v-model="form.latitud"
                type="number"
                step="0.0000001"
                placeholder="-16.5000000"
              />
              <small class="field-help">Dentro de Bolivia: entre -23 y -9.</small>
            </div>

            <div class="form-group">
              <label>Longitud</label>
              <input
                v-model="form.longitud"
                type="number"
                step="0.0000001"
                placeholder="-68.1500000"
              />
              <small class="field-help">Dentro de Bolivia: entre -70 y -57.</small>
            </div>
          </div>
        </div>

        <div v-if="localError || error" class="comunidad-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear comunidad' : 'Guardar cambios' }}
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
  comunidad: {
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
  () => [props.mode, props.comunidad],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    nombre_comunidad: '',
    municipio: '',
    provincia: '',
    departamento: 'La Paz',
    altitud_msnm: '',
    latitud: '',
    longitud: '',
    activo: true,
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.comunidad) {
    form.nombre_comunidad = props.comunidad.nombre_comunidad || '';
    form.municipio = props.comunidad.municipio || '';
    form.provincia = props.comunidad.provincia || '';
    form.departamento = props.comunidad.departamento || 'La Paz';
    form.altitud_msnm = props.comunidad.altitud_msnm ?? '';
    form.latitud = props.comunidad.latitud ?? '';
    form.longitud = props.comunidad.longitud ?? '';
    form.activo = Boolean(props.comunidad.activo);
    return;
  }

  Object.assign(form, defaultForm());
}

function limpiarTextoGeneral(value) {
  return String(value || '')
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü0-9\s\-]/g, '')
    .replace(/\s{2,}/g, ' ')
    .trimStart();
}

function soloLetras(value) {
  return String(value || '')
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]/g, '')
    .replace(/\s{2,}/g, ' ')
    .trimStart();
}

function soloNumeros(value, max = 5) {
  return String(value || '')
    .replace(/\D/g, '')
    .slice(0, max);
}

function validarCoordenada(value, min, max, label) {
  if (value === '' || value === null || value === undefined) {
    return '';
  }

  const numberValue = Number(value);

  if (Number.isNaN(numberValue)) {
    return `${label} debe ser un número válido.`;
  }

  if (numberValue < min || numberValue > max) {
    return `${label} debe estar entre ${min} y ${max}.`;
  }

  return '';
}

function submit() {
  localError.value = '';

  form.nombre_comunidad = limpiarTextoGeneral(form.nombre_comunidad).trim();
  form.municipio = soloLetras(form.municipio).trim();
  form.provincia = soloLetras(form.provincia).trim();
  form.altitud_msnm = soloNumeros(form.altitud_msnm, 5);

  if (!form.nombre_comunidad) {
    return showError('El nombre de la comunidad es obligatorio.');
  }

  if (!form.municipio) {
    return showError('El municipio es obligatorio.');
  }

  if (!form.departamento) {
    return showError('Debes seleccionar un departamento.');
  }

  if (form.altitud_msnm && (Number(form.altitud_msnm) < 0 || Number(form.altitud_msnm) > 6600)) {
    return showError('La altitud debe estar entre 0 y 6600 msnm.');
  }

  let message = validarCoordenada(form.latitud, -23, -9, 'La latitud (Bolivia)');
  if (message) return showError(message);

  message = validarCoordenada(form.longitud, -70, -57, 'La longitud (Bolivia)');
  if (message) return showError(message);

  const payload = {
    nombre_comunidad: form.nombre_comunidad,
    municipio: form.municipio,
    provincia: form.provincia || null,
    departamento: form.departamento,
    altitud_msnm: form.altitud_msnm ? Number(form.altitud_msnm) : null,
    latitud: form.latitud !== '' ? Number(form.latitud) : null,
    longitud: form.longitud !== '' ? Number(form.longitud) : null,
    activo: form.activo,
  };

  emit('save', payload);
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>