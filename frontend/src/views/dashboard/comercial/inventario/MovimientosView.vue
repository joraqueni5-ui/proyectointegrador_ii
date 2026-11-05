<template>
  <section class="inventario-page movimientos-page fade-in">
    <!-- HEADER -->
    <div class="inventario-header movimientos-header">
      <div>
        <div class="movimientos-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <span>Comercial</span>
          <span>›</span>
          <strong>Movimientos</strong>
        </div>

        <h1>Movimientos de inventario</h1>
        <p>
          Registra entradas y salidas de productos por almacén. Estos movimientos actualizan el stock real.
        </p>
      </div>

      <div class="inventario-header-actions movimientos-header-actions">
        <button
          class="inventario-btn-secondary"
          type="button"
          :disabled="loading"
          @click="cargarTodo"
        >
          <i class="ti ti-refresh"></i>
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>

        <button
          v-if="puedeGestionar"
          class="inventario-btn-secondary movimientos-btn-entrada"
          type="button"
          @click="openModal('Entrada')"
        >
          <i class="ti ti-arrow-down-to-arc"></i>
          Entrada
        </button>

        <button
          v-if="puedeGestionar"
          class="inventario-btn-primary movimientos-btn-salida"
          type="button"
          @click="openModal('Salida')"
        >
          <i class="ti ti-arrow-up-from-arc"></i>
          Salida
        </button>
      </div>
    </div>

    <!-- ERROR GENERAL -->
    <div v-if="pageError" class="inventario-error movimientos-page-error">
      {{ pageError }}
    </div>

    <!-- KPIS -->
    <MovimientosKpis
      v-if="!loading"
      :total-movimientos="totalMovimientos"
      :total-entradas="totalEntradas"
      :total-salidas="totalSalidas"
      :movimientos-mes="movimientosMes"
    />

    <!-- PANEL PRINCIPAL -->
    <div class="inventario-panel movimientos-panel">
      <MovimientosFiltros
        v-model:id-producto="filtros.id_producto"
        v-model:id-almacen="filtros.id_almacen"
        v-model:tipo="filtros.tipo"
        :productos="productos"
        :almacenes="almacenes"
        @buscar="buscarMovimientos"
        @limpiar="limpiarFiltros"
      />

      <div v-if="loading" class="inventario-loading movimientos-loading">
        <div class="movimientos-spinner"></div>
        <span>Cargando movimientos...</span>
      </div>

      <template v-else>
        <MovimientosTabla
          :movimientos="movimientos"
          @view="openDetalle"
        />

        <MovimientosPaginacion
          :pagination="pagination"
          @change-page="cambiarPagina"
        />
      </template>
    </div>

    <!-- MODAL REGISTRAR MOVIMIENTO -->
    <MovimientoFormModal
      v-if="showFormModal"
      :productos="productos"
      :almacenes="almacenes"
      :saving="saving"
      :default-tipo="defaultTipo"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <!-- MODAL DETALLE -->
    <MovimientoViewModal
      v-if="showDetalleModal"
      :movimiento="selectedMovimiento"
      @close="closeDetalleModal"
    />
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import '@/styles/inventario.css';

import movimientoService from '@/services/modules/movimientoService';
import productoService from '@/services/modules/productoService';
import almacenService from '@/services/modules/almacenService';
import { obtenerMensajeError } from '@/services/errorService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

import MovimientosKpis from '@/components/movimientos/MovimientosKpis.vue';
import MovimientosFiltros from '@/components/movimientos/MovimientosFiltros.vue';
import MovimientosTabla from '@/components/movimientos/MovimientosTabla.vue';
import MovimientoFormModal from '@/components/movimientos/MovimientoFormModal.vue';
import MovimientoViewModal from '@/components/movimientos/MovimientoViewModal.vue';
import MovimientosPaginacion from '@/components/movimientos/MovimientosPaginacion.vue';

const movimientos = ref([]);
const productos = ref([]);
const almacenes = ref([]);

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');

