<template>
  <section class="dashboard-comercial fade-in">
    <!-- HEADER -->
    <div class="dc-hero">
      <div>
        <div class="dc-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <strong>Dashboard Comercial</strong>
        </div>

        <h1>Panel comercial</h1>
        <p>
          Resumen dinámico de ventas, cotizaciones, inventario y actividad comercial.
        </p>
      </div>

      <button class="dc-refresh" type="button" :disabled="loading" @click="cargarDashboard">
        <i class="ti ti-refresh" :class="{ spin: loading }"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <!-- ERROR -->
    <div v-if="error" class="dc-error">
      {{ error }}
    </div>

    <!-- KPIS -->
    <div class="dc-kpi-grid">
      <div class="dc-kpi dc-kpi-main">
        <div class="dc-kpi-icon">
          <i class="ti ti-cash"></i>
        </div>
        <div>
          <span>Ventas del mes</span>
          <strong>{{ formatoBs(kpis.ventas_mes) }}</strong>
          <small>{{ kpis.facturas_emitidas }} ventas registradas</small>
        </div>
      </div>

      <div class="dc-kpi">
        <div class="dc-kpi-icon green">
          <i class="ti ti-file-invoice"></i>
        </div>
        <div>
          <span>Cotizaciones</span>
          <strong>{{ kpis.cotizaciones }}</strong>
          <small>registradas / consultadas</small>
        </div>
      </div>

      <div class="dc-kpi">
        <div class="dc-kpi-icon amber">
          <i class="ti ti-clock-dollar"></i>
        </div>
        <div>
          <span>Saldos pendientes</span>
          <strong>{{ formatoBs(kpis.saldos_pendientes) }}</strong>
          <small>{{ kpis.productores_pend }} productores pendientes</small>
        </div>
      </div>

      <div class="dc-kpi">
        <div class="dc-kpi-icon blue">
          <i class="ti ti-package"></i>
        </div>
        <div>
          <span>Stock semillas</span>
          <strong>{{ Number(kpis.stock_semillas_kg || 0).toFixed(1) }} kg</strong>
          <small>{{ kpis.total_bioinsumos }} bioinsumos registrados</small>
        </div>
      </div>
    </div>

    <!-- ACCESOS -->
    <div class="dc-shortcuts">
      <RouterLink to="/dashboard/ventas" class="dc-shortcut primary">
        <i class="ti ti-shopping-cart-plus"></i>
        <div>
          <strong>Nueva venta</strong>
          <span>Registrar operación comercial</span>
        </div>
      </RouterLink>

      <RouterLink to="/dashboard/cotizaciones" class="dc-shortcut">
        <i class="ti ti-file-plus"></i>
        <div>
          <strong>Cotizaciones</strong>
          <span>Crear o revisar cotizaciones</span>
        </div>
      </RouterLink>

      <RouterLink to="/dashboard/inventario" class="dc-shortcut">
        <i class="ti ti-box-seam"></i>
        <div>
          <strong>Inventario</strong>
          <span>Consultar productos disponibles</span>
        </div>
      </RouterLink>

      <RouterLink to="/dashboard/reportes" class="dc-shortcut">
        <i class="ti ti-chart-bar"></i>
        <div>
          <strong>Reportes</strong>
          <span>Exportar información comercial</span>
        </div>
      </RouterLink>
    </div>

    <!-- CONTENIDO -->
    <div class="dc-content-grid">
      <!-- GRAFICO -->
      <div class="dc-card dc-chart-card">
        <div class="dc-card-title">
          <div>
            <h2>Tendencia de ventas</h2>
            <p>Ventas registradas durante los últimos meses.</p>
          </div>
          <i class="ti ti-chart-line"></i>
        </div>

        <div v-if="loading" class="dc-loader">
          <div class="dc-spinner"></div>
          <span>Cargando estadísticas...</span>
        </div>

        <div v-else class="dc-chart-box">
          <canvas ref="chartVentas"></canvas>
        </div>
      </div>

      <!-- RESUMEN -->
      <div class="dc-card dc-summary-card">
        <div class="dc-card-title">
          <div>
            <h2>Estado comercial</h2>
            <p>Indicadores rápidos del área comercial.</p>
          </div>
          <i class="ti ti-activity"></i>
        </div>

        <div class="dc-status-list">
          <div class="dc-status-item">
            <span>Facturas vencidas</span>
            <strong>{{ kpis.facturas_vencidas }}</strong>
          </div>

          <div class="dc-status-item">
            <span>Productos con stock</span>
            <strong>{{ productosStock }}</strong>
          </div>

          <div class="dc-status-item">
            <span>Ventas recientes</span>
            <strong>{{ ventasRecientes.length }}</strong>
          </div>

          <div class="dc-status-item">
            <span>Cotizaciones recientes</span>
            <strong>{{ cotizacionesRecientes.length }}</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- LISTAS -->
    <div class="dc-list-grid">
      <div class="dc-card">
        <div class="dc-card-title">
          <div>
            <h2>Últimas ventas</h2>
            <p>Movimientos comerciales recientes.</p>
          </div>

          <RouterLink to="/dashboard/ventas" class="dc-link">
            Ver todas →
          </RouterLink>
        </div>

        <div v-if="!ventasRecientes.length" class="dc-empty">
          <i class="ti ti-receipt-off"></i>
          <span>No hay ventas recientes.</span>
        </div>

        <div v-else class="dc-list">
          <div v-for="venta in ventasRecientes" :key="venta.id" class="dc-list-item">
            <div class="dc-avatar">
              {{ inicial(venta.productor?.nombre_completo || venta.nro_factura) }}
            </div>

            <div>
              <strong>{{ venta.nro_factura || 'Venta' }}</strong>
              <span>{{ venta.productor?.nombre_completo || 'Sin productor' }}</span>
            </div>

            <b>{{ formatoBs(venta.monto_total) }}</b>
          </div>
        </div>
      </div>

      <div class="dc-card">
        <div class="dc-card-title">
          <div>
            <h2>Últimas cotizaciones</h2>
            <p>Solicitudes comerciales registradas.</p>
          </div>

          <RouterLink to="/dashboard/cotizaciones" class="dc-link">
            Ver todas →
          </RouterLink>
        </div>

        <div v-if="!cotizacionesRecientes.length" class="dc-empty">
          <i class="ti ti-file-off"></i>
          <span>No hay cotizaciones recientes.</span>
        </div>

        <div v-else class="dc-list">
          <div
            v-for="cotizacion in cotizacionesRecientes"
            :key="cotizacion.id"
            class="dc-list-item"
          >
            <div class="dc-avatar blue">
              {{ inicial(cotizacion.productor?.nombre_completo || cotizacion.codigo) }}
            </div>

            <div>
              <strong>{{ cotizacion.codigo || 'Cotización' }}</strong>
              <span>{{ cotizacion.productor?.nombre_completo || 'Sin productor' }}</span>
            </div>

            <b>{{ formatoBs(cotizacion.total) }}</b>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Chart from 'chart.js/auto';

