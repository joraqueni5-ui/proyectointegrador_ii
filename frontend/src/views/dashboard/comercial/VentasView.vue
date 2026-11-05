<template>
  <section class="ventas-page fade-in">
    <!-- CONTEXTBAR -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Comercial</span>
        <span>›</span>
        <span class="cur">Ventas</span>
      </div>

      <div class="cbar-right">
        <button
          class="btn btn-p"
          type="button"
          @click="openVentaModal"
        >
          <i class="ti ti-shopping-cart-plus"></i>
          Nueva venta
        </button>
      </div>
    </div>

    <!-- HEADER -->
    <div class="pg-header ventas-header">
      <div>
        <div class="pg-title">Ventas comerciales</div>
        <div class="pg-sub">
          Registra ventas, controla pagos y descuenta inventario automáticamente.
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
    <div v-if="pageError" class="ventas-error">
      {{ pageError }}
    </div>

    <div v-if="successMessage" class="ventas-success">
      <i class="ti ti-circle-check"></i>
      {{ successMessage }}
    </div>

    <!-- KPIS -->
    <VentasKpis
      v-if="!loading"
      :kpis="kpis"
    />

    <!-- PANEL PRINCIPAL -->
    <div class="ventas-card">
      <VentasFiltros
        v-model:busqueda="busqueda"
        v-model:estado="filtroEstado"
        :loading="loading"
        @buscar="buscarVentas"
        @limpiar="limpiarFiltros"
        @actualizar="cargarTodo"
      />

      <div v-if="loading" class="ventas-loading">
        <div class="spinner"></div>
        <span>Cargando ventas...</span>
      </div>

      <VentasTabla
        v-else
        :ventas="ventas"
        :saving="saving"
        @ver="verDetalle"
        @imprimir="imprimirComprobante"
        @anular="openAnularModal"
      />

      <PaginacionTabla
        :pagination="pagination"
        etiqueta="ventas registradas"
        :disabled="loading"
        @change-page="cambiarPagina"
      />
    </div>
  </section>

  <!-- MODALES FUERA DEL LAYOUT PARA QUE NO LOS TAPE EL SIDEBAR -->
  <Teleport to="body">
    <!-- MODAL NUEVA VENTA -->
    <VentaFormModal
      v-if="showVentaModal"
      :productores="productores"
      :productos="productos"
      :almacenes="almacenes"
      :saving="saving"
      :error="formError"
      @close="closeVentaModal"
      @save="handleSaveVenta"
    />

    <!-- MODAL DETALLE -->
    <VentaDetalleModal
      v-if="showDetalleModal"
      :venta="ventaDetalle"
      @close="closeDetalle"
      @imprimir="imprimirComprobante"
    />

    <!-- MODAL ANULAR -->
    <VentaAnularModal
      v-if="showAnularModal"
      :venta="ventaToAnular"
      :saving="saving"
      :error="anularError"
      @close="closeAnularModal"
      @confirm="handleAnular"
    />

    <!-- MODAL COMPROBANTE -->
    <VentaComprobanteModal
      v-if="showComprobanteModal"
      :venta="ventaComprobante"
      @close="closeComprobante"
    />
  </Teleport>
</template>

<script setup>
import { onMounted, ref } from 'vue';

import ventaService from '@/services/modules/ventaService';
import productoService from '@/services/modules/productoService';
import productorService from '@/services/modules/productorService';
import almacenService from '@/services/modules/almacenService';
import { obtenerMensajeError } from '@/services/errorService';

import VentasKpis from '@/components/ventas/VentasKpis.vue';
import VentasFiltros from '@/components/ventas/VentasFiltros.vue';
import VentasTabla from '@/components/ventas/VentasTabla.vue';
import VentaFormModal from '@/components/ventas/VentaFormModal.vue';
import VentaDetalleModal from '@/components/ventas/VentaDetalleModal.vue';
import VentaAnularModal from '@/components/ventas/VentaAnularModal.vue';
import VentaComprobanteModal from '@/components/ventas/VentaComprobanteModal.vue';

import '@/styles/ventas.css';
import { normalizeList, paginacionDe } from '@/utils/datos';
import PaginacionTabla from '@/components/ui/PaginacionTabla.vue';

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const anularError = ref('');
const successMessage = ref('');

const ventas = ref([]);
const pagina = ref(1);
const pagination = ref(null);
const productos = ref([]);
const productores = ref([]);
const almacenes = ref([]);

