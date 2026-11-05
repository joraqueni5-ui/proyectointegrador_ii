<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal productos-modal">
      <div class="inventario-modal-head productos-modal-head">
        <div>
          <h2>
            {{ mode === 'create' ? 'Nuevo producto' : 'Editar producto' }}
          </h2>
          <p>
            Registra la información comercial del producto. El stock se administra desde movimientos.
          </p>
        </div>

        <button type="button" @click="close">
          ×
        </button>
      </div>

      <div class="productos-form-section">
        <div class="productos-section-title">
          <i class="ti ti-package"></i>
          Datos principales
        </div>

        <div class="inventario-form-grid productos-form-grid">
          <div>
            <label>Código *</label>
            <input
              v-model.trim="form.codigo_producto"
              placeholder="Ej. S-001"
              maxlength="30"
              @input="form.codigo_producto = form.codigo_producto.toUpperCase()"
            />
          </div>

          <div>
            <label>Nombre *</label>
            <input
              v-model.trim="form.nombre_producto"
              placeholder="Ej. Semilla Desire"
              maxlength="200"
            />
          </div>

          <div>
            <label>Categoría *</label>
            <select v-model="form.categoria">
              <option value="">Seleccione</option>
              <option value="Semilla">Semilla</option>
              <option value="Bioinsumo">Bioinsumo</option>
              <option value="Agroquimico">Agroquímico</option>
              <option value="Fertilizante">Fertilizante</option>
              <option value="Equipo">Equipo</option>
              <option value="Otro">Otro</option>
            </select>
          </div>

          <div>
            <label>Unidad de medida *</label>
            <input
              v-model.trim="form.unidad_medida"
              placeholder="kg, litros, unidades"
              maxlength="30"
            />
          </div>

          <div>
            <label>Precio unitario *</label>
            <input
              v-model.number="form.precio_unitario"
              type="number"
              min="0"
              step="0.01"
            />
          </div>

          <div>
            <label>Precio costo</label>
            <input
              v-model.number="form.precio_costo"
              type="number"
              min="0"
              step="0.01"
            />
          </div>

          <div>
            <label>Stock mínimo referencial</label>
            <input
              v-model.number="form.stock_minimo"
              type="number"
              min="0"
              step="0.001"
            />
            <small class="productos-field-help">
              Referencial del producto. El stock real se controla por almacén.
            </small>
          </div>

          <div class="productos-span-2">
            <label>Imagen URL</label>
            <input
              v-model.trim="form.imagen_url"
              placeholder="https://..."
              maxlength="500"
            />
          </div>

          <div class="inventario-span-2 productos-span-3">
            <label>Descripción</label>
            <textarea
              v-model.trim="form.descripcion"
              rows="3"
              placeholder="Descripción, uso, presentación o detalles técnicos del producto..."
            ></textarea>
          </div>
        </div>
      </div>

      <p v-if="localError || error" class="inventario-error productos-form-error">
        {{ localError || error }}
      </p>

      <div class="inventario-modal-actions productos-modal-actions">
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
          {{ saving ? 'Guardando...' : 'Guardar producto' }}
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
  producto: {
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
  () => [props.mode, props.producto],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    codigo_producto: '',
    nombre_producto: '',
    categoria: '',
    descripcion: '',
    unidad_medida: '',
    precio_unitario: 0,
    precio_costo: null,
    stock_minimo: 0,
    imagen_url: '',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.producto) {
    form.codigo_producto = props.producto.codigo_producto || '';
    form.nombre_producto = props.producto.nombre_producto || '';
    form.categoria = props.producto.categoria || '';
    form.descripcion = props.producto.descripcion || '';
    form.unidad_medida = props.producto.unidad_medida || '';
    form.precio_unitario = Number(props.producto.precio_unitario) || 0;
    form.precio_costo =
      props.producto.precio_costo !== null && props.producto.precio_costo !== undefined
        ? Number(props.producto.precio_costo)
        : null;
    form.stock_minimo = Number(props.producto.stock_minimo) || 0;
    form.imagen_url = props.producto.imagen_url || '';
    return;
  }

  Object.assign(form, defaultForm());
}

function submit() {
  localError.value = '';

  if (!form.codigo_producto.trim()) {
    return showError('El código del producto es obligatorio.');
  }

  if (!form.nombre_producto.trim()) {
    return showError('El nombre del producto es obligatorio.');
  }

  if (!form.categoria) {
    return showError('Debe seleccionar una categoría.');
  }

  if (!form.unidad_medida.trim()) {
    return showError('La unidad de medida es obligatoria.');
  }

  if (form.precio_unitario === null || form.precio_unitario === '') {
    return showError('El precio unitario es obligatorio.');
  }

  if (Number(form.precio_unitario) < 0) {
    return showError('El precio unitario no puede ser negativo.');
  }

  if (
    form.precio_costo !== null &&
    form.precio_costo !== '' &&
    Number(form.precio_costo) < 0
  ) {
    return showError('El precio de costo no puede ser negativo.');
  }

  if (
    form.stock_minimo !== null &&
    form.stock_minimo !== '' &&
    Number(form.stock_minimo) < 0
  ) {
    return showError('El stock mínimo no puede ser negativo.');
  }

  emit('save', {
    codigo_producto: form.codigo_producto.trim().toUpperCase(),
    nombre_producto: form.nombre_producto.trim(),
    categoria: form.categoria,
    descripcion: form.descripcion || null,
    unidad_medida: form.unidad_medida.trim(),
    precio_unitario: Number(form.precio_unitario) || 0,
    precio_costo:
      form.precio_costo !== null && form.precio_costo !== ''
        ? Number(form.precio_costo)
        : null,
    stock_minimo:
      form.stock_minimo !== null && form.stock_minimo !== ''
        ? Number(form.stock_minimo)
        : 0,
    imagen_url: form.imagen_url || null,
  });
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>