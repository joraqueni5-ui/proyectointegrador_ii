<template>
  <DashboardComercial v-if="esComercial" />

  <div v-else class="dashboard-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span class="cur">Panel de Control</span>
      </div>

      <div class="cbar-right">
        <span class="dashboard-date">
          {{ fechaActual }}
        </span>

        <button class="notif-btn" type="button" @click="router.push('/dashboard/alertas')">
          <i class="ti ti-bell" style="font-size:16px"></i>
          <span v-if="alertasActivas > 0" class="notif-dot"></span>
        </button>

        <div class="av dashboard-user-avatar">
          {{ inicialUsuario }}
        </div>
      </div>
    </div>

    <!-- Header -->
    <div class="pg-header dashboard-header">
      <div>
        <div class="pg-title">Panel de Control</div>
        <div class="pg-sub">Campaña agrícola 2026 — Depto. La Paz, Bolivia</div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loadingPage" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loadingPage ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="dashboard-error">
      {{ error }}
    </div>

    <!-- Loading inicial -->
    <div v-if="loadingPage && !kpis" class="loading-state">
      <div class="spinner"></div>
      <span>Cargando panel de control...</span>
    </div>

    <template v-else>
      <!-- KPIs fila 1 -->
      <div v-if="kpis" class="dashboard-kpi-grid mb20">
        <div class="sc">
          <div class="slabel">Productores activos</div>
          <div class="sval">{{ kpis.productores || 0 }}</div>
          <div class="ssub">
            <i class="ti ti-user-check up"></i>
            registrados
          </div>
        </div>

        <div class="sc blue">
          <div class="slabel">Parcelas activas</div>
          <div class="sval">{{ kpis.parcelas || 0 }}</div>
          <div class="ssub">
            <i class="ti ti-map-2"></i>
            bajo seguimiento
          </div>
        </div>

        <div class="sc amber">
          <div class="slabel">Inspecciones</div>
          <div class="sval">{{ kpis.inspecciones || 0 }}</div>
          <div class="ssub">
            <i class="ti ti-clipboard-check"></i>
            realizadas
          </div>
        </div>

        <div class="sc" :class="{ red: Number(kpis.alertas_activas || 0) > 0 }">
          <div class="slabel">Alertas activas</div>
          <div class="sval" :class="{ 'text-danger': Number(kpis.alertas_activas || 0) > 0 }">
            {{ kpis.alertas_activas || 0 }}
          </div>
          <div class="ssub">
            <i
              class="ti ti-bell"
              :class="Number(kpis.alertas_activas || 0) > 0 ? 'down' : 'up'"
            ></i>
            {{ Number(kpis.alertas_activas || 0) > 0 ? 'requieren atención' : 'sin alertas' }}
          </div>
        </div>
      </div>

      <!-- KPIs fila 2 -->
      <div v-if="kpis" class="dashboard-kpi-grid dashboard-kpi-grid-secondary mb20">
        <div class="sc purple">
          <div class="slabel">Diagnósticos CNN</div>
          <div class="sval">{{ kpis.diagnosticos || 0 }}</div>
          <div class="ssub">
            <i class="ti ti-microscope"></i>
            análisis realizados
          </div>
        </div>

        <div class="sc">
          <div class="slabel">Producción total</div>
          <div class="sval">
            {{ Number(kpis.cosechas_total_ton || 0).toFixed(1) }}
            <span class="dashboard-unit">t</span>
          </div>
          <div class="ssub">
            <i class="ti ti-plant-2 up"></i>
            toneladas cosechadas
          </div>
        </div>

        <div class="sc blue">
          <div class="slabel">Comunidades</div>
          <div class="sval">{{ kpis.comunidades || 0 }}</div>
          <div class="ssub">
            <i class="ti ti-building-community"></i>
            registradas
          </div>
        </div>
      </div>

      <!-- Gráficos -->
      <div class="dashboard-charts mb20">
        <div class="card chart-card">
          <div class="card-title">
            <span>
              <i class="ti ti-chart-bar"></i>
              Producción mensual
            </span>
            <span class="card-subtitle">Toneladas cosechadas</span>
          </div>

          <div v-if="loadingCharts" class="chart-loader">
            <div class="spinner"></div>
          </div>

          <div v-else class="dashboard-chart-body">
            <canvas ref="chartCosechas"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="card-title">
            <span>
              <i class="ti ti-chart-donut"></i>
              Enfermedades detectadas
            </span>
            <span class="card-subtitle">Según diagnósticos IA</span>
          </div>

          <div v-if="loadingCharts" class="chart-loader">
            <div class="spinner"></div>
          </div>

          <div v-else-if="!enfermedadesData.length" class="empty-state">
            <i class="ti ti-chart-donut"></i>
            <span>Sin diagnósticos registrados</span>
          </div>

          <div v-else class="dashboard-chart-body-sm">
            <canvas ref="chartEnfermedades"></canvas>
          </div>
        </div>
      </div>

      <!-- Mapa + Actividad -->
      <div class="dashboard-bottom-grid">
        <!-- Mapa -->
        <div class="card map-panel">
          <div class="card-title">
            <span>
              <i class="ti ti-map-pin"></i>
              Mapa de parcelas
            </span>
            <span class="card-subtitle">
              {{ parcelasConGps }} con GPS
            </span>
          </div>

          <div ref="mapContainer" class="dashboard-map"></div>
        </div>

        <!-- Panel derecho -->
        <div class="activity-column">
          <!-- Alertas -->
          <div class="card activity-card">
            <div class="card-title">
              <span>
                <i class="ti ti-alert-triangle"></i>
                Alertas activas
              </span>

              <router-link to="/dashboard/alertas" class="card-link">
                Ver todas →
              </router-link>
            </div>

            <div v-if="!alertasRecientes.length" class="empty-state compact">
              <i class="ti ti-circle-check"></i>
              <span>Sin alertas activas</span>
            </div>

            <div v-else class="activity-list">
              <div
                v-for="a in alertasRecientes.slice(0, 4)"
                :key="a.id"
                class="activity-item alert-item-clean"
              >
                <span class="b" :class="badgeRiesgo(a.nivel_riesgo)">
                  {{ limpiarNivel(a.nivel_riesgo) }}
                </span>

                <div class="activity-info">
                  <div class="activity-title">
                    <i class="ti ti-alert-triangle text-warning"></i>
                    {{ a.titulo || 'Alerta fitosanitaria' }}
                  </div>
                  <div class="activity-sub">
                    {{ a.diagnostico?.inspeccion?.parcela?.codigo_parcela || 'Sin parcela' }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Inspecciones -->
          <div class="card activity-card">
            <div class="card-title">
              <span>
                <i class="ti ti-clipboard-list"></i>
                Últimas inspecciones
              </span>

              <router-link to="/dashboard/inspecciones" class="card-link">
                Ver todas →
              </router-link>
            </div>

            <div v-if="!inspeccionesRecientes.length" class="empty-state compact">
              <i class="ti ti-clipboard-x"></i>
              <span>Sin inspecciones registradas</span>
            </div>

            <div v-else class="activity-list">
              <div
                v-for="i in inspeccionesRecientes.slice(0, 4)"
                :key="i.id"
                class="activity-item"
              >
                <div class="av small">
                  {{ i.usuario?.name?.charAt(0)?.toUpperCase() || 'U' }}
                </div>

                <div class="activity-info">
                  <div class="activity-title">
                    {{ i.parcela?.codigo_parcela || 'Sin parcela' }}
                    <span v-if="i.parcela?.productor?.nombre_completo">
                      — {{ i.parcela.productor.nombre_completo }}
                    </span>
                  </div>

                  <div class="activity-sub">
                    {{ formatDate(i.fecha_visita) }}
                    ·
                    {{ i.usuario?.name || 'Usuario' }} {{ i.usuario?.paterno || '' }}
                  </div>
                </div>

                <span class="b" :class="badgeEstado(i.estado)">
                  {{ limpiarEstado(i.estado) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import alertaService from '@/services/modules/alertaService';
import dashboardService from '@/services/modules/dashboardService';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import Chart from 'chart.js/auto';
import DashboardComercial from '@/components/dashboard/DashboardComercial.vue';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

const router = useRouter();

const usuarioActual = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user_data') || '{}');
  } catch {
    return {};
  }
});