const kpis = ref({
  ventas_mes: 0,
  facturas_emitidas: 0,
  saldos_pendientes: 0,
  productores_pend: 0,
  facturas_vencidas: 0,
});

const busqueda = ref('');
const filtroEstado = ref('');

const showVentaModal = ref(false);
const showDetalleModal = ref(false);
const showAnularModal = ref(false);

const showComprobanteModal = ref(false);
const ventaComprobante = ref(null);

const ventaDetalle = ref(null);
const ventaToAnular = ref(null);

onMounted(cargarTodo);

async function cargarTodo() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchDashboard(),
      fetchVentas(),
      fetchProductos(),
      fetchProductores(),
      fetchAlmacenes(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function buscarVentas() {
  pagina.value = 1;
  loading.value = true;
  pageError.value = '';

  try {
    await fetchVentas();
  } finally {
    loading.value = false;
  }
}

async function fetchDashboard() {
  try {
    const { data } = await ventaService.dashboard();

    kpis.value = {
      ventas_mes: data?.ventas_mes ?? 0,
      facturas_emitidas: data?.facturas_emitidas ?? 0,
      saldos_pendientes: data?.saldos_pendientes ?? 0,
      productores_pend: data?.productores_pend ?? 0,
      facturas_vencidas: data?.facturas_vencidas ?? 0,
    };
  } catch (err) {
    console.error(err);
  }
}

async function fetchVentas() {
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

    const { data } = await ventaService.listar(params);

    ventas.value = normalizeList(data);
    pagination.value = paginacionDe(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudieron cargar las ventas.'
    );
    ventas.value = [];
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
    await fetchVentas();
  } finally {
    loading.value = false;
  }
}

function limpiarFiltros() {
  busqueda.value = '';
  filtroEstado.value = '';
  buscarVentas();
}

function openVentaModal() {
  pageError.value = '';
  successMessage.value = '';
  showVentaModal.value = true;
}

function closeVentaModal() {
  formError.value = '';
  showVentaModal.value = false;
}

async function handleSaveVenta(payload) {
  saving.value = true;
  formError.value = '';
  successMessage.value = '';

  try {
    await ventaService.crear(payload);

    await Promise.all([
      fetchDashboard(),
      fetchVentas(),
      fetchProductos(),
    ]);

    closeVentaModal();

    mostrarExito('La venta fue registrada correctamente. El stock fue descontado del inventario.');
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(
      err,
      'Error al registrar la venta.'
    );
  } finally {
    saving.value = false;
  }
}

async function verDetalle(venta) {
  if (!venta?.id) {
    return;
  }

  pageError.value = '';

  try {
    const { data } = await ventaService.obtener(venta.id);
    ventaDetalle.value = data;
    showDetalleModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar el detalle de la venta.'
    );
  }
}

function closeDetalle() {
  showDetalleModal.value = false;
  ventaDetalle.value = null;
}

function openAnularModal(venta) {
  ventaToAnular.value = venta;
  anularError.value = '';
  pageError.value = '';
  successMessage.value = '';
  showAnularModal.value = true;
}

function closeAnularModal() {
  showAnularModal.value = false;
  ventaToAnular.value = null;
  anularError.value = '';
}

async function handleAnular() {
  if (!ventaToAnular.value?.id) {
    return;
  }

  saving.value = true;
  anularError.value = '';
  pageError.value = '';
  successMessage.value = '';

  try {
    const nro =
      ventaToAnular.value.nro_factura ||
      ventaToAnular.value.id;

    await ventaService.cambiarEstado(
      ventaToAnular.value.id,
      'Anulada'
    );

    await Promise.all([
      fetchDashboard(),
      fetchVentas(),
      fetchProductos(),
    ]);

    closeAnularModal();

    mostrarExito(`La venta #${nro} fue anulada correctamente.`);
  } catch (err) {
    console.error(err);
    anularError.value = obtenerMensajeError(
      err,
      'Error al anular la venta.'
    );
  } finally {
    saving.value = false;
  }
}

/* ==============================
   COMPROBANTE
============================== */

async function imprimirComprobante(venta) {
  if (!venta?.id) {
    return;
  }

  pageError.value = '';

  try {
    const { data } = await ventaService.obtener(venta.id);

    ventaComprobante.value = data;
    showComprobanteModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar el comprobante de venta.'
    );
  }
}

function closeComprobante() {
  showComprobanteModal.value = false;
  ventaComprobante.value = null;
}

function mostrarExito(mensaje) {
  successMessage.value = mensaje;

  setTimeout(() => {
    successMessage.value = '';
  }, 4500);
}
</script>