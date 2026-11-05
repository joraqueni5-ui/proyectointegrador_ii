<template>
  <section class="reportes-page fade-in">
    <!-- CONTEXTBAR -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Comercial</span>
        <span>›</span>
        <span class="cur">Reportes</span>
      </div>

      <div class="cbar-right">
        <button
          class="btn btn-s"
          type="button"
          @click="limpiarFiltros"
        >
          <i class="ti ti-eraser"></i>
          Limpiar
        </button>

        <button
          class="btn btn-p"
          type="button"
          :disabled="loading"
          @click="generarReporte"
        >
          <i class="ti ti-report-search"></i>
          {{ loading ? 'Generando...' : 'Generar reporte' }}
        </button>
      </div>
    </div>

    <!-- HEADER -->
    <div class="pg-header reportes-header">
      <div>
        <div class="pg-title">Reportes comerciales</div>
        <div class="pg-sub">
          Consulta, filtra y exporta reportes de inventario, movimientos, ventas y cotizaciones.
        </div>
      </div>
    </div>

    <!-- MENSAJES -->
    <div v-if="error" class="reportes-error">
      <i class="ti ti-alert-circle"></i>
      {{ error }}
    </div>

    <div v-if="successMessage" class="reportes-success">
      <i class="ti ti-circle-check"></i>
      {{ successMessage }}
    </div>

    <!-- SELECTOR DE TIPO -->
    <ReportesSelector
      v-model="filtros.tipo"
      @change="onTipoChange"
    />

    <!-- PANEL DE FILTROS -->
    <div class="reportes-config-layout">
      <ReportesFiltros
        v-model:filtros="filtros"
        :productos="productos"
        :almacenes="almacenes"
        :productores="productores"
        :responsables="responsablesAlmacen"
        :estados-disponibles="estadosDisponibles"
        :usa-fechas="usaFechas"
        :usa-producto="usaProducto"
        :usa-categoria="usaCategoria"
        :usa-almacen="usaAlmacen"
        :usa-responsable="usaResponsable"
        :usa-productor="usaProductor"
        :usa-estado="usaEstado"
        :usa-metodo-pago="usaMetodoPago"
        :usa-tipo-movimiento="usaTipoMovimiento"
        :usa-motivo="usaMotivo"
        :usa-montos="usaMontos"
      />

      <ReportesAcciones
        :loading="loading"
        :exporting="exporting"
        :has-reporte="!!reporte"
        @limpiar="limpiarFiltros"
        @generar="generarReporte"
        @excel="exportarExcel"
        @pdf="exportarPdf"
      />
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="reportes-loading">
      <div class="spinner"></div>
      <span>Generando reporte...</span>
    </div>

    <!-- RESULTADO -->
    <template v-else-if="reporte">
      <ReportesResumen
        :reporte="reporte"
        :total-filas="reporte.filas?.length || 0"
      />

      <ReportesTabla
        :reporte="reporte"
      />
    </template>

    <!-- EMPTY -->
    <ReportesEmpty v-else />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import reporteService from '@/services/modules/reporteService';
import productoService from '@/services/modules/productoService';
import almacenService from '@/services/modules/almacenService';
import productorService from '@/services/modules/productorService';
import { obtenerMensajeError } from '@/services/errorService';

import ReportesSelector from '@/components/reportes/ReportesSelector.vue';
import ReportesFiltros from '@/components/reportes/ReportesFiltros.vue';
import ReportesAcciones from '@/components/reportes/ReportesAcciones.vue';
import ReportesResumen from '@/components/reportes/ReportesResumen.vue';
import ReportesTabla from '@/components/reportes/ReportesTabla.vue';
import ReportesEmpty from '@/components/reportes/ReportesEmpty.vue';

import '@/styles/reportes.css';
import { normalizeList } from '@/utils/datos';
import { descargarBlob } from '@/services/api';

const loading = ref(false);
const exporting = ref(false);

const error = ref('');
const successMessage = ref('');

const reporte = ref(null);

const productos = ref([]);
const almacenes = ref([]);
const productores = ref([]);

const filtros = ref(defaultFiltros());

onMounted(async () => {
  await Promise.all([
    cargarProductos(),
    cargarAlmacenes(),
    cargarProductores(),
  ]);
});

function defaultFiltros() {
  return {
    tipo: 'inventario',
    fecha_inicio: '',
    fecha_fin: '',
    id_producto: '',
    id_almacen: '',
    id_productor: '',
    id_usuario: '',
    id_responsable: '',
    id_aprobador: '',
    categoria: '',
    estado: '',
    metodo_pago: '',
    tipo_movimiento: '',
    motivo: '',
    monto_min: '',
    monto_max: '',
  };
}

/* ==============================
   CAMPOS DINÁMICOS
============================== */

