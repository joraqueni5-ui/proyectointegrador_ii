<template>
  <div class="modal-overlay productor-modal-overlay" @click.self="close">
    <div class="modal-card productor-form-modal">
      <div class="modal-header productor-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nuevo productor' : 'Editar productor' }}
          </div>
          <p class="productor-modal-subtitle">
            {{ mode === 'create'
              ? 'Registra los datos personales y agrícolas del productor.'
              : 'Actualiza la información personal, agrícola y de estado del productor.'
            }}
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <!-- Datos personales -->
        <div class="productor-form-section">
          <div class="productor-section-title">
            <i class="ti ti-user"></i>
            Datos personales
          </div>

          <div class="productor-form-grid">
            <div class="form-group">
              <label>Nombres *</label>
              <input
                v-model="form.nombres"
                maxlength="100"
                placeholder="Ej. Juan Luis"
                @input="form.nombres = soloLetras(form.nombres)"
              />
              <small class="field-help">Solo letras y espacios.</small>
            </div>

            <div class="form-group">
              <label>Apellido paterno *</label>
              <input
                v-model="form.apellido_paterno"
                maxlength="100"
                placeholder="Ej. Quispe"
                @input="form.apellido_paterno = soloLetras(form.apellido_paterno)"
              />
            </div>

            <div class="form-group">
              <label>Apellido materno</label>
              <input
                v-model="form.apellido_materno"
                maxlength="100"
                placeholder="Ej. Mamani"
                @input="form.apellido_materno = soloLetras(form.apellido_materno)"
              />
            </div>

            <div class="form-group">
  <label>Carnet de identidad *</label>
  <input
    v-model="form.carnet_identidad"
    inputmode="numeric"
    maxlength="10"
    placeholder="Ej. 12345678"
    @input="form.carnet_identidad = soloNumeros(form.carnet_identidad, 10)"
  />
  <small class="field-help">Entre 5 y 10 dígitos.</small>
</div>

<div class="form-group">
  <label>Extensión CI *</label>
  <select v-model="form.ci_extension">
    <option value="">Selecciona</option>
    <option value="LP">LP</option>
    <option value="CB">CB</option>
    <option value="SC">SC</option>
    <option value="OR">OR</option>
    <option value="PT">PT</option>
    <option value="CH">CH</option>
    <option value="TJ">TJ</option>
    <option value="BE">BE</option>
    <option value="PD">PD</option>
  </select>
  <small class="field-help">Expedido en.</small>
</div>

<div class="form-group">
  <label>Complemento CI</label>
  <input
    v-model="form.complemento_ci"
    maxlength="10"
    placeholder="Ej. 1A"
    @input="form.complemento_ci = limpiarComplemento(form.complemento_ci)"
  />
  <small class="field-help">Opcional. Dejar vacío si no corresponde.</small>
</div>

            <div class="form-group">
              <label>Sexo</label>
              <select v-model="form.sexo">
                <option value="">Selecciona</option>
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="Otro">Otro</option>
              </select>
            </div>

            <div class="form-group">
              <label>Fecha de nacimiento</label>
              <input
                v-model="form.fecha_nacimiento"
                type="date"
                :min="fechaMinNacimiento"
                :max="fechaMaxNacimiento"
                />
                <small class="field-help">Debe ser mayor de edad, mínimo 18 años.</small>
            </div>

            <div class="form-group">
              <label>Teléfono</label>
              <input
                v-model="form.telefono"
                inputmode="numeric"
                maxlength="8"
                placeholder="Ej. 70000000"
                @input="form.telefono = soloNumeros(form.telefono, 8)"
              />
              <small class="field-help">Exactamente 8 dígitos si se registra.</small>
            </div>
          </div>
        </div>

        <!-- Datos agrícolas -->
        <div class="productor-form-section">
          <div class="productor-section-title">
            <i class="ti ti-plant"></i>
            Datos agrícolas
          </div>

          <div class="productor-form-grid">
            <div class="form-group span-2">
              <label>Comunidad *</label>
              <select v-model="form.id_comunidad">
                <option value="">Selecciona una comunidad</option>
                <option
                  v-for="c in comunidades"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.nombre_comunidad }} — {{ c.municipio || 'Sin municipio' }}
                  {{ c.activo === false ? '(Inactiva)' : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>Superficie total (ha)</label>
              <input
                v-model="form.superficie_total_ha"
                type="number"
                step="0.01"
                min="0"
                placeholder="Ej. 2.50"
              />
            </div>

            <div class="form-group span-3">
              <label>Dirección</label>
              <input
                v-model.trim="form.direccion"
                maxlength="255"
                placeholder="Dirección referencial del productor"
              />
            </div>

            <div class="form-group span-3">
              <label>Observaciones</label>
              <textarea
                v-model.trim="form.observaciones"
                placeholder="Notas adicionales..."
              ></textarea>
            </div>

            <div v-if="mode === 'edit'" class="form-group">
              <label>Estado</label>
              <select v-model="form.activo">
                <option :value="true">Activo</option>
                <option :value="false">Inactivo</option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="localError || error" class="productor-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear productor' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue';

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
  productor: {
    type: Object,
    default: null,
  },
  comunidades: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const form = reactive(defaultForm());

const fechaMaxNacimiento = computed(() => {
  const fecha = new Date();
  fecha.setFullYear(fecha.getFullYear() - 18);
  return fecha.toISOString().split('T')[0];
});

const fechaMinNacimiento = computed(() => {
  return '1900-01-01';
});

watch(
  () => [props.mode, props.productor, props.comunidades],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    id_comunidad: '',
    nombres: '',
    apellido_paterno: '',
    apellido_materno: '',
    carnet_identidad: '',
    ci_extension: '',
    complemento_ci: '',
    telefono: '',
    sexo: '',
    fecha_nacimiento: '',
    direccion: '',
    superficie_total_ha: '',
    observaciones: '',
    activo: true,
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.productor) {
    form.id_comunidad = props.productor.id_comunidad || '';
    form.nombres = soloLetras(props.productor.nombres || '');
    form.apellido_paterno = soloLetras(props.productor.apellido_paterno || '');
    form.apellido_materno = soloLetras(props.productor.apellido_materno || '');
    form.carnet_identidad = soloNumeros(props.productor.carnet_identidad || '', 10);
    form.ci_extension = props.productor.ci_extension || '';
    form.complemento_ci = limpiarComplemento(props.productor.complemento_ci || '');
    form.telefono = soloNumeros(props.productor.telefono || '', 8);
    form.sexo = props.productor.sexo || '';
    form.fecha_nacimiento = props.productor.fecha_nacimiento
      ? String(props.productor.fecha_nacimiento).split('T')[0]
      : '';
    form.direccion = props.productor.direccion || '';
    form.superficie_total_ha = props.productor.superficie_total_ha ?? '';
    form.observaciones = props.productor.observaciones || '';
    form.activo = Boolean(props.productor.activo);
    return;
  }

  Object.assign(form, defaultForm());
}

function soloLetras(value) {
  return String(value || '')
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]/g, '')
    .replace(/\s{2,}/g, ' ')
    .trimStart();
}

