<template>
  <div class="modal-overlay enfermedad-modal-overlay" @click.self="close">
    <div class="modal-card enfermedad-productos-modal">
      <div class="modal-header enfermedad-modal-header">
        <div>
          <div class="modal-title">
            Productos recomendados
          </div>
          <p class="enfermedad-modal-subtitle">
            Vincula insumos del inventario a
            <strong>{{ enfermedad?.nombre_comun || 'la enfermedad' }}</strong>.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body enfermedad-productos-body">
        <div v-if="error" class="enfermedad-form-error">
          {{ error }}
        </div>

        <div class="enfermedad-productos-layout">
          <!-- LISTADO -->
          <section class="enfermedad-productos-list-panel">
            <div class="enfermedad-productos-head">
              <div>
                <h3>Productos vinculados</h3>
                <p>
                  Insumos sugeridos para prevención, control o tratamiento.
                </p>
              </div>

              <button
                class="btn btn-p"
                type="button"
                @click="nuevoProducto"
              >
                <i class="ti ti-plus"></i>
                Vincular
              </button>
            </div>

            <div v-if="loading" class="enfermedad-productos-loading">
              <div class="spinner"></div>
              <span>Cargando productos recomendados...</span>
            </div>

            <div v-else-if="productosRecomendados.length === 0" class="enfermedad-empty-block">
              <i class="ti ti-package-off"></i>
              <span>No hay productos recomendados vinculados.</span>
            </div>

            <div v-else class="enfermedad-productos-list">
              <article
                v-for="producto in productosRecomendados"
                :key="producto.id"
                class="enfermedad-producto-recomendado-card"
              >
                <div class="enfermedad-producto-rec-top">
                  <div class="enfermedad-producto-rec-icon">
                    <i class="ti ti-package"></i>
                  </div>

                  <div>
                    <strong>{{ producto.nombre_producto }}</strong>
                    <small>
                      {{ producto.codigo_producto || 'Sin código' }}
                      · {{ producto.categoria || 'Sin categoría' }}
                    </small>
                  </div>
                </div>

                <div class="enfermedad-producto-rec-meta">
                  <span class="b bb">
                    {{ producto.pivot?.tipo_recomendacion || 'Sin tipo' }}
                  </span>

                  <span class="b" :class="prioridadClass(producto.pivot?.prioridad)">
                    Prioridad {{ producto.pivot?.prioridad || '—' }}
                  </span>
                </div>

                <div class="enfermedad-producto-rec-detail">
                  <label>Dosis recomendada</label>
                  <p>{{ producto.pivot?.dosis_recomendada || 'Sin dosis registrada.' }}</p>
                </div>

                <div class="enfermedad-producto-rec-detail">
                  <label>Observaciones</label>
                  <p>{{ producto.pivot?.observaciones || 'Sin observaciones.' }}</p>
                </div>

                <div class="enfermedad-producto-rec-stock">
                  <span>Stock disponible</span>
                  <strong>
                    {{ numero(stockTotal(producto)) }}
                    {{ producto.unidad_medida || '' }}
                  </strong>
                </div>

                <div class="enfermedad-producto-rec-actions">
                  <button
                    class="enfermedad-action edit"
                    type="button"
                    @click="editarProducto(producto)"
                  >
                    <i class="ti ti-pencil"></i>
                    Editar
                  </button>

                  <button
                    class="enfermedad-action state danger"
                    type="button"
                    :disabled="saving"
                    @click="desactivarProducto(producto)"
                  >
                    <i class="ti ti-ban"></i>
                    Desactivar
                  </button>
                </div>
              </article>
            </div>
          </section>

          <!-- FORMULARIO -->
          <section class="enfermedad-productos-form-panel">
            <div class="enfermedad-section-title">
              <i class="ti ti-prescription"></i>
              {{ editando ? 'Editar recomendación' : 'Nueva recomendación' }}
            </div>

            <div class="enfermedad-form-grid enfermedad-productos-form-grid">
              <div class="form-group span-3">
                <label>Producto *</label>
                <select
                  v-model="form.id_producto"
                  :disabled="editando"
                >
                  <option value="">Selecciona producto del inventario</option>

                  <option
                    v-for="producto in productosDisponibles"
                    :key="producto.id"
                    :value="producto.id"
                  >
                    {{ producto.nombre_producto }} — {{ producto.codigo_producto }}
                  </option>
                </select>

                <small class="enfermedad-productos-help">
                  Solo se muestran productos activos del inventario.
                </small>
              </div>

              <div class="form-group">
                <label>Tipo recomendación *</label>
                <select v-model="form.tipo_recomendacion">
                  <option value="Preventivo">Preventivo</option>
                  <option value="Curativo">Curativo</option>
                  <option value="Control">Control</option>
                  <option value="Apoyo">Apoyo</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>

              <div class="form-group">
                <label>Prioridad *</label>
                <select v-model="form.prioridad">
                  <option value="Alta">Alta</option>
                  <option value="Media">Media</option>
                  <option value="Baja">Baja</option>
                </select>
              </div>

              <div class="form-group">
                <label>Stock del producto</label>
                <div class="enfermedad-productos-stock-preview">
                  {{ numero(stockProductoSeleccionado) }}
                  {{ productoSeleccionado?.unidad_medida || '' }}
                </div>
              </div>

              <div class="form-group span-3">
                <label>Dosis recomendada</label>
                <input
                  v-model.trim="form.dosis_recomendada"
                  maxlength="150"
                  placeholder="Ej. 20 ml por mochila / 1 kg por hectárea"
                />
              </div>

              <div class="form-group span-3">
                <label>Observaciones</label>
                <textarea
                  v-model.trim="form.observaciones"
                  placeholder="Indicaciones de aplicación, frecuencia, restricciones o recomendaciones técnicas..."
                ></textarea>
              </div>
            </div>

            <div v-if="formError" class="enfermedad-form-error">
              {{ formError }}
            </div>

            <div class="enfermedad-productos-form-actions">
              <button
                class="btn btn-s"
                type="button"
                @click="resetForm"
              >
                Limpiar
              </button>

              <button
                class="btn btn-p"
                type="button"
                :disabled="saving"
                @click="guardarProducto"
              >
                <i class="ti ti-check"></i>
                {{ saving ? 'Guardando...' : editando ? 'Guardar cambios' : 'Vincular producto' }}
              </button>
            </div>
          </section>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