const rolNombre = computed(() => {
  return (
    usuarioActual.value?.rol?.nombre ||
    usuarioActual.value?.role?.nombre ||
    usuarioActual.value?.rol ||
    usuarioActual.value?.role ||
    ''
  );
});

const esComercial = computed(() => rolNombre.value === 'Comercial');

const kpis = ref(null);
const parcelasMapa = ref([]);
const alertasRecientes = ref([]);
const inspeccionesRecientes = ref([]);
const enfermedadesData = ref([]);
const alertasActivas = ref(0);

const loadingPage = ref(false);
const loadingCharts = ref(true);
const error = ref('');

const mapContainer = ref(null);
const chartCosechas = ref(null);
const chartEnfermedades = ref(null);

let mapInstance = null;
let markerLayer = null;
let chartCosechasInstance = null;
let chartEnfermedadesInstance = null;

const fechaActual = computed(() => {
  return new Date().toLocaleDateString('es-BO', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const inicialUsuario = computed(() => {
  return usuarioActual.value?.name?.charAt(0)?.toUpperCase() || 'U';
});

const parcelasConGps = computed(() => {
  return parcelasMapa.value.filter((p) => p.latitud && p.longitud).length;
});

onMounted(async () => {
  if (!esComercial.value) {
    await fetchAll();
  }
});

onBeforeUnmount(() => {
  destroyCharts();

  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});

async function fetchAll() {
  loadingPage.value = true;
  loadingCharts.value = true;
  error.value = '';

  try {
    const user = usuarioActual.value;

    const permisos = Array.isArray(user?.permisos)
      ? user.permisos
      : Array.isArray(user?.rol?.permisos)
        ? user.rol.permisos
        : Array.isArray(user?.role?.permisos)
          ? user.role.permisos
          : [];

    const nombreRol =
      user?.rol?.nombre ||
      user?.role?.nombre ||
      user?.rol ||
      user?.role ||
      '';

    const puedeVerAlertas =
      nombreRol === 'Superadmin' ||
      permisos.includes('ver_alertas');

    const [kpisRes, mapaRes, alertasRes, inspecRes] = await Promise.allSettled([
      dashboardService.kpis(),
      dashboardService.mapaParcelas(),
      dashboardService.alertasRecientes(),
      dashboardService.inspeccionesRecientes(),
    ]);

    kpis.value =
      kpisRes.status === 'fulfilled'
        ? kpisRes.value.data || {}
        : {};

    parcelasMapa.value =
      mapaRes.status === 'fulfilled' && Array.isArray(mapaRes.value.data)
        ? mapaRes.value.data
        : [];

    alertasRecientes.value =
      alertasRes.status === 'fulfilled' && Array.isArray(alertasRes.value.data)
        ? alertasRes.value.data
        : [];

    inspeccionesRecientes.value =
      inspecRes.status === 'fulfilled' && Array.isArray(inspecRes.value.data)
        ? inspecRes.value.data
        : [];

    if (puedeVerAlertas) {
      try {
        const noLeidasRes = await alertaService.noLeidas();
        alertasActivas.value = noLeidasRes.data?.count || 0;
      } catch {
        alertasActivas.value = 0;
      }
    } else {
      alertasActivas.value = 0;
    }

    await nextTick();
    initMap();

    const [enfRes, cosRes] = await Promise.allSettled([
      dashboardService.graficoEnfermedades(),
      dashboardService.graficoCosechas(),
    ]);

    enfermedadesData.value =
      enfRes.status === 'fulfilled' && Array.isArray(enfRes.value.data)
        ? enfRes.value.data
        : [];

    loadingCharts.value = false;

    await nextTick();

    initChartCosechas(
      cosRes.status === 'fulfilled' && Array.isArray(cosRes.value.data)
        ? cosRes.value.data
        : []
    );

    initChartEnfermedades(enfermedadesData.value);
  } catch (err) {
    console.error(err);
    error.value = err.response?.data?.message || 'No se pudo cargar la información del panel de control.';
    loadingCharts.value = false;
  } finally {
    loadingPage.value = false;
  }
}

function initMap() {
  if (!mapContainer.value) {
    return;
  }

  if (!mapInstance) {
    mapInstance = L.map(mapContainer.value, {
      zoomControl: true,
      scrollWheelZoom: false,
    }).setView([-16.5, -68.15], 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(mapInstance);

    markerLayer = L.layerGroup().addTo(mapInstance);
  }

  markerLayer.clearLayers();

  const parcelasValidas = parcelasMapa.value.filter((p) => p.latitud && p.longitud);

  parcelasValidas.forEach((p) => {
    L.marker([Number(p.latitud), Number(p.longitud)])
      .addTo(markerLayer)
      .bindPopup(`
        <div style="font-family:Plus Jakarta Sans, sans-serif;font-size:12px;min-width:170px">
          <strong style="color:#2e7d5a">${p.codigo_parcela || 'Parcela'}</strong><br/>
          ${p.nombre_referencial || ''}<br/>
          <span style="color:#64748b">Productor:</span> ${p.productor?.nombre_completo || '—'}<br/>
          <span style="color:#64748b">Superficie:</span> ${p.superficie_ha || '—'} ha<br/>
          <span style="color:#64748b">Estado:</span> ${p.estado || '—'}
        </div>
      `);
  });

  if (parcelasValidas.length > 1) {
    const bounds = parcelasValidas.map((p) => [Number(p.latitud), Number(p.longitud)]);
    mapInstance.fitBounds(bounds, { padding: [35, 35] });
  }

  setTimeout(() => {
    mapInstance?.invalidateSize();
  }, 120);
}

function initChartCosechas(data) {
  if (!chartCosechas.value) {
    return;
  }

  chartCosechasInstance?.destroy();

  chartCosechasInstance = new Chart(chartCosechas.value, {
    type: 'bar',
    data: {
      labels: data.length ? data.map((d) => d.mes) : ['Sin datos'],
      datasets: [
        {
          label: 'Toneladas',
          data: data.length ? data.map((d) => Number(d.total || 0)) : [0],
          backgroundColor: 'rgba(61, 168, 122, 0.45)',
          borderColor: '#2e7d5a',
          borderWidth: 1,
          borderRadius: 7,
          maxBarThickness: 46,
        },
      ],
    },
    options: chartOptions(false),
  });
}

function initChartEnfermedades(data) {
  chartEnfermedadesInstance?.destroy();
  chartEnfermedadesInstance = null;

  if (!chartEnfermedades.value || !data.length) {
    return;
  }

  chartEnfermedadesInstance = new Chart(chartEnfermedades.value, {
    type: 'doughnut',
    data: {
      labels: data.map((d) => d.nombre),
      datasets: [
        {
          data: data.map((d) => Number(d.total || 0)),
          backgroundColor: [
            '#3da87a',
            '#f87171',
            '#60a5fa',
            '#fbbf24',
            '#c084fc',
            '#fb923c',
          ],
          borderWidth: 3,
          borderColor: '#ffffff',
        },
      ],
    },
    options: chartOptions(true),
  });
}

function chartOptions(showLegend) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    resizeDelay: 100,
    plugins: {
      legend: {
        display: showLegend,
        position: 'bottom',
        labels: {
          boxWidth: 12,
          boxHeight: 12,
          padding: 14,
          font: {
            family: 'Plus Jakarta Sans',
            size: 11,
            weight: 700,
          },
        },
      },
      tooltip: {
        titleFont: {
          family: 'Plus Jakarta Sans',
        },
        bodyFont: {
          family: 'Plus Jakarta Sans',
        },
      },
    },
    scales: showLegend
      ? {}
      : {
          x: {
            ticks: {
              font: {
                family: 'Plus Jakarta Sans',
                size: 11,
              },
            },
            grid: {
              display: false,
            },
          },
          y: {
            beginAtZero: true,
            ticks: {
              font: {
                family: 'Plus Jakarta Sans',
                size: 11,
              },
            },
            grid: {
              color: 'rgba(148, 163, 184, 0.2)',
            },
          },
        },
  };
}

function destroyCharts() {
  chartCosechasInstance?.destroy();
  chartEnfermedadesInstance?.destroy();

  chartCosechasInstance = null;
  chartEnfermedadesInstance = null;
}

function badgeRiesgo(nivel) {
  const value = normalizarTexto(nivel);

  return {
    bajo: 'bg',
    medio: 'ba',
    alto: 'bgr',
    critico: 'bgr',
    crítico: 'bgr',
  }[value] || 'bgr';
}

function badgeEstado(estado) {
  const value = normalizarTexto(estado);

  return {
    pendiente: 'badge-yellow',
    en_proceso: 'ba',
    completada: 'bg',
    validada: 'bb',
  }[value] || 'bgr';
}

function limpiarNivel(nivel) {
  if (!nivel) {
    return '—';
  }

  return String(nivel).replace('_', ' ');
}

function limpiarEstado(estado) {
  if (!estado) {
    return '—';
  }

  return String(estado).replaceAll('_', ' ');
}

function formatDate(dateStr) {
  if (!dateStr) {
    return 'Sin fecha';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return 'Sin fecha';
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function normalizarTexto(value) {
  return String(value || '')
    .trim()
    .toLowerCase();
}
</script>