function soloNumeros(value, max = 8) {
  return String(value || '')
    .replace(/\D/g, '')
    .slice(0, max);
}

function limpiarComplemento(value) {
  return String(value || '')
    .replace(/[^A-Za-z0-9]/g, '')
    .toUpperCase()
    .slice(0, 10);
}

function validarTextoCampo(value, label, obligatorio = false) {
  const texto = String(value || '').trim();

  if (obligatorio && !texto) {
    return `${label} es obligatorio.`;
  }

  if (texto && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]+$/.test(texto)) {
    return `${label} solo debe contener letras y espacios.`;
  }

  return '';
}

function submit() {
  localError.value = '';

  form.nombres = soloLetras(form.nombres).trim();
  form.apellido_paterno = soloLetras(form.apellido_paterno).trim();
  form.apellido_materno = soloLetras(form.apellido_materno).trim();
  form.carnet_identidad = soloNumeros(form.carnet_identidad, 10);
  form.telefono = soloNumeros(form.telefono, 8);
  form.complemento_ci = limpiarComplemento(form.complemento_ci);

  let message = validarTextoCampo(form.nombres, 'Nombres', true);
  if (message) return showError(message);

  message = validarTextoCampo(form.apellido_paterno, 'Apellido paterno', true);
  if (message) return showError(message);

  message = validarTextoCampo(form.apellido_materno, 'Apellido materno', false);
  if (message) return showError(message);

  if (!form.carnet_identidad) {
    return showError('El carnet de identidad es obligatorio.');
  }

  if (form.carnet_identidad.length < 5 || form.carnet_identidad.length > 10) {
    return showError('El carnet de identidad debe tener entre 5 y 10 dígitos.');
  }

  if (!form.ci_extension) {
  return showError('Debe seleccionar la extensión del CI.');
}

  if (form.telefono && form.telefono.length !== 8) {
    return showError('El teléfono debe tener exactamente 8 dígitos.');
  }

  if (!form.id_comunidad) {
    return showError('Debe seleccionar una comunidad.');
  }

  if (form.fecha_nacimiento) {
  if (form.fecha_nacimiento < fechaMinNacimiento.value) {
    return showError('La fecha de nacimiento no puede ser anterior al año 1900.');
  }

  if (form.fecha_nacimiento > fechaMaxNacimiento.value) {
    return showError('El productor debe ser mayor de edad. Debe tener al menos 18 años.');
  }
}

  if (
    form.superficie_total_ha !== '' &&
    form.superficie_total_ha !== null &&
    Number(form.superficie_total_ha) < 0
  ) {
    return showError('La superficie total no puede ser negativa.');
  }

  const payload = {
    id_comunidad: form.id_comunidad,
    nombres: form.nombres,
    apellido_paterno: form.apellido_paterno,
    apellido_materno: form.apellido_materno || null,
    carnet_identidad: form.carnet_identidad,
    ci_extension: form.ci_extension,
    complemento_ci: form.complemento_ci || null,
    telefono: form.telefono || null,
    sexo: form.sexo || null,
    fecha_nacimiento: form.fecha_nacimiento || null,
    direccion: form.direccion || null,
    superficie_total_ha:
      form.superficie_total_ha !== '' && form.superficie_total_ha !== null
        ? Number(form.superficie_total_ha)
        : null,
    observaciones: form.observaciones || null,
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