const usaFechas = computed(() =>
  ['movimientos', 'ventas', 'cotizaciones'].includes(filtros.value.tipo)
);

const usaProducto = computed(() =>
  ['inventario', 'movimientos', 'alertas_stock'].includes(filtros.value.tipo)
);

const usaCategoria = computed(() =>
  ['inventario', 'alertas_stock'].includes(filtros.value.tipo)
);

const usaAlmacen = computed(() =>
  ['inventario', 'movimientos', 'alertas_stock'].includes(filtros.value.tipo)
);

const usaResponsable = computed(() =>
  ['inventario', 'alertas_stock'].includes(filtros.value.tipo)
);

const usaProductor = computed(() =>
  ['ventas', 'cotizaciones'].includes(filtros.value.tipo)
);

const usaEstado = computed(() =>
  ['ventas', 'cotizaciones'].includes(filtros.value.tipo)
);

const usaMetodoPago = computed(() =>
  ['ventas', 'cotizaciones'].includes(filtros.value.tipo)
);

const usaTipoMovimiento = computed(() =>
  filtros.value.tipo === 'movimientos'
);

const usaMotivo = computed(() =>
  filtros.value.tipo === 'movimientos'
);

const usaMontos = computed(() =>
  filtros.value.tipo === 'ventas'
);

const estadosDisponibles = computed(() => {
  if (filtros.value.tipo === 'ventas') {
    return ['Pagada', 'Pendiente', 'Parcial', 'Anulada'];
  }

  if (filtros.value.tipo === 'cotizaciones') {
    return ['Pendiente', 'Aprobada', 'Rechazada', 'Convertida', 'Anulada'];
  }

  return [];
});

const responsablesAlmacen = computed(() => {
  const mapa = new Map();

  almacenes.value.forEach((almacen) => {
    const responsable = almacen.responsable;

    if (!responsable?.id) {
      return;
    }

    mapa.set(responsable.id, {
      id: responsable.id,
      nombre: nombreUsuario(responsable),
    });
  });

  return Array.from(mapa.values());
});

/* ==============================
   CARGA DE CATÁLOGOS
============================== */

async function cargarProductos() {
  try {
    const { data } = await productoService.listar({
      todos: true,
    });

    productos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

async function cargarAlmacenes() {
  try {
    const { data } = await almacenService.listar({
      todos: true,
    });

    almacenes.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

async function cargarProductores() {
  try {
    const { data } = await productorService.listar({
      todos: true,
    });

    productores.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

/* ==============================
   FILTROS
============================== */

function onTipoChange(tipo) {
  filtros.value = {
    ...defaultFiltros(),
    tipo,
  };

  reporte.value = null;
  error.value = '';
  successMessage.value = '';
}

function limpiarFiltros() {
  filtros.value = defaultFiltros();
  reporte.value = null;
  error.value = '';
  successMessage.value = '';
}

function filtrosLimpios() {
  const params = {};

  Object.entries(filtros.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params[key] = value;
    }
  });

  return params;
}

/* ==============================
   GENERAR REPORTE
============================== */

async function generarReporte() {
  loading.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteService.preview(filtrosLimpios());

    reporte.value = data;

    mostrarExito('Reporte generado correctamente.');
  } catch (err) {
    console.error(err);
    reporte.value = null;

    error.value = obtenerMensajeError(
      err,
      'No se pudo generar el reporte.'
    );
  } finally {
    loading.value = false;
  }
}

/* ==============================
   EXPORTACIONES
============================== */

async function exportarExcel() {
  exporting.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteService.excel(filtrosLimpios());

    descargarBlob(
      data,
      `reporte_${filtros.value.tipo}.xlsx`,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    mostrarExito('Reporte Excel exportado correctamente.');
  } catch (err) {
    console.error(err);

    error.value = obtenerMensajeError(
      err,
      'No se pudo exportar el Excel.'
    );
  } finally {
    exporting.value = false;
  }
}

async function exportarPdf() {
  exporting.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteService.pdf(filtrosLimpios());

    descargarBlob(
      data,
      `reporte_${filtros.value.tipo}.pdf`,
      'application/pdf'
    );

    mostrarExito('Reporte PDF exportado correctamente.');
  } catch (err) {
    console.error(err);

    error.value = obtenerMensajeError(
      err,
      'No se pudo exportar el PDF.'
    );
  } finally {
    exporting.value = false;
  }
}

/* ==============================
   UTILIDADES
============================== */

function nombreUsuario(usuario) {
  if (!usuario) {
    return '—';
  }

  return [
    usuario.name,
    usuario.paterno,
    usuario.materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function mostrarExito(mensaje) {
  successMessage.value = mensaje;

  setTimeout(() => {
    successMessage.value = '';
  }, 3500);
}
</script>