import ventaService from '@/services/modules/ventaService';
import cotizacionService from '@/services/modules/cotizacionService';
import inventarioService from '@/services/modules/inventarioService';

const loading = ref(false);
const error = ref('');

const chartVentas = ref(null);
let chartVentasInstance = null;

const kpis = ref({
  ventas_mes: 0,
  facturas_emitidas: 0,
  saldos_pendientes: 0,
  productores_pend: 0,
  facturas_vencidas: 0,
  cotizaciones: 0,
  stock_semillas_kg: 0,
  total_bioinsumos: 0,
});

const tendenciaVentas = ref([]);
const ventasRecientes = ref([]);
const cotizacionesRecientes = ref([]);
const semillas = ref([]);
const bioinsumos = ref([]);

const productosStock = computed(() => {
  return semillas.value.length + bioinsumos.value.length;
});

onMounted(cargarDashboard);

onBeforeUnmount(() => {
  chartVentasInstance?.destroy();
  chartVentasInstance = null;
});

async function cargarDashboard() {
  loading.value = true;
  error.value = '';

  try {
    const [
      ventasDashboardRes,
      ventasTendenciaRes,
      ventasListadoRes,
      cotizacionesRes,
      inventarioRes,
    ] = await Promise.allSettled([
      ventaService.dashboard(),
      ventaService.tendencia({ meses: 6 }),
      ventaService.listar({ per_page: 5 }),
      cotizacionService.listar({ per_page: 5 }),
      inventarioService.dashboard(),
    ]);

    if (ventasDashboardRes.status === 'fulfilled') {
      const data = ventasDashboardRes.value.data || {};

      kpis.value.ventas_mes = data.ventas_mes ?? 0;
      kpis.value.facturas_emitidas = data.facturas_emitidas ?? 0;
      kpis.value.saldos_pendientes = data.saldos_pendientes ?? 0;
      kpis.value.productores_pend = data.productores_pend ?? 0;
      kpis.value.facturas_vencidas = data.facturas_vencidas ?? 0;
    }

    if (ventasTendenciaRes.status === 'fulfilled') {
      tendenciaVentas.value = Array.isArray(ventasTendenciaRes.value.data)
        ? ventasTendenciaRes.value.data
        : [];
    } else {
      tendenciaVentas.value = [];
    }

    if (ventasListadoRes.status === 'fulfilled') {
      const data = ventasListadoRes.value.data;
      ventasRecientes.value = Array.isArray(data)
        ? data
        : data?.data ?? [];
    } else {
      ventasRecientes.value = [];
    }

    if (cotizacionesRes.status === 'fulfilled') {
      const data = cotizacionesRes.value.data;

      cotizacionesRecientes.value = Array.isArray(data)
        ? data
        : data?.data ?? [];

      kpis.value.cotizaciones = data?.total ?? cotizacionesRecientes.value.length;
    } else {
      cotizacionesRecientes.value = [];
      kpis.value.cotizaciones = 0;
    }

    if (inventarioRes.status === 'fulfilled') {
      const data = inventarioRes.value.data || {};

      kpis.value.stock_semillas_kg = data?.kpis?.stock_semillas_kg ?? 0;
      kpis.value.total_bioinsumos = data?.kpis?.total_bioinsumos ?? 0;

      semillas.value = data?.semillas || [];
      bioinsumos.value = data?.bioinsumos || [];
    } else {
      semillas.value = [];
      bioinsumos.value = [];
    }

    await nextTick();
    dibujarGraficoVentas();
  } catch (err) {
    console.error(err);
    error.value = 'No se pudo cargar el dashboard comercial.';
  } finally {
    loading.value = false;
  }
}

