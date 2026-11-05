<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal almacenes-modal">
      <div class="inventario-modal-head almacenes-modal-head">
        <div>
          <h2>
            {{ mode === 'create' ? 'Nuevo almacén' : 'Editar almacén' }}
          </h2>
          <p>
            Registra los datos operativos del almacén. El stock se administra desde inventario y movimientos.
          </p>
        </div>

        <button type="button" @click="close">
          ×
        </button>
      </div>

      <div class="almacenes-form-section">
        <div class="almacenes-section-title">
          <i class="ti ti-building-warehouse"></i>
          Datos del almacén
        </div>

        <div class="inventario-form-grid almacenes-form-grid">
          <div class="inventario-span-2">
            <label>Nombre del almacén *</label>
            <input
              v-model.trim="form.nombre_almacen"
              placeholder="Ej. Almacén Central"
              maxlength="150"
            />
          </div>

          <div>
            <label>Departamento *</label>
            <select v-model="form.departamento">
              <option value="">Seleccione</option>
              <option value="La Paz">La Paz</option>
              <option value="Cochabamba">Cochabamba</option>
              <option value="Santa Cruz">Santa Cruz</option>
              <option value="Oruro">Oruro</option>
              <option value="Potosí">Potosí</option>
              <option value="Chuquisaca">Chuquisaca</option>
              <option value="Tarija">Tarija</option>
              <option value="Beni">Beni</option>
              <option value="Pando">Pando</option>
            </select>
          </div>

          <div>
            <label>Capacidad máxima m³</label>
            <input
              v-model.number="form.capacidad_maxima_m3"
              type="number"
              min="0"
              step="0.01"
              placeholder="Ej. 100"
            />
          </div>

          <div>
            <label>Teléfono contacto</label>
            <input
              v-model.trim="form.telefono_contacto"
              maxlength="30"
              placeholder="Ej. 71234567"
            />
          </div>

          <div>
            <label>Estado</label>
            <select v-model="form.estado">
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
            </select>
          </div>

          <div class="inventario-span-2">
            <label>Ubicación</label>
            <input
              v-model.trim="form.ubicacion"
              maxlength="255"
              placeholder="Ej. Planta principal, zona norte"
            />
          </div>

          <div class="inventario-span-2">
            <label>Observaciones</label>
            <textarea
              v-model.trim="form.observaciones"
              rows="3"
              placeholder="Observaciones del almacén..."
            ></textarea>
          </div>
        </div>
      </div>

      <p v-if="localError || error" class="inventario-error almacenes-form-error">
        {{ localError || error }}
      </p>

      <div class="inventario-modal-actions almacenes-modal-actions">
        <button type="button" @click="close">
          Cancelar
        </button>

        <button
          class="inventario-btn-primary"
          type="button"
          :disabled="saving"
          @click="submit"
        >
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : 'Guardar almacén' }}
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
  almacen: {
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
  () => [props.mode, props.almacen],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    nombre_almacen: '',
    ubicacion: '',
    departamento: '',
    capacidad_maxima_m3: 0,
    telefono_contacto: '',
    observaciones: '',
    estado: 'Activo',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.almacen) {
    form.nombre_almacen = props.almacen.nombre_almacen || '';
    form.ubicacion = props.almacen.ubicacion || '';
    form.departamento = props.almacen.departamento || '';
    form.capacidad_maxima_m3 = Number(props.almacen.capacidad_maxima_m3) || 0;
    form.telefono_contacto = props.almacen.telefono_contacto || '';
    form.observaciones = props.almacen.observaciones || '';
    form.estado = props.almacen.estado || 'Activo';
    return;
  }

  Object.assign(form, defaultForm());
}

function submit() {
  localError.value = '';

  if (!form.nombre_almacen.trim()) {
    return showError('El nombre del almacén es obligatorio.');
  }

  if (!form.departamento) {
    return showError('Debe seleccionar el departamento.');
  }

  if (
    form.capacidad_maxima_m3 !== null &&
    form.capacidad_maxima_m3 !== '' &&
    Number(form.capacidad_maxima_m3) < 0
  ) {
    return showError('La capacidad máxima no puede ser negativa.');
  }

  emit('save', {
    nombre_almacen: form.nombre_almacen.trim(),
    ubicacion: form.ubicacion || null,
    departamento: form.departamento,
    capacidad_maxima_m3:
      form.capacidad_maxima_m3 !== null && form.capacidad_maxima_m3 !== ''
        ? Number(form.capacidad_maxima_m3)
        : 0,
    telefono_contacto: form.telefono_contacto || null,
    observaciones: form.observaciones || null,
    estado: form.estado || 'Activo',
  });
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>