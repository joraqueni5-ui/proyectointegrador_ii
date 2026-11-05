<template>
  <section class="cotizaciones-page fade-in">
    <!-- CONTEXTBAR -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Comercial</span>
        <span>›</span>
        <span class="cur">Cotizaciones</span>
      </div>

      <div class="cbar-right">
        <button
          class="btn btn-p"
          type="button"
          @click="openCreate"
        >
          <i class="ti ti-file-plus"></i>
          Nueva cotización
        </button>
      </div>
    </div>

    <!-- HEADER -->
    <div class="pg-header cotizaciones-header">
      <div>
        <div class="pg-title">Cotizaciones comerciales</div>
        <div class="pg-sub">
          Gestiona propuestas de venta antes de confirmar salida de inventario.
        </div>
      </div>

      <button
        class="btn btn-s"
        type="button"
        :disabled="loading"
        @click="cargarTodo"
      >
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <!-- MENSAJES -->
    <div v-if="pageError" class="cotizaciones-error">
      {{ pageError }}
    </div>

    <div v-if="successMessage" class="cotizaciones-success">
      <i class="ti ti-circle-check"></i>
      {{ successMessage }}
    </div>

    <!-- KPIS -->
    <CotizacionesKpis
      v-if="!loading"
      :total="cotizaciones.length"
      :pendientes="conteo.pendientes"
      :aprobadas="conteo.aprobadas"
      :convertidas="conteo.convertidas"
      :rechazadas="conteo.rechazadas"
    />

    <!-- PANEL PRINCIPAL -->
    <div class="cotizaciones-card">
      <CotizacionesFiltros
        v-model:busqueda="busqueda"
        v-model:estado="filtroEstado"
        :loading="loading"
        @buscar="buscarCotizaciones"
        @limpiar="limpiarFiltros"
        @actualizar="cargarTodo"
      />

      <div v-if="loading" class="cotizaciones-loading">
        <div class="spinner"></div>
        <span>Cargando cotizaciones...</span>
      </div>

      <CotizacionesTabla
        v-else
        :cotizaciones="cotizaciones"
        :saving="saving"
        @ver="verDetalle"
        @aprobar="aprobarCotizacion"
        @rechazar="openRechazarModal"
        @convertir="convertirVenta"
        @anular="anularCotizacion"
      />

      <PaginacionTabla
        :pagination="pagination"
        etiqueta="cotizaciones registradas"
        :disabled="loading"
        @change-page="cambiarPagina"
      />
    </div>
  </section>

  <!-- MODALES FUERA DEL LAYOUT PARA QUE NO LOS TAPE EL SIDEBAR -->
  <Teleport to="body">
    <!-- MODAL NUEVA COTIZACIÓN -->
    <CotizacionFormModal
      v-if="showFormModal"
      :productores="productores"
      :productos="productos"
      :almacenes="almacenes"
      :saving="saving"
      :error="formError"
      @close="closeCreate"
      @save="handleSave"
    />

    <!-- MODAL DETALLE -->
    <CotizacionDetalleModal
      v-if="showDetalleModal"
      :cotizacion="detalleActual"
      @close="closeDetalle"
    />

    <!-- MODAL RECHAZAR -->
    <CotizacionRechazarModal
      v-if="showRechazarModal"
      :cotizacion="cotizacionToRechazar"
      :saving="saving"
      :error="rechazarError"
      @close="closeRechazarModal"
      @confirm="rechazarCotizacion"
    />

    <!-- MODAL CONFIRMAR ACCIONES -->
    <CotizacionConfirmModal
      v-if="showConfirmModal"
      :cotizacion="cotizacionToConfirm"
      :action="confirmAction"
      :saving="saving"
      :error="confirmError"
      @close="closeConfirmModal"
      @confirm="handleConfirmAction"
    />
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import cotizacionService from '@/services/modules/cotizacionService';
import productoService from '@/services/modules/productoService';
import productorService from '@/services/modules/productorService';
import almacenService from '@/services/modules/almacenService';
import { obtenerMensajeError } from '@/services/errorService';