function dibujarGraficoVentas() {
  if (!chartVentas.value) {
    return;
  }

  chartVentasInstance?.destroy();

  const labels = tendenciaVentas.value.length
    ? tendenciaVentas.value.map((item) => item.mes)
    : ['Sin datos'];

  const valores = tendenciaVentas.value.length
    ? tendenciaVentas.value.map((item) => Number(item.total || 0))
    : [0];

  chartVentasInstance = new Chart(chartVentas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Ventas Bs',
          data: valores,
          fill: true,
          tension: 0.35,
          borderWidth: 3,
          pointRadius: 5,
          pointHoverRadius: 7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      resizeDelay: 100,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label(context) {
              return formatoBs(context.raw);
            },
          },
        },
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            callback(value) {
              return `Bs ${value}`;
            },
          },
        },
      },
    },
  });
}

function formatoBs(value) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
  }).format(Number(value || 0));
}

function inicial(value) {
  return String(value || 'U').charAt(0).toUpperCase();
}
</script>

<style scoped>
.dashboard-comercial {
  padding: 30px;
  min-height: 100%;
  background:
    radial-gradient(circle at top left, rgba(46, 125, 90, 0.12), transparent 34%),
    linear-gradient(180deg, #f8fafc 0%, #eef6f1 100%);
}

.dc-hero {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: center;
  background: linear-gradient(135deg, #0f5132, #198754);
  color: #ffffff;
  border-radius: 24px;
  padding: 30px;
  margin-bottom: 22px;
  box-shadow: 0 18px 40px rgba(15, 81, 50, 0.22);
  animation: slideDown 0.45s ease;
}

.dc-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  opacity: 0.9;
  font-size: 14px;
  margin-bottom: 10px;
}

.dc-hero h1 {
  margin: 0;
  font-size: 34px;
  font-weight: 800;
}

.dc-hero p {
  margin: 8px 0 0;
  opacity: 0.9;
}

.dc-refresh {
  border: 0;
  border-radius: 14px;
  padding: 13px 18px;
  background: rgba(255, 255, 255, 0.18);
  color: #ffffff;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.dc-refresh:disabled {
  opacity: 0.75;
  cursor: not-allowed;
}

.spin {
  animation: spin 0.8s linear infinite;
}

.dc-error {
  background: #fee2e2;
  color: #b91c1c;
  border-radius: 16px;
  padding: 14px 18px;
  font-weight: 800;
  margin-bottom: 18px;
}

.dc-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 20px;
}

.dc-kpi {
  display: flex;
  gap: 16px;
  align-items: center;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid rgba(226, 232, 240, 0.9);
  border-radius: 22px;
  padding: 22px;
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08);
  animation: popIn 0.45s ease both;
}

.dc-kpi:nth-child(2) {
  animation-delay: 0.08s;
}

.dc-kpi:nth-child(3) {
  animation-delay: 0.16s;
}