const pagination = ref(null);
const paginaActual = ref(1);

const showFormModal = ref(false);
const showDetalleModal = ref(false);

const selectedMovimiento = ref(null);
const defaultTipo = ref('Entrada');

const filtros = ref({
  id_producto: '',
  id_almacen: '',
  tipo: '',
});

// Mismo permiso que exige el backend para modificar.
const puedeGestionar = tienePermiso(usuarioGuardado(), 'gestionar_inventario');

const totalMovimientos = computed(() => {
  return pagination.value?.total ?? movimientos.value.length;
});

const totalEntradas = computed(() => {
  return movimientos.value.filter((mov) => mov.tipo === 'Entrada').length;
});

const totalSalidas = computed(() => {
  return movimientos.value.filter((mov) => mov.tipo === 'Salida').length;
});

const movimientosMes = computed(() => {
  const ahora = new Date();
  const mes = ahora.getMonth();
  const anio = ahora.getFullYear();

  return movimientos.value.filter((mov) => {
    if (!mov.fecha_movimiento) {
      return false;
    }

    const fecha = new Date(mov.fecha_movimiento);

    return fecha.getMonth() === mes && fecha.getFullYear() === anio;
  }).length;
});

onMounted(cargarTodo);

async function cargarTodo() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchMovimientos(),
      fetchProductos(),
      fetchAlmacenes(),
    ]);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar la información de movimientos.');
  } finally {
    loading.value = false;
  }
}

async function fetchMovimientos() {
  const params = {
    page: paginaActual.value,
    per_page: 15,
  };

  if (filtros.value.id_producto) {
    params.id_producto = filtros.value.id_producto;
  }

  if (filtros.value.id_almacen) {
    params.id_almacen = filtros.value.id_almacen;
  }

  if (filtros.value.tipo) {
    params.tipo = filtros.value.tipo;
  }

  const { data } = await movimientoService.listar(params);

  movimientos.value = Array.isArray(data)
    ? data
    : data.data ?? [];

  pagination.value = data.current_page
    ? {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    : null;
}

async function fetchProductos() {
  const { data } = await productoService.listar({
    todos: true,
  });

  productos.value = Array.isArray(data)
    ? data
    : data.data ?? [];
}

async function fetchAlmacenes() {
  const { data } = await almacenService.listar({
    todos: true,
  });

  almacenes.value = Array.isArray(data)
    ? data
    : data.data ?? [];
}

function buscarMovimientos() {
  paginaActual.value = 1;
  loading.value = true;
  pageError.value = '';

  fetchMovimientos()
    .catch((err) => {
      console.error(err);
      pageError.value = obtenerMensajeError(err, 'No se pudieron filtrar los movimientos.');
    })
    .finally(() => {
      loading.value = false;
    });
}

function cambiarPagina(pagina) {
  paginaActual.value = pagina;
  loading.value = true;
  pageError.value = '';

  fetchMovimientos()
    .catch((err) => {
      console.error(err);
      pageError.value = obtenerMensajeError(err, 'No se pudo cambiar de página.');
    })
    .finally(() => {
      loading.value = false;
    });
}

function limpiarFiltros() {
  filtros.value = {
    id_producto: '',
    id_almacen: '',
    tipo: '',
  };

  paginaActual.value = 1;
  buscarMovimientos();
}

function openModal(tipo = 'Entrada') {
  defaultTipo.value = tipo;
  pageError.value = '';
  showFormModal.value = true;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    await movimientoService.registrar(payload);

    await Promise.all([
      fetchMovimientos(),
      fetchProductos(),
      fetchAlmacenes(),
    ]);

    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al registrar movimiento.');
  } finally {
    saving.value = false;
  }
}

function openDetalle(movimiento) {
  selectedMovimiento.value = movimiento;
  showDetalleModal.value = true;
}

function closeDetalleModal() {
  showDetalleModal.value = false;
  selectedMovimiento.value = null;
}
</script>