import CotizacionesKpis from '@/components/cotizaciones/CotizacionesKpis.vue';
import CotizacionesFiltros from '@/components/cotizaciones/CotizacionesFiltros.vue';
import CotizacionesTabla from '@/components/cotizaciones/CotizacionesTabla.vue';
import CotizacionFormModal from '@/components/cotizaciones/CotizacionFormModal.vue';
import CotizacionDetalleModal from '@/components/cotizaciones/CotizacionDetalleModal.vue';
import CotizacionRechazarModal from '@/components/cotizaciones/CotizacionRechazarModal.vue';
import CotizacionConfirmModal from '@/components/cotizaciones/CotizacionConfirmModal.vue';

import '@/styles/cotizaciones.css';
import { normalizeList, paginacionDe } from '@/utils/datos';
import PaginacionTabla from '@/components/ui/PaginacionTabla.vue';

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const rechazarError = ref('');
const confirmError = ref('');
const successMessage = ref('');

const cotizaciones = ref([]);
const pagina = ref(1);
const pagination = ref(null);
const productos = ref([]);
const productores = ref([]);
const almacenes = ref([]);

const busqueda = ref('');
const filtroEstado = ref('');

const showFormModal = ref(false);
const showDetalleModal = ref(false);
const showRechazarModal = ref(false);
const showConfirmModal = ref(false);

const detalleActual = ref(null);
const cotizacionToRechazar = ref(null);
const cotizacionToConfirm = ref(null);
const confirmAction = ref('');

const conteo = computed(() => ({
  pendientes: cotizaciones.value.filter((c) => c.estado === 'Pendiente').length,
  aprobadas: cotizaciones.value.filter((c) => c.estado === 'Aprobada').length,
  convertidas: cotizaciones.value.filter((c) => c.estado === 'Convertida').length,
  rechazadas: cotizaciones.value.filter((c) => c.estado === 'Rechazada').length,
}));

onMounted(cargarTodo);

async function cargarTodo() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchCotizaciones(),
      fetchProductos(),
      fetchProductores(),
      fetchAlmacenes(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function buscarCotizaciones() {
  pagina.value = 1;
  loading.value = true;
  pageError.value = '';

  try {
    await fetchCotizaciones();
  } finally {
    loading.value = false;
  }
}

async function fetchCotizaciones() {
  pageError.value = '';

  try {
    const params = {
      page: pagina.value,
      per_page: 20,
    };

    if (busqueda.value) {
      params.busqueda = busqueda.value;
    }

    if (filtroEstado.value) {
      params.estado = filtroEstado.value;
    }

    const { data } = await cotizacionService.listar(params);

    cotizaciones.value = normalizeList(data);
    pagination.value = paginacionDe(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudieron cargar las cotizaciones.'
    );
    cotizaciones.value = [];
  }
}

async function fetchProductos() {
  try {
    const { data } = await productoService.listar({
      todos: true,
      activo: true,
    });

    productos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudieron cargar los productos.'
    );
    productos.value = [];
  }
}

async function fetchProductores() {
  try {
    const { data } = await productorService.listar({
      todos: true,
      activo: true,
    });

    productores.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudieron cargar los productores.'
    );
    productores.value = [];
  }
}

async function fetchAlmacenes() {
  try {
    const { data } = await almacenService.listar({
      todos: true,
      estado: 'Activo',
    });

    almacenes.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudieron cargar los almacenes.'
    );
    almacenes.value = [];
  }
}

async function cambiarPagina(nuevaPagina) {
  pagina.value = nuevaPagina;
  loading.value = true;

  try {
    await fetchCotizaciones();
  } finally {
    loading.value = false;
  }
}

function limpiarFiltros() {
  busqueda.value = '';
  filtroEstado.value = '';
  buscarCotizaciones();
}

function openCreate() {
  pageError.value = '';
  successMessage.value = '';
  showFormModal.value = true;
}