.dc-kpi:nth-child(4) {
  animation-delay: 0.24s;
}

.dc-kpi-main {
  background: linear-gradient(135deg, #0f7a4f, #22c55e);
  color: #ffffff;
}

.dc-kpi-icon {
  width: 54px;
  height: 54px;
  border-radius: 18px;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
  font-size: 26px;
  flex-shrink: 0;
}

.dc-kpi:not(.dc-kpi-main) .dc-kpi-icon {
  background: #dcfce7;
  color: #15803d;
}

.dc-kpi-icon.green {
  background: #dcfce7 !important;
  color: #15803d !important;
}

.dc-kpi-icon.amber {
  background: #fef3c7 !important;
  color: #b45309 !important;
}

.dc-kpi-icon.blue {
  background: #dbeafe !important;
  color: #1d4ed8 !important;
}

.dc-kpi span {
  display: block;
  color: inherit;
  opacity: 0.72;
  font-size: 13px;
  font-weight: 800;
}

.dc-kpi strong {
  display: block;
  margin-top: 3px;
  font-size: 29px;
  line-height: 1;
  font-weight: 800;
}

.dc-kpi small {
  display: block;
  margin-top: 8px;
  color: inherit;
  opacity: 0.72;
  font-weight: 700;
}

.dc-shortcuts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.dc-shortcut {
  display: flex;
  gap: 14px;
  align-items: center;
  text-decoration: none;
  color: #0f172a;
  background: #ffffff;
  border-radius: 20px;
  padding: 18px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dc-shortcut:hover {
  transform: translateY(-4px);
  box-shadow: 0 18px 34px rgba(15, 23, 42, 0.12);
}

.dc-shortcut.primary {
  background: #0f5132;
  color: #ffffff;
}

.dc-shortcut i {
  font-size: 28px;
}

.dc-shortcut strong {
  display: block;
  font-size: 16px;
}

.dc-shortcut span {
  display: block;
  margin-top: 3px;
  font-size: 13px;
  opacity: 0.72;
}

.dc-content-grid {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(320px, 0.9fr);
  gap: 20px;
  margin-bottom: 20px;
}

.dc-list-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

.dc-card {
  background: #ffffff;
  border-radius: 22px;
  padding: 22px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
}

.dc-card-title {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: flex-start;
  margin-bottom: 18px;
}

.dc-card-title h2 {
  margin: 0;
  color: #0f172a;
  font-size: 21px;
}

.dc-card-title p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 14px;
}

.dc-card-title > i {
  font-size: 28px;
  color: #198754;
}

.dc-link {
  color: #198754;
  font-weight: 800;
  text-decoration: none;
}

.dc-chart-box {
  height: 310px;
}

.dc-loader,
.dc-empty {
  height: 210px;
  display: grid;
  place-items: center;
  text-align: center;
  color: #64748b;
  font-weight: 800;
}

.dc-spinner {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  border: 4px solid #dbeafe;
  border-top-color: #198754;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 10px;
}

.dc-status-list {
  display: grid;
  gap: 13px;
}

.dc-status-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
  border-radius: 16px;
  padding: 15px;
  border: 1px solid #e2e8f0;
}

.dc-status-item span {
  color: #64748b;
  font-weight: 800;
}

.dc-status-item strong {
  font-size: 22px;
  color: #0f172a;
}

.dc-list {
  display: grid;
  gap: 12px;
}

.dc-list-item {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  background: #f8fafc;
  border-radius: 16px;
  padding: 12px;
  border: 1px solid #e2e8f0;
}

.dc-list-item strong {
  display: block;
  color: #0f172a;
}

.dc-list-item span {
  display: block;
  margin-top: 2px;
  color: #64748b;
  font-size: 13px;
}

.dc-list-item b {
  color: #198754;
  white-space: nowrap;
}

.dc-avatar {
  width: 46px;
  height: 46px;
  border-radius: 15px;
  display: grid;
  place-items: center;
  background: #dcfce7;
  color: #15803d;
  font-weight: 800;
}

.dc-avatar.blue {
  background: #dbeafe;
  color: #1d4ed8;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-12px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes popIn {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1200px) {
  .dc-kpi-grid,
  .dc-shortcuts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .dc-content-grid,
  .dc-list-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 720px) {
  .dashboard-comercial {
    padding: 18px;
  }

  .dc-hero {
    flex-direction: column;
    align-items: flex-start;
  }

  .dc-kpi-grid,
  .dc-shortcuts {
    grid-template-columns: 1fr;
  }
}
</style>