import productoService from '@/services/modules/productoService';
import productoRecomendadoService from '@/services/modules/productoRecomendadoService';
import { obtenerMensajeError } from '@/services/errorService';

const props = defineProps({
  enfermedad: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'updated']);

const loading = ref(true);
const saving = ref(false);

const error = ref('');
const formError = ref('');

const productos = ref([]);
const productosRecomendados = ref([]);

const productoEditando = ref(null);

const form = reactive(defaultForm());

const editando = computed(() => Boolean(productoEditando.value));

const productosDisponibles = computed(() => {
  const vinculados = productosRecomendados.value.map((item) => Number(item.id));

  if (editando.value) {
    return productos.value.filter((p) => p.activo !== false);
  }

  return productos.value.filter((p) => {
    return p.activo !== false && !vinculados.includes(Number(p.id));
  });
});

const productoSeleccionado = computed(() => {
  if (editando.value && productoEditando.value) {
    return productoEditando.value;
  }

  return productos.value.find((p) => String(p.id) === String(form.id_producto)) || null;
});

const stockProductoSeleccionado = computed(() => {
  return stockTotal(productoSeleccionado.value);
});

onMounted(cargarDatos);

async function cargarDatos() {
  if (!props.enfermedad?.id) {
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    await Promise.all([
      cargarProductos(),
      cargarProductosRecomendados(),
    ]);
  } catch (err) {
    console.error(err);
    error.value = obtenerMensajeError(err, 'No se pudieron cargar los productos recomendados.');
  } finally {
    loading.value = false;
  }
}

