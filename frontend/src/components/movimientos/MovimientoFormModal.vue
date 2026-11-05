<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal movimientos-modal">
      <div class="inventario-modal-head movimientos-modal-head">
        <div>
          <h2>Registrar movimiento</h2>
          <p>
            Registra entradas o salidas. El stock se actualiza según el producto y almacén seleccionado.
          </p>
        </div>

        <button type="button" @click="close">
          ×
        </button>
      </div>

      <div class="movimientos-form-section">
        <div class="movimientos-section-title">
          <i class="ti ti-arrows-transfer-down"></i>
          Datos del movimiento
        </div>

        <div class="movimientos-tipo-selector">
          <button
            type="button"
            :class="form.tipo === 'Entrada' ? 'active entrada' : ''"
            @click="setTipo('Entrada')"
          >
            <i class="ti ti-arrow-down-to-arc"></i>
            Entrada
          </button>

          <button
            type="button"
            :class="form.tipo === 'Salida' ? 'active salida' : ''"
            @click="setTipo('Salida')"
          >
            <i class="ti ti-arrow-up-from-arc"></i>
            Salida
          </button>
        </div>

        <div class="inventario-form-grid movimientos-form-grid">
          <div class="inventario-span-2">
            <label>Producto *</label>
            <select v-model="form.id_producto">
              <option value="">Seleccione producto</option>
              <option
                v-for="producto in productosActivos"
                :key="producto.id"
                :value="producto.id"
              >
                {{ producto.nombre_producto }} — {{ producto.codigo_producto }}
              </option>
            </select>
          </div>

          <div>
            <label>Almacén *</label>
            <select v-model="form.id_almacen">
              <option value="">Seleccione almacén</option>
              <option
                v-for="almacen in almacenesActivos"
                :key="almacen.id"
                :value="almacen.id"
              >
                {{ almacen.nombre_almacen }}
              </option>
            </select>
          </div>

          <div>
            <label>Cantidad *</label>
            <input
              v-model.number="form.cantidad"
              type="number"
              min="0.001"
              step="0.001"
              placeholder="Ej. 10"
            />
          </div>

          <div>
            <label>Motivo *</label>
            <select v-model="form.motivo">
              <option
                v-for="motivo in motivosDisponibles"
                :key="motivo"
                :value="motivo"
              >
                {{ textoMotivo(motivo) }}
              </option>
            </select>
          </div>

          <div>
            <label>Fecha</label>
            <input
              v-model="form.fecha_movimiento"
              type="date"
              :max="today"
            />
          </div>

          <div class="inventario-span-2">
            <div
              class="movimientos-stock-preview"
              :class="form.tipo === 'Salida' && cantidadMayorAlStock ? 'danger' : ''"
            >
              <div>
                <span>Stock disponible en este almacén</span>
                <strong>
                  {{ numero(stockDisponible) }}
                  {{ productoSeleccionado?.unidad_medida || '' }}
                </strong>
              </div>

              <small v-if="!form.id_producto || !form.id_almacen">
                Selecciona producto y almacén para ver el stock específico.
              </small>

              <small v-else-if="form.tipo === 'Salida' && cantidadMayorAlStock">
                La salida supera el stock disponible en el almacén seleccionado.
              </small>

              <small v-else>
                El stock se calcula por producto y almacén, no por stock total general.
              </small>
            </div>
          </div>

          <div class="inventario-span-2">
            <label>Observaciones</label>
            <textarea
              v-model.trim="form.observaciones"
              rows="3"
              placeholder="Detalle del movimiento, documento de respaldo, responsable, motivo adicional..."
            ></textarea>
          </div>
        </div>
      </div>

      <p v-if="localError || error" class="inventario-error movimientos-form-error">
        {{ localError || error }}
      </p>

      <div class="inventario-modal-actions movimientos-modal-actions">
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
          {{ saving ? 'Registrando...' : 'Registrar movimiento' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  // Error devuelto por el servidor al guardar.
  error: {
    type: String,
    default: '',
  },
  productos: {
    type: Array,
    default: () => [],
  },
  almacenes: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
  defaultTipo: {
    type: String,
    default: 'Entrada',
  },
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const today = new Date().toISOString().slice(0, 10);

const motivosEntrada = [
  'Compra',
  'Devolucion',
  'Ajuste_entrada',
  'Traslado_entrada',
  'Otro',
];

const motivosSalida = [
  'Venta',
  'Ajuste_salida',
  'Traslado_salida',
  'Otro',
];

const form = reactive(defaultForm());

const productosActivos = computed(() => {
  return props.productos.filter((producto) => producto.activo !== false);
});

const almacenesActivos = computed(() => {
  return props.almacenes.filter((almacen) => almacen.estado !== 'Inactivo');
});

const motivosDisponibles = computed(() => {
  return form.tipo === 'Entrada'
    ? motivosEntrada
    : motivosSalida;
});

const productoSeleccionado = computed(() => {
  return props.productos.find((producto) => String(producto.id) === String(form.id_producto)) || null;
});

const stockDisponible = computed(() => {
  const producto = productoSeleccionado.value;

  if (!producto || !form.id_almacen) {
    return 0;
  }

  if (!Array.isArray(producto.inventarios)) {
    return 0;
  }

  const inventario = producto.inventarios.find(
    (item) => String(item.id_almacen) === String(form.id_almacen)
  );

  return Number(inventario?.stock_actual || 0);
});

const cantidadMayorAlStock = computed(() => {
  if (form.tipo !== 'Salida') {
    return false;
  }

  return Number(form.cantidad || 0) > stockDisponible.value;
});

watch(
  () => props.defaultTipo,
  () => {
    resetForm(props.defaultTipo);
  },
  { immediate: true }
);

function defaultForm(tipo = 'Entrada') {
  return {
    id_producto: '',
    id_almacen: '',
    tipo,
    cantidad: null,
    motivo: tipo === 'Entrada' ? 'Compra' : 'Ajuste_salida',
    fecha_movimiento: today,
    observaciones: '',
  };
}

function resetForm(tipo = 'Entrada') {
  localError.value = '';
  Object.assign(form, defaultForm(tipo));

  if (almacenesActivos.value.length === 1) {
    form.id_almacen = almacenesActivos.value[0].id;
  }
}

function setTipo(tipo) {
  form.tipo = tipo;
  form.motivo = tipo === 'Entrada' ? 'Compra' : 'Ajuste_salida';
}

function submit() {
  localError.value = '';

  if (!form.id_producto) {
    return showError('Debe seleccionar un producto.');
  }

  if (!form.id_almacen) {
    return showError('Debe seleccionar un almacén.');
  }

  if (!form.tipo) {
    return showError('Debe seleccionar el tipo de movimiento.');
  }

  if (
    form.cantidad === null ||
    form.cantidad === '' ||
    Number(form.cantidad) <= 0
  ) {
    return showError('La cantidad debe ser mayor a 0.');
  }

  if (!form.motivo) {
    return showError('Debe seleccionar el motivo.');
  }

  if (form.fecha_movimiento && form.fecha_movimiento > today) {
    return showError('La fecha del movimiento no puede ser futura.');
  }

  if (form.tipo === 'Salida' && cantidadMayorAlStock.value) {
    return showError('No se puede registrar una salida mayor al stock disponible en este almacén.');
  }

  emit('save', {
    id_producto: form.id_producto,
    id_almacen: form.id_almacen,
    tipo: form.tipo,
    cantidad: Number(form.cantidad),
    motivo: form.motivo,
    fecha_movimiento: form.fecha_movimiento || null,
    observaciones: form.observaciones || null,
  });
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function textoMotivo(motivo) {
  return {
    Compra: 'Compra',
    Venta: 'Venta',
    Devolucion: 'Devolución',
    Ajuste_entrada: 'Ajuste entrada',
    Ajuste_salida: 'Ajuste salida',
    Traslado_entrada: 'Traslado entrada',
    Traslado_salida: 'Traslado salida',
    Otro: 'Otro',
  }[motivo] || motivo;
}
</script>