<template>
  <section class="inventario-page almacenes-page fade-in">
    <!-- HEADER -->
    <div class="inventario-header almacenes-header">
      <div>
        <div class="almacenes-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <span>Comercial</span>
          <span>›</span>
          <strong>Almacenes</strong>
        </div>

        <h1>Gestión de almacenes</h1>
        <p>
          Administra los espacios donde se resguardan semillas, bioinsumos e insumos agrícolas.
        </p>
      </div>

      <div class="inventario-header-actions almacenes-header-actions">
        <button
          class="inventario-btn-secondary"
          type="button"
          :disabled="loading"
          @click="fetchAlmacenes"
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
          Nuevo almacén
        </button>
      </div>
    </div>

    <!-- ERROR GENERAL -->
    <div v-if="pageError" class="inventario-error almacenes-page-error">
      {{ pageError }}
    </div>

    <!-- KPIS -->
    <AlmacenesKpis
      v-if="!loading"
      :total-almacenes="almacenes.length"
      :total-activos="totalActivos"
      :total-inactivos="totalInactivos"
      :total-con-stock="totalConStock"
    />

    <!-- PANEL PRINCIPAL -->
    <div class="inventario-panel almacenes-panel">
      <AlmacenesFiltros
        v-model:search="search"
        v-model:estado="filtroEstado"
        v-model:departamento="filtroDepartamento"
        @buscar="fetchAlmacenes"
        @limpiar="limpiarFiltros"
      />

      <div v-if="loading" class="inventario-loading almacenes-loading">
        <div class="almacenes-spinner"></div>
        <span>Cargando almacenes...</span>
      </div>

      <AlmacenesTabla
        v-else
        :almacenes="almacenes"
        :puede-gestionar="puedeGestionar"
        @view="openView"
        @edit="openEdit"
        @toggle-state="openEstado"
      />
    </div>

    <!-- MODAL VER DETALLE -->
    <AlmacenViewModal
      v-if="showViewModal"
      :almacen="selectedAlmacen"
      @close="closeViewModal"
    />

    <!-- MODAL CREAR / EDITAR -->
    <AlmacenFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :almacen="selectedAlmacen"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <!-- MODAL ACTIVAR / DESACTIVAR -->
    <AlmacenEstadoModal
      v-if="showEstadoModal"
      :almacen="selectedAlmacen"
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

import almacenService from '@/services/modules/almacenService';
import { obtenerMensajeError } from '@/services/errorService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

import AlmacenesKpis from '@/components/almacenes/AlmacenesKpis.vue';
import AlmacenesFiltros from '@/components/almacenes/AlmacenesFiltros.vue';
import AlmacenesTabla from '@/components/almacenes/AlmacenesTabla.vue';
import AlmacenFormModal from '@/components/almacenes/AlmacenFormModal.vue';
import AlmacenViewModal from '@/components/almacenes/AlmacenViewModal.vue';
import AlmacenEstadoModal from '@/components/almacenes/AlmacenEstadoModal.vue';

const almacenes = ref([]);

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const estadoError = ref('');

const search = ref('');
const filtroEstado = ref('');
const filtroDepartamento = ref('');

const showViewModal = ref(false);
const showFormModal = ref(false);
const showEstadoModal = ref(false);

const modalMode = ref('create');
const selectedAlmacen = ref(null);

// Mismo permiso que exige el backend para modificar.
const puedeGestionar = tienePermiso(usuarioGuardado(), 'gestionar_almacenes');

const totalActivos = computed(() => {
  return almacenes.value.filter((a) => a.estado === 'Activo').length;
});

const totalInactivos = computed(() => {
  return almacenes.value.filter((a) => a.estado === 'Inactivo').length;
});

const totalConStock = computed(() => {
  return almacenes.value.filter((a) => stockTotal(a) > 0).length;
});

onMounted(fetchAlmacenes);

async function fetchAlmacenes() {
  loading.value = true;
  pageError.value = '';

  try {
    const params = {
      todos: true,
    };

    if (search.value) {
      params.search = search.value;
    }

    if (filtroEstado.value) {
      params.estado = filtroEstado.value;
    }

    if (filtroDepartamento.value) {
      params.departamento = filtroDepartamento.value;
    }

    const { data } = await almacenService.listar(params);

    almacenes.value = Array.isArray(data)
      ? data
      : data.data ?? [];
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los almacenes.');
    almacenes.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  selectedAlmacen.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  showFormModal.value = true;
}

function openEdit(almacen) {
  selectedAlmacen.value = almacen;
  modalMode.value = 'edit';
  pageError.value = '';
  showFormModal.value = true;
}

async function openView(almacen) {
  pageError.value = '';

  try {
    const { data } = await almacenService.obtener(almacen.id);
    selectedAlmacen.value = data;
  } catch (err) {
    console.error(err);
    selectedAlmacen.value = almacen;
  }

  showViewModal.value = true;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedAlmacen.value = null;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedAlmacen.value = null;
}

function openEstado(almacen) {
  selectedAlmacen.value = almacen;
  estadoError.value = '';
  pageError.value = '';
  showEstadoModal.value = true;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedAlmacen.value = null;
  estadoError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await almacenService.crear(payload);
    } else {
      await almacenService.actualizar(selectedAlmacen.value.id, payload);
    }

    await fetchAlmacenes();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar almacén.');
  } finally {
    saving.value = false;
  }
}

async function handleCambiarEstado() {
  if (!selectedAlmacen.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await almacenService.cambiarEstado(selectedAlmacen.value.id);
    await fetchAlmacenes();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(
      err,
      'Error al cambiar el estado del almacén.'
    );
  } finally {
    saving.value = false;
  }
}

function limpiarFiltros() {
  search.value = '';
  filtroEstado.value = '';
  filtroDepartamento.value = '';
  fetchAlmacenes();
}

function stockTotal(almacen) {
  if (!almacen) {
    return 0;
  }

  if (almacen.stock_total !== null && almacen.stock_total !== undefined) {
    return Number(almacen.stock_total) || 0;
  }

  if (Array.isArray(almacen.inventarios)) {
    return almacen.inventarios.reduce(
      (sum, item) => sum + (Number(item.stock_actual) || 0),
      0
    );
  }

  return 0;
}
</script>