async function cargarProductos() {
  const { data } = await productoService.listar({
    todos: true,
    activo: true,
  });

  productos.value = Array.isArray(data)
    ? data
    : data.data ?? [];
}

async function cargarProductosRecomendados() {
  const { data } = await productoRecomendadoService.listar(props.enfermedad.id);

  productosRecomendados.value = Array.isArray(data)
    ? data
    : [];
}

function defaultForm() {
  return {
    id_producto: '',
    tipo_recomendacion: 'Control',
    prioridad: 'Media',
    dosis_recomendada: '',
    observaciones: '',
  };
}

function nuevoProducto() {
  resetForm();
}

function editarProducto(producto) {
  productoEditando.value = producto;

  form.id_producto = producto.id;
  form.tipo_recomendacion = producto.pivot?.tipo_recomendacion || 'Control';
  form.prioridad = producto.pivot?.prioridad || 'Media';
  form.dosis_recomendada = producto.pivot?.dosis_recomendada || '';
  form.observaciones = producto.pivot?.observaciones || '';
  formError.value = '';
}

function resetForm() {
  productoEditando.value = null;
  formError.value = '';
  Object.assign(form, defaultForm());
}

function validarForm() {
  if (!form.id_producto) {
    formError.value = 'Debe seleccionar un producto.';
    return false;
  }

  if (!form.tipo_recomendacion) {
    formError.value = 'Debe seleccionar el tipo de recomendación.';
    return false;
  }

  if (!form.prioridad) {
    formError.value = 'Debe seleccionar la prioridad.';
    return false;
  }

  return true;
}

async function guardarProducto() {
  if (!validarForm()) {
    return;
  }

  saving.value = true;
  formError.value = '';
  error.value = '';

  try {
    const payload = {
      id_producto: form.id_producto,
      tipo_recomendacion: form.tipo_recomendacion,
      prioridad: form.prioridad,
      dosis_recomendada: form.dosis_recomendada || null,
      observaciones: form.observaciones || null,
      activo: true,
    };

    if (editando.value) {
      await productoRecomendadoService.actualizar(
        props.enfermedad.id,
        productoEditando.value.id,
        {
          tipo_recomendacion: payload.tipo_recomendacion,
          prioridad: payload.prioridad,
          dosis_recomendada: payload.dosis_recomendada,
          observaciones: payload.observaciones,
          activo: true,
        }
      );
    } else {
      await productoRecomendadoService.vincular(
        props.enfermedad.id,
        payload
      );
    }

    await cargarProductosRecomendados();
    resetForm();
    emit('updated');
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'No se pudo guardar la recomendación.');
  } finally {
    saving.value = false;
  }
}

async function desactivarProducto(producto) {
  if (!producto?.id) {
    return;
  }

  const confirmar = window.confirm(
    `¿Deseas desactivar el producto recomendado "${producto.nombre_producto}"?`
  );

  if (!confirmar) {
    return;
  }

  saving.value = true;
  error.value = '';

  try {
    await productoRecomendadoService.desactivar(
      props.enfermedad.id,
      producto.id
    );

    await cargarProductosRecomendados();
    resetForm();
    emit('updated');
  } catch (err) {
    console.error(err);
    error.value = obtenerMensajeError(err, 'No se pudo desactivar el producto recomendado.');
  } finally {
    saving.value = false;
  }
}

function stockTotal(producto) {
  if (!producto?.inventarios) {
    return 0;
  }

  return producto.inventarios.reduce(
    (sum, item) => sum + (Number(item.stock_actual) || 0),
    0
  );
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function prioridadClass(prioridad) {
  return {
    Alta: 'br',
    Media: 'ba',
    Baja: 'bg',
  }[prioridad] || 'bgr';
}

function close() {
  emit('close');
}
</script>