function closeCreate() {
  formError.value = '';
  showFormModal.value = false;
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';
  successMessage.value = '';

  try {
    await cotizacionService.crear(payload);
    await fetchCotizaciones();
    closeCreate();

    mostrarExito('La cotización fue registrada correctamente.');
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(
      err,
      'Error al registrar la cotización.'
    );
  } finally {
    saving.value = false;
  }
}

async function verDetalle(cotizacion) {
  if (!cotizacion?.id) {
    return;
  }

  pageError.value = '';

  try {
    const { data } = await cotizacionService.obtener(cotizacion.id);
    detalleActual.value = data;
    showDetalleModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar el detalle de la cotización.'
    );
  }
}

function closeDetalle() {
  showDetalleModal.value = false;
  detalleActual.value = null;
}

function aprobarCotizacion(cotizacion) {
  openConfirmModal('aprobar', cotizacion);
}

function anularCotizacion(cotizacion) {
  openConfirmModal('anular', cotizacion);
}

function convertirVenta(cotizacion) {
  openConfirmModal('convertir', cotizacion);
}

function openConfirmModal(action, cotizacion) {
  confirmAction.value = action;
  cotizacionToConfirm.value = cotizacion;
  confirmError.value = '';
  pageError.value = '';
  successMessage.value = '';
  showConfirmModal.value = true;
}

function closeConfirmModal() {
  showConfirmModal.value = false;
  confirmAction.value = '';
  cotizacionToConfirm.value = null;
  confirmError.value = '';
}

async function handleConfirmAction() {
  if (!cotizacionToConfirm.value?.id) {
    return;
  }

  saving.value = true;
  confirmError.value = '';
  pageError.value = '';
  successMessage.value = '';

  try {
    const id = cotizacionToConfirm.value.id;
    const nro = cotizacionToConfirm.value.nro_cotizacion || `#${id}`;

    if (confirmAction.value === 'aprobar') {
      await cotizacionService.aprobar(id);
      mostrarExito(`La cotización ${nro} fue aprobada correctamente.`);
    }

    if (confirmAction.value === 'anular') {
      await cotizacionService.anular(id);
      mostrarExito(`La cotización ${nro} fue anulada correctamente.`);
    }

    if (confirmAction.value === 'convertir') {
      await cotizacionService.convertirVenta(id);
      mostrarExito(`La cotización ${nro} fue convertida en venta correctamente.`);
    }

    await fetchCotizaciones();
    closeConfirmModal();
  } catch (err) {
    console.error(err);
    confirmError.value = obtenerMensajeError(
      err,
      'No se pudo completar la acción.'
    );
  } finally {
    saving.value = false;
  }
}

function openRechazarModal(cotizacion) {
  cotizacionToRechazar.value = cotizacion;
  rechazarError.value = '';
  pageError.value = '';
  successMessage.value = '';
  showRechazarModal.value = true;
}

function closeRechazarModal() {
  showRechazarModal.value = false;
  cotizacionToRechazar.value = null;
  rechazarError.value = '';
}

async function rechazarCotizacion(motivo) {
  if (!cotizacionToRechazar.value?.id) {
    return;
  }

  saving.value = true;
  rechazarError.value = '';
  pageError.value = '';
  successMessage.value = '';

  try {
    const nro =
      cotizacionToRechazar.value.nro_cotizacion ||
      `#${cotizacionToRechazar.value.id}`;

    await cotizacionService.rechazar(
      cotizacionToRechazar.value.id,
      motivo
    );

    await fetchCotizaciones();
    closeRechazarModal();

    mostrarExito(`La cotización ${nro} fue rechazada correctamente.`);
  } catch (err) {
    console.error(err);
    rechazarError.value = obtenerMensajeError(
      err,
      'Error al rechazar la cotización.'
    );
  } finally {
    saving.value = false;
  }
}

function mostrarExito(mensaje) {
  successMessage.value = mensaje;

  setTimeout(() => {
    successMessage.value = '';
  }, 4500);
}
</script>