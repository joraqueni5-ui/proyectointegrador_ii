<template>
  <div class="modal-overlay venta-modal-overlay" @click.self="close">
    <div class="modal-card venta-form-modal">
      <!-- HEADER -->
      <div class="venta-form-header">
        <div class="venta-form-header-icon">
          <i class="ti ti-shopping-cart"></i>
        </div>

        <div>
          <span>Nueva operación de venta</span>
          <h3>Nueva venta</h3>
          <p>
            Registra una venta directa, descuenta inventario y genera el movimiento de salida correspondiente.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="venta-form-body">
        <div class="venta-form-layout">
          <!-- IZQUIERDA -->
          <div class="venta-form-main">
            <!-- DATOS GENERALES -->
            <section class="venta-form-section">
              <div class="venta-section-title">
                <div>
                  <i class="ti ti-user-dollar"></i>
                  <strong>Datos de la venta</strong>
                </div>
                <span>Paso 1</span>
              </div>

              <div class="venta-form-grid">
                <div class="form-group span-2">
                  <label>Productor *</label>
                  <select v-model="form.id_productor">
                    <option value="">Seleccione productor</option>

                    <option
                      v-for="productor in productores"
                      :key="productor.id"
                      :value="productor.id"
                    >
                      {{ productor.nombre_completo }}
                    </option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Método de pago *</label>
                  <select v-model="form.metodo_pago" @change="ajustarEstadoPorPago">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="QR">QR</option>
                    <option value="Credito">Crédito</option>
                    <option value="Otro">Otro</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Estado</label>
                  <select
                    v-model="form.estado"
                    :disabled="form.metodo_pago !== 'Credito'"
                  >
                    <option
                      v-for="estado in estadosDisponibles"
                      :key="estado"
                      :value="estado"
                    >
                      {{ estado }}
                    </option>
                  </select>

                  <small class="venta-help">
                    En pagos al contado se marca como Pagada automáticamente.
                  </small>
                </div>

                <div class="form-group">
                  <label>Fecha emisión</label>
                  <input v-model="form.fecha_emision" type="date" />
                </div>

                <div class="form-group">
                  <label>Fecha vencimiento</label>
                  <input
                    v-model="form.fecha_vencimiento"
                    type="date"
                    :disabled="form.metodo_pago !== 'Credito'"
                  />

                  <small class="venta-help">
                    Solo aplica para ventas a crédito.
                  </small>
                </div>

                <div class="form-group">
                  <label>Descuento general</label>
                  <input
                    v-model.number="form.descuento"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                  />
                </div>

                <div class="form-group span-2">
                  <label>Observaciones</label>
                  <textarea
                    v-model.trim="form.observaciones"
                    placeholder="Observaciones generales de la venta..."
                  ></textarea>
                </div>
              </div>
            </section>

            <!-- PRODUCTOS -->
            <section class="venta-form-section">
              <div class="venta-section-title">
                <div>
                  <i class="ti ti-packages"></i>
                  <strong>Productos vendidos</strong>
                </div>

                <button
                  class="btn btn-s"
                  type="button"
                  @click="agregarDetalle"
                >
                  <i class="ti ti-plus"></i>
                  Agregar producto
                </button>
              </div>

              <div v-if="form.detalles.length === 0" class="venta-productos-empty">
                <i class="ti ti-package-off"></i>
                <span>Agrega al menos un producto para registrar la venta.</span>
              </div>

              <div v-else class="venta-productos-builder">
                <article
                  v-for="(detalle, index) in form.detalles"
                  :key="index"
                  class="venta-producto-row"
                >
                  <div class="venta-producto-index">
                    {{ index + 1 }}
                  </div>

                  <div class="venta-producto-fields">
                    <div class="form-group">
                      <label>Producto *</label>
                      <select
                        v-model="detalle.id_producto"
                        @change="seleccionarProducto(detalle)"
                      >
                        <option value="">Seleccione producto</option>

                        <option
                          v-for="producto in productosConStock"
                          :key="producto.id"
                          :value="producto.id"
                        >
                          {{ producto.nombre_producto }}
                          —
                          Stock:
                          {{ numero(stockProducto(producto)) }}
                          {{ producto.unidad_medida }}
                        </option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label>Almacén *</label>
                      <select v-model="detalle.id_almacen">
                        <option value="">Seleccione almacén</option>

                        <option
                          v-for="almacen in almacenesDisponiblesDetalle(detalle)"
                          :key="almacen.id"
                          :value="almacen.id"
                        >
                          {{ almacen.nombre_almacen }}
                          —
                          {{ numero(almacen.stock_actual) }} disp.
                        </option>
                      </select>

                      <small class="venta-help">
                        Disponible:
                        {{ numero(stockDisponibleDetalle(detalle)) }}
                        {{ unidadProductoDetalle(detalle) }}
                      </small>
                    </div>

                    <div class="form-group">
                      <label>Cantidad *</label>
                      <input
                        v-model.number="detalle.cantidad"
                        type="number"
                        :min="stepCantidadDetalle(detalle)"
                        :max="stockDisponibleDetalle(detalle)"
                        :step="stepCantidadDetalle(detalle)"
                        @blur="ajustarCantidad(detalle)"
                      />

                      <small
                        class="venta-help"
                        :class="Number(detalle.cantidad) > stockDisponibleDetalle(detalle) ? 'danger' : ''"
                      >
                        Máximo:
                        {{ numero(stockDisponibleDetalle(detalle)) }}
                        {{ unidadProductoDetalle(detalle) }}
                      </small>
                    </div>

                    <div class="form-group">
                      <label>Precio unitario</label>
                      <input
                        v-model.number="detalle.precio_unitario"
                        type="number"
                        readonly
                        class="venta-readonly"
                      />
                    </div>

                    <div class="venta-line-total">
                      <span>Subtotal</span>
                      <strong>Bs {{ numero(subtotalLinea(detalle)) }}</strong>
                    </div>
                  </div>

                  <button
                    class="venta-remove-line"
                    type="button"
                    title="Quitar producto"
                    @click="quitarDetalle(index)"
                  >
                    <i class="ti ti-trash"></i>
                  </button>
                </article>
              </div>
            </section>
          </div>

          <!-- DERECHA RESUMEN -->
          <aside class="venta-form-summary">
            <div class="venta-summary-card">
              <div class="venta-summary-icon">
                <i class="ti ti-cash-register"></i>
              </div>

              <h4>Resumen de venta</h4>
              <p>Vista previa del total y estado antes de registrar.</p>

              <div class="venta-summary-lines">
                <div>
                  <span>Productos</span>
                  <strong>{{ form.detalles.length }}</strong>
                </div>

                <div>
                  <span>Subtotal</span>
                  <strong>Bs {{ numero(subtotalVenta) }}</strong>
                </div>

                <div>
                  <span>Descuento</span>
                  <strong>Bs {{ numero(form.descuento) }}</strong>
                </div>

                <div>
                  <span>Estado</span>
                  <strong>{{ form.estado }}</strong>
                </div>
              </div>

              <div class="venta-summary-total">
                <span>Total a cobrar</span>
                <strong>Bs {{ numero(totalVenta) }}</strong>
              </div>

              <div class="venta-summary-warning">
                <i class="ti ti-alert-triangle"></i>
                Al registrar la venta, el sistema descontará stock y generará movimientos de salida.
              </div>
            </div>

            <div class="venta-summary-status">
              <div>
                <i class="ti ti-receipt"></i>
                <span>Comprobante</span>
              </div>

              <strong>Imprimible</strong>
            </div>
          </aside>
        </div>

        <div v-if="formError || error" class="venta-form-error">
          {{ formError || error }}
        </div>
      </div>

      <div class="modal-footer venta-form-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button
          class="btn btn-p"
          type="button"
          :disabled="saving"
          @click="guardar"
        >
          <i class="ti ti-device-floppy"></i>
          {{ saving ? 'Registrando...' : 'Registrar venta' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { stockProducto } from '@/utils/datos';

const props = defineProps({
  // Error devuelto por el servidor al guardar.
  error: {
    type: String,
    default: '',
  },
  productores: {
    type: Array,
    default: () => [],
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
});

const emit = defineEmits(['close', 'save']);

const formError = ref('');
const form = reactive(defaultForm());

const estadosDisponibles = computed(() => {
  if (form.metodo_pago === 'Credito') {
    return ['Pendiente', 'Parcial', 'Pagada'];
  }

  return ['Pagada'];
});

const productosConStock = computed(() => {
  return props.productos.filter((producto) => {
    return producto.activo !== false && stockProducto(producto) > 0;
  });
});

const subtotalVenta = computed(() => {
  return form.detalles.reduce((sum, detalle) => {
    return sum + subtotalLinea(detalle);
  }, 0);
});

const totalVenta = computed(() => {
  const descuento = Number(form.descuento) || 0;

  return Math.max(subtotalVenta.value - descuento, 0);
});

onMounted(() => {
  if (form.detalles.length === 0) {
    agregarDetalle();
  }
});

function defaultForm() {
  return {
    id_productor: '',
    tipo: 'Venta',
    metodo_pago: 'Efectivo',
    fecha_emision: new Date().toISOString().slice(0, 10),
    fecha_vencimiento: '',
    descuento: 0,
    observaciones: '',
    estado: 'Pagada',
    detalles: [],
  };
}

function ajustarEstadoPorPago() {
  if (form.metodo_pago === 'Credito') {
    form.estado = 'Pendiente';
  } else {
    form.estado = 'Pagada';
    form.fecha_vencimiento = '';
  }
}

function agregarDetalle() {
  form.detalles.push({
    id_producto: '',
    id_almacen: '',
    cantidad: 1,
    precio_unitario: 0,
    descuento_linea: 0,
  });
}

function quitarDetalle(index) {
  form.detalles.splice(index, 1);
}

function seleccionarProducto(detalle) {
  const producto = productoSeleccionado(detalle);

  if (!producto) {
    detalle.precio_unitario = 0;
    detalle.id_almacen = '';
    detalle.cantidad = null;
    return;
  }

  detalle.precio_unitario = Number(producto.precio_unitario) || 0;
  detalle.id_almacen = '';
  detalle.cantidad = stepCantidadDetalle(detalle);

  const disponibles = almacenesDisponiblesDetalle(detalle);

  if (disponibles.length === 1) {
    detalle.id_almacen = disponibles[0].id;
  }
}

function productoSeleccionado(detalle) {
  return props.productos.find((producto) => {
    return Number(producto.id) === Number(detalle.id_producto);
  });
}

function unidadProductoDetalle(detalle) {
  const producto = productoSeleccionado(detalle);

  return producto?.unidad_medida || '';
}

function stepCantidadDetalle(detalle) {
  const unidad = unidadProductoDetalle(detalle).toLowerCase();

  if (
    unidad.includes('kg') ||
    unidad.includes('litro') ||
    unidad === 'l'
  ) {
    return 0.5;
  }

  return 1;
}

function ajustarCantidad(detalle) {
  const step = stepCantidadDetalle(detalle);
  const stockDisponible = stockDisponibleDetalle(detalle);

  let cantidad = Number(detalle.cantidad) || step;

  cantidad = Math.round(cantidad / step) * step;

  if (cantidad < step) {
    cantidad = step;
  }

  if (stockDisponible > 0 && cantidad > stockDisponible) {
    cantidad = stockDisponible;
  }

  detalle.cantidad = Number(cantidad.toFixed(3));
}

function almacenesDisponiblesDetalle(detalle) {
  const producto = productoSeleccionado(detalle);

  if (!producto || !Array.isArray(producto.inventarios)) {
    return [];
  }

  return producto.inventarios
    .map((inventario) => {
      const idAlmacen = inventario.id_almacen || inventario.almacen?.id;

      const almacenBase = props.almacenes.find((almacen) => {
        return Number(almacen.id) === Number(idAlmacen);
      });

      return {
        id: idAlmacen,
        nombre_almacen:
          inventario.almacen?.nombre_almacen ||
          almacenBase?.nombre_almacen ||
          'Almacén',
        stock_actual: Number(inventario.stock_actual) || 0,
      };
    })
    .filter((almacen) => almacen.id && almacen.stock_actual > 0);
}

function stockDisponibleDetalle(detalle) {
  const producto = productoSeleccionado(detalle);

  if (!producto) {
    return 0;
  }

  if (!detalle.id_almacen) {
    return stockProducto(producto);
  }

  if (!Array.isArray(producto.inventarios)) {
    return 0;
  }

  const inventario = producto.inventarios.find((item) => {
    const idAlmacen = item.id_almacen || item.almacen?.id;

    return Number(idAlmacen) === Number(detalle.id_almacen);
  });

  return Number(inventario?.stock_actual) || 0;
}

function subtotalLinea(detalle) {
  const cantidad = Number(detalle.cantidad) || 0;
  const precio = Number(detalle.precio_unitario) || 0;
  const descuentoLinea = Number(detalle.descuento_linea) || 0;

  return Math.max((cantidad * precio) - descuentoLinea, 0);
}

function validarVenta() {
  formError.value = '';

  if (!form.id_productor) {
    formError.value = 'Debe seleccionar un productor.';
    return false;
  }

  if (!form.metodo_pago) {
    formError.value = 'Debe seleccionar un método de pago.';
    return false;
  }

  if (form.metodo_pago !== 'Credito') {
    form.estado = 'Pagada';
    form.fecha_vencimiento = '';
  }

  if (!form.detalles.length) {
    formError.value = 'Debe agregar al menos un producto.';
    return false;
  }

  if (Number(form.descuento) < 0) {
    formError.value = 'El descuento general no puede ser negativo.';
    return false;
  }

  if (Number(form.descuento) > subtotalVenta.value) {
    formError.value = 'El descuento general no puede ser mayor al subtotal.';
    return false;
  }

  for (const detalle of form.detalles) {
    if (!detalle.id_producto) {
      formError.value = 'Todos los detalles deben tener producto.';
      return false;
    }

    if (!detalle.id_almacen) {
      formError.value = 'Todos los detalles deben tener almacén.';
      return false;
    }

    if (!detalle.cantidad || Number(detalle.cantidad) <= 0) {
      formError.value = 'La cantidad debe ser mayor a 0.';
      return false;
    }

    const producto = productoSeleccionado(detalle);

    if (!producto) {
      formError.value = 'Uno de los productos seleccionados no es válido.';
      return false;
    }

    const stockDisponible = stockDisponibleDetalle(detalle);

    if (Number(detalle.cantidad) > stockDisponible) {
      formError.value =
        `Stock insuficiente para ${producto.nombre_producto}. ` +
        `Disponible: ${numero(stockDisponible)} ${producto.unidad_medida || ''}.`;

      return false;
    }
  }

  return true;
}

function payloadVenta() {
  return {
    id_productor: form.id_productor,
    tipo: 'Venta',
    metodo_pago: form.metodo_pago,
    fecha_emision: form.fecha_emision || null,
    fecha_vencimiento:
      form.metodo_pago === 'Credito'
        ? form.fecha_vencimiento || null
        : null,
    descuento: Number(form.descuento) || 0,
    observaciones: form.observaciones || null,
    estado: form.estado,
    detalles: form.detalles.map((detalle) => ({
      id_producto: detalle.id_producto,
      id_almacen: detalle.id_almacen,
      cantidad: Number(detalle.cantidad),
      precio_unitario: Number(detalle.precio_unitario) || 0,
      descuento_linea: Number(detalle.descuento_linea) || 0,
    })),
  };
}

function guardar() {
  if (!validarVenta()) {
    return;
  }

  emit('save', payloadVenta());
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function close() {
  emit('close');
}
</script>