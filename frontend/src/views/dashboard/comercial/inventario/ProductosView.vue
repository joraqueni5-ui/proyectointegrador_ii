<template>
  <section class="inventario-page productos-page fade-in">
    <!-- HEADER -->
    <div class="inventario-header productos-header">
      <div>
        <div class="productos-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <span>Comercial</span>
          <span>›</span>
          <strong>Productos</strong>
        </div>

        <h1>Gestión de productos</h1>
        <p>
          Catálogo de semillas, bioinsumos, agroquímicos, fertilizantes y otros insumos agrícolas.
        </p>
      </div>

      <div class="inventario-header-actions productos-header-actions">
        <button
          class="inventario-btn-secondary"
          type="button"
          :disabled="loading"
          @click="fetchProductos"
        >
          <i class="ti ti-refresh"></i>
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>

        <button
          v-if="puedeGestionar"
          class="inventario-btn-primary"
          type="button"
          @click="openCreate"
        >
          <i class="ti ti-plus"></i>
          Nuevo producto
        </button>
      </div>
    </div>

    <!-- ERROR GENERAL -->
    <div v-if="pageError" class="inventario-error productos-page-error">
      {{ pageError }}
    </div>

    <!-- KPIS -->
    <ProductosKpis
      v-if="!loading"
      :total-productos="productos.length"
      :total-activos="totalActivos"
      :total-bajo-stock="totalBajoStock"
      :total-inactivos="totalInactivos"
    />

    <!-- PANEL PRINCIPAL -->
    <div class="inventario-panel productos-panel">
      <ProductosFiltros
        v-model:search="search"
        v-model:categoria="filtroCategoria"
        :exporting="exporting"
        @buscar="fetchProductos"
        @limpiar="limpiarFiltros"
        @excel="exportarExcel"
        @pdf="exportarPdf"
      />

      <div v-if="loading" class="inventario-loading productos-loading">
        <div class="productos-spinner"></div>
        <span>Cargando productos...</span>
      </div>

      <ProductosTabla
        v-else
        :productos="productos"
        :puede-gestionar="puedeGestionar"
        @view="openView"
        @edit="openEdit"
        @toggle-state="openEstado"
      />
    </div>

    <!-- MODAL VER DETALLE -->
    <ProductoViewModal
      v-if="showViewModal"
      :producto="selectedProducto"
      @close="closeViewModal"
    />

    <!-- MODAL CREAR / EDITAR -->
    <ProductoFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :producto="selectedProducto"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <!-- MODAL ACTIVAR / DESACTIVAR -->
    <ProductoEstadoModal
      v-if="showEstadoModal"
      :producto="selectedProducto"
      :saving="saving"
      :error="estadoError"
      @close="closeEstadoModal"
      @confirm="handleCambiarEstado"
    />
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import '@/styles/inventario.css';

import productoService from '@/services/modules/productoService';
import reporteService from '@/services/modules/reporteService';
import { obtenerMensajeError } from '@/services/errorService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

import ProductosKpis from '@/components/productos/ProductosKpis.vue';
import ProductosFiltros from '@/components/productos/ProductosFiltros.vue';
import ProductosTabla from '@/components/productos/ProductosTabla.vue';
import ProductoFormModal from '@/components/productos/ProductoFormModal.vue';
import ProductoViewModal from '@/components/productos/ProductoViewModal.vue';
import ProductoEstadoModal from '@/components/productos/ProductoEstadoModal.vue';
import { stockProducto } from '@/utils/datos';
import { descargarBlob } from '@/services/api';

const productos = ref([]);

const loading = ref(true);
const saving = ref(false);
const exporting = ref(false);

const pageError = ref('');
const formError = ref('');
const estadoError = ref('');

const search = ref('');
const filtroCategoria = ref('');

const showViewModal = ref(false);
const showFormModal = ref(false);
const showEstadoModal = ref(false);

const modalMode = ref('create');
const selectedProducto = ref(null);

// Mismo permiso que exige el backend para modificar.
const puedeGestionar = tienePermiso(usuarioGuardado(), 'gestionar_inventario');

const totalActivos = computed(() => {
  return productos.value.filter((p) => p.activo).length;
});

const totalInactivos = computed(() => {
  return productos.value.filter((p) => !p.activo).length;
});

const totalBajoStock = computed(() => {
  return productos.value.filter((p) => {
    const minimo = Number(p.stock_minimo || 0);
    return minimo > 0 && stockProducto(p) <= minimo;
  }).length;
});

onMounted(fetchProductos);

async function fetchProductos() {
  loading.value = true;
  pageError.value = '';

  try {
    const params = {
      todos: true,
    };

    if (search.value) {
      params.search = search.value;
    }

    if (filtroCategoria.value) {
      params.categoria = filtroCategoria.value;
    }

    const { data } = await productoService.listar(params);

    productos.value = Array.isArray(data)
      ? data
      : data.data ?? [];
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los productos.');
    productos.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  selectedProducto.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  showFormModal.value = true;
}

function openEdit(producto) {
  selectedProducto.value = producto;
  modalMode.value = 'edit';
  pageError.value = '';
  showFormModal.value = true;
}

async function openView(producto) {
  pageError.value = '';

  try {
    const { data } = await productoService.obtener(producto.id);
    selectedProducto.value = data;
  } catch (err) {
    console.error(err);
    selectedProducto.value = producto;
  }

  showViewModal.value = true;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedProducto.value = null;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedProducto.value = null;
}

function openEstado(producto) {
  selectedProducto.value = producto;
  estadoError.value = '';
  pageError.value = '';
  showEstadoModal.value = true;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedProducto.value = null;
  estadoError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await productoService.crear(payload);
    } else {
      await productoService.actualizar(selectedProducto.value.id, payload);
    }

    await fetchProductos();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar producto.');
  } finally {
    saving.value = false;
  }
}

async function handleCambiarEstado() {
  if (!selectedProducto.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await productoService.cambiarEstado(selectedProducto.value.id);
    await fetchProductos();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(
      err,
      'Error al cambiar el estado del producto.'
    );
  } finally {
    saving.value = false;
  }
}

function limpiarFiltros() {
  search.value = '';
  filtroCategoria.value = '';
  fetchProductos();
}

function filtrosReporteProductos() {
  const params = {
    tipo: 'productos',
  };

  if (search.value) {
    params.busqueda = search.value;
  }

  if (filtroCategoria.value) {
    params.categoria = filtroCategoria.value;
  }

  return params;
}

async function exportarExcel() {
  exporting.value = true;
  pageError.value = '';

  try {
    const { data } = await reporteService.excel(filtrosReporteProductos());

    descargarBlob(
      data,
      'reporte_productos.xlsx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo exportar a Excel.');
  } finally {
    exporting.value = false;
  }
}

async function exportarPdf() {
  exporting.value = true;
  pageError.value = '';

  try {
    const { data } = await reporteService.pdf(filtrosReporteProductos());

    descargarBlob(
      data,
      'reporte_productos.pdf',
      'application/pdf'
    );
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo exportar a PDF.');
  } finally {
    exporting.value = false;
  }
}

</script>