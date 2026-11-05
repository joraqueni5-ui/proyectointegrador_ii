<template>
  <div class="alertas-page fade-in">
    <!-- =====================================================
         BARRA SUPERIOR
    ====================================================== -->

    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size: 14px"></i>

        <span>›</span>

        <span> Módulo Diagnóstico </span>

        <span>›</span>

        <span class="cur"> Alertas </span>
      </div>

      <div class="cbar-right">
        <span v-if="noLeidas > 0" class="b br alertas-no-leidas-badge">
          <i class="ti ti-bell-ringing"></i>

          {{ noLeidas }} sin leer
        </span>

        <button type="button" class="btn btn-s" :disabled="loading" @click="cargarTodo">
          <i class="ti ti-refresh"></i>

          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </div>
    </div>

    <!-- =====================================================
         CABECERA
    ====================================================== -->

    <div class="pg-header alertas-header">
      <div>
        <div class="pg-title">Alertas diagnósticas</div>

        <div class="pg-sub">Notificaciones generadas por diagnósticos de riesgo fitosanitario.</div>
      </div>
    </div>

    <!-- =====================================================
         ERROR GENERAL
    ====================================================== -->

    <div v-if="pageError" class="alertas-error">
      {{ pageError }}
    </div>

    <!-- =====================================================
         KPIS GLOBALES
    ====================================================== -->

    <AlertasKpis
      v-if="!loading"
      :total-alertas="estadisticas.total"
      :criticas="estadisticas.criticas"
      :altas="estadisticas.por_prioridad?.Alto ?? 0"
      :no-leidas="estadisticas.no_leidas"
      :atendidas="estadisticas.resueltas"
    />

    <!-- =====================================================
         CONTENIDO
    ====================================================== -->

    <div class="alertas-layout">
      <!-- ===================================================
           COLUMNA IZQUIERDA
      ==================================================== -->

      <div class="alertas-left">
        <AlertasFiltros
          v-model:nivel="filtros.nivel"
          v-model:estado="filtros.estado"
          v-model:estadoSeguimiento="filtros.estadoSeguimiento"
          v-model:soloMias="filtros.soloMias"
          :loading="loading"
          @buscar="buscarAlertas"
          @limpiar="limpiarFiltros"
          @actualizar="cargarTodo"
        />

        <AlertasLista :alertas="alertas" :loading="loading" @select="seleccionarAlerta" />

        <AlertasPaginacion :pagination="pagination" @change-page="cambiarPagina" />
      </div>

      <!-- ===================================================
           COLUMNA DERECHA
      ==================================================== -->

      <div class="alertas-right">
        <AlertaDetallePanel
          :alerta="alertaSeleccionada"
          :saving="saving"
          :puede-gestionar="puedeGestionar"
          @close="limpiarSeleccion"
          @marcar-leida="marcarLeida"
          @atender="abrirAtender"
          @planificar="abrirPlanificar"
          @iniciar-tratamiento="abrirTratamiento"
          @registrar-seguimiento="abrirSeguimiento"
          @resolver="abrirResolver"
        />

        <AlertasResumen
          :activas="conteo.activas"
          :vencidas="conteo.vencidas"
          :ignoradas="conteo.ignoradas"
          :atendidas="conteo.atendidas"
        />
      </div>
    </div>

    <!-- =====================================================
         MODAL INICIAR REVISIÓN
    ====================================================== -->

    <AlertaAtenderModal
      v-if="showAtenderModal"
      :alerta="alertaAAtender"
      :saving="saving"
      :error="atenderError"
      @close="cerrarAtender"
      @save="handleAtender"
    />

    <!-- =====================================================
         MODAL PLANIFICAR
    ====================================================== -->

    <AlertaPlanificarModal
      v-if="showPlanificarModal"
      :alerta="alertaAPlanificar"
      :saving="saving"
      :error="planificarError"
      @close="cerrarPlanificar"
      @save="handlePlanificar"
    />

    <!-- =====================================================
         MODAL TRATAMIENTO
    ====================================================== -->

    <AlertaTratamientoModal
      v-if="showTratamientoModal"
      :alerta="alertaATratamiento"
      :saving="saving"
      :error="tratamientoError"
      @close="cerrarTratamiento"
      @save="handleIniciarTratamiento"
    />

    <!-- =====================================================
         MODAL SEGUIMIENTO
    ====================================================== -->

    <AlertaSeguimientoModal
      v-if="showSeguimientoModal"
      :alerta="alertaASeguimiento"
      :saving="saving"
      :error="seguimientoError"
      @close="cerrarSeguimiento"
      @save="handleSeguimiento"
    />

    <!-- =====================================================
         MODAL RESOLVER
    ====================================================== -->

    <AlertaResolverModal
      v-if="showResolverModal"
      :alerta="alertaAResolver"
      :saving="saving"
      :error="resolverError"
      @close="cerrarResolver"
      @save="handleResolver"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import alertaService from '@/services/modules/alertaService';
import { obtenerMensajeError } from '@/services/errorService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';
import AlertasKpis from '@/components/alertas/AlertasKpis.vue';
import AlertasFiltros from '@/components/alertas/AlertasFiltros.vue';
import AlertasLista from '@/components/alertas/AlertasLista.vue';
import AlertaDetallePanel from '@/components/alertas/AlertaDetallePanel.vue';
import AlertasResumen from '@/components/alertas/AlertasResumen.vue';
import AlertasPaginacion from '@/components/alertas/AlertasPaginacion.vue';
import AlertaAtenderModal from '@/components/alertas/AlertaAtenderModal.vue';
import AlertaPlanificarModal from '@/components/alertas/AlertaPlanificarModal.vue';
import AlertaTratamientoModal from '@/components/alertas/AlertaTratamientoModal.vue';
import AlertaSeguimientoModal from '@/components/alertas/AlertaSeguimientoModal.vue';
import AlertaResolverModal from '@/components/alertas/AlertaResolverModal.vue';
import '@/styles/alertas.css';

/* ============================================================
   ALERTAS
============================================================ */

const alertas = ref([]);

const alertaSeleccionada = ref(null);

/* ============================================================
   ESTADÍSTICAS GLOBALES
============================================================ */

const estadisticas = ref({
  total: 0,
  resueltas: 0,
  descartadas: 0,
  pendientes: 0,
  criticas: 0,
  vencidas_seguimiento: 0,
  sin_responsable: 0,
  en_tratamiento: 0,
  en_seguimiento: 0,
  no_leidas: 0,
  tasa_resolucion: 0,
  por_prioridad: {
    Critico: 0,
    Alto: 0,
    Medio: 0,
    Bajo: 0,
  },
  por_seguimiento: {},
});

/* ============================================================
   MODAL REVISIÓN
============================================================ */

const alertaAAtender = ref(null);

const showAtenderModal = ref(false);

const atenderError = ref('');

/* ============================================================
   MODAL PLANIFICACIÓN
============================================================ */

const alertaAPlanificar = ref(null);

const showPlanificarModal = ref(false);

const planificarError = ref('');

/* ============================================================
   MODAL TRATAMIENTO
============================================================ */

const alertaATratamiento = ref(null);

const showTratamientoModal = ref(false);

const tratamientoError = ref('');

/* ============================================================
   MODAL SEGUIMIENTO
============================================================ */

const alertaASeguimiento = ref(null);

const showSeguimientoModal = ref(false);

const seguimientoError = ref('');

/* ============================================================
   MODAL RESOLVER
============================================================ */

const alertaAResolver = ref(null);

const showResolverModal = ref(false);

const resolverError = ref('');

/* ============================================================
   ESTADO GENERAL
============================================================ */

const loading = ref(true);

const saving = ref(false);

const pageError = ref('');

const noLeidas = ref(0);

/* ============================================================
   PAGINACIÓN
============================================================ */

const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

/* ============================================================
   FILTROS
============================================================ */

const filtros = ref({
  nivel: '',
  estado: '',
  estadoSeguimiento: '',
  soloMias: false,
});

/* ============================================================
   CONTEOS DE LA PÁGINA ACTUAL
============================================================ */

const puedeGestionar = tienePermiso(usuarioGuardado(), 'gestionar_alertas');

const conteo = computed(() => ({
  Critico: alertas.value.filter((alerta) => ['Critico', 'Crítico'].includes(alerta.nivel_riesgo))
    .length,
  Alto: alertas.value.filter((alerta) => alerta.nivel_riesgo === 'Alto').length,
  Medio: alertas.value.filter((alerta) => alerta.nivel_riesgo === 'Medio').length,
  Bajo: alertas.value.filter((alerta) => alerta.nivel_riesgo === 'Bajo').length,
  activas: alertas.value.filter((alerta) => alerta.estado === 'Activa').length,
  atendidas: alertas.value.filter((alerta) => alerta.estado === 'Atendida').length,
  vencidas: alertas.value.filter((alerta) => alerta.estado === 'Vencida').length,
  ignoradas: alertas.value.filter((alerta) => alerta.estado === 'Ignorada').length,
}));

/* ============================================================
   INICIO
============================================================ */

onMounted(cargarTodo);

/* ============================================================
   CARGAR TODO
============================================================ */

async function cargarTodo() {
  pageError.value = '';

  await Promise.all([
    fetchAlertas(pagination.value.current_page || 1),

    fetchNoLeidas(),

    fetchEstadisticas(),
  ]);
}

/* ============================================================
   ESTADÍSTICAS GLOBALES
============================================================ */

async function fetchEstadisticas() {
  try {
    const { data } = await alertaService.estadisticas();

    estadisticas.value = {
      total: Number(data?.total ?? 0),
      resueltas: Number(data?.resueltas ?? 0),
      descartadas: Number(data?.descartadas ?? 0),
      pendientes: Number(data?.pendientes ?? 0),
      criticas: Number(data?.criticas ?? 0),
      vencidas_seguimiento: Number(data?.vencidas_seguimiento ?? 0),
      sin_responsable: Number(data?.sin_responsable ?? 0),
      en_tratamiento: Number(data?.en_tratamiento ?? 0),
      en_seguimiento: Number(data?.en_seguimiento ?? 0),
      no_leidas: Number(data?.no_leidas ?? 0),
      tasa_resolucion: Number(data?.tasa_resolucion ?? 0),
      por_prioridad: data?.por_prioridad ?? {},
      por_seguimiento: data?.por_seguimiento ?? {},
    };

    /*
     * Sincronizamos también el contador
     * de la barra superior.
     */
    noLeidas.value = estadisticas.value.no_leidas;
  } catch (err) {
    console.error('Error cargando estadísticas de alertas:', err);
  }
}

/* ============================================================
   LISTAR ALERTAS
============================================================ */

async function fetchAlertas(page = 1) {
  loading.value = true;

  pageError.value = '';

  try {
    const params = {
      page,
    };

    /* NIVEL DE RIESGO */

    if (filtros.value.nivel) {
      params.nivel_riesgo = filtros.value.nivel;
    }

    /* ESTADO GENERAL */

    if (filtros.value.estado) {
      params.estado = filtros.value.estado;
    }

    /* ESTADO DE SEGUIMIENTO */

    if (filtros.value.estadoSeguimiento) {
      params.estado_seguimiento = filtros.value.estadoSeguimiento;
    }

    /* SOLO ALERTAS ASIGNADAS AL USUARIO */

    if (filtros.value.soloMias) {
      params.solo_asignadas = 1;
    }

    const { data } = await alertaService.listar(params);

    alertas.value = Array.isArray(data) ? data : (data?.data ?? []);

    pagination.value = {
      current_page: data?.current_page ?? page,
      last_page: data?.last_page ?? 1,
      total: data?.total ?? alertas.value.length,
    };

    sincronizarSeleccion();
  } catch (err) {
    console.error(err);

    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las alertas diagnósticas.');

    alertas.value = [];
  } finally {
    loading.value = false;
  }
}

/* ============================================================
   NO LEÍDAS
============================================================ */

async function fetchNoLeidas() {
  try {
    const { data } = await alertaService.noLeidas();

    noLeidas.value = Number(data?.count ?? 0);
  } catch (err) {
    console.error('Error cargando alertas no leídas:', err);
  }
}

/* ============================================================
   BUSCAR
============================================================ */

async function buscarAlertas() {
  pagination.value.current_page = 1;

  await fetchAlertas(1);
}

/* ============================================================
   LIMPIAR FILTROS
============================================================ */

async function limpiarFiltros() {
  filtros.value = {
    nivel: '',
    estado: '',
    estadoSeguimiento: '',
    soloMias: false,
  };

  pagination.value.current_page = 1;

  await fetchAlertas(1);
}

/* ============================================================
   CAMBIAR PÁGINA
============================================================ */

function cambiarPagina(page) {
  fetchAlertas(page);
}

/* ============================================================
   SELECCIONAR ALERTA
============================================================ */

async function seleccionarAlerta(alerta) {
  if (!alerta?.id) {
    return;
  }

  pageError.value = '';

  try {
    const { data } = await alertaService.obtener(alerta.id);

    alertaSeleccionada.value = data;

    if (!data?.leida) {
      await marcarLeida(data);
    }
  } catch (err) {
    console.error(err);

    alertaSeleccionada.value = alerta;

    if (!alerta.leida) {
      await marcarLeida(alerta);
    }
  }
}

/* ============================================================
   MARCAR LEÍDA
============================================================ */

async function marcarLeida(alerta) {
  if (!alerta?.id || alerta.leida) {
    return;
  }

  saving.value = true;

  pageError.value = '';

  try {
    await alertaService.marcarLeida(alerta.id);

    actualizarAlertaLocal(alerta.id, {
      leida: true,
      fecha_lectura: new Date().toISOString(),
    });

    if (alertaSeleccionada.value?.id === alerta.id) {
      alertaSeleccionada.value = {
        ...alertaSeleccionada.value,
        leida: true,
        fecha_lectura: new Date().toISOString(),
      };
    }

    noLeidas.value = Math.max(0, noLeidas.value - 1);

    estadisticas.value.no_leidas = noLeidas.value;
  } catch (err) {
    console.error(err);

    pageError.value = obtenerMensajeError(err, 'No se pudo marcar la alerta como leída.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   INICIAR REVISIÓN
============================================================ */

function abrirAtender(alerta) {
  alertaAAtender.value = alerta;

  atenderError.value = '';

  showAtenderModal.value = true;
}

function cerrarAtender() {
  showAtenderModal.value = false;

  alertaAAtender.value = null;

  atenderError.value = '';
}

async function handleAtender(payload) {
  if (!alertaAAtender.value?.id) {
    return;
  }

  saving.value = true;

  atenderError.value = '';

  const id = alertaAAtender.value.id;

  try {
    await alertaService.iniciarRevision(id, payload);

    cerrarAtender();

    await refrescarAlerta(id);
  } catch (err) {
    console.error(err);

    atenderError.value = obtenerMensajeError(err, 'No se pudo iniciar la revisión.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   PLANIFICAR
============================================================ */

function abrirPlanificar(alerta) {
  alertaAPlanificar.value = alerta;

  planificarError.value = '';

  showPlanificarModal.value = true;
}

function cerrarPlanificar() {
  showPlanificarModal.value = false;

  alertaAPlanificar.value = null;

  planificarError.value = '';
}

async function handlePlanificar(payload) {
  if (!alertaAPlanificar.value?.id) {
    return;
  }

  saving.value = true;

  planificarError.value = '';

  const id = alertaAPlanificar.value.id;

  try {
    await alertaService.planificar(id, payload);

    cerrarPlanificar();

    await refrescarAlerta(id);
  } catch (err) {
    console.error(err);

    planificarError.value = obtenerMensajeError(err, 'No se pudo registrar el plan de manejo.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   INICIAR TRATAMIENTO
============================================================ */

function abrirTratamiento(alerta) {
  alertaATratamiento.value = alerta;

  tratamientoError.value = '';

  showTratamientoModal.value = true;
}

function cerrarTratamiento() {
  showTratamientoModal.value = false;

  alertaATratamiento.value = null;

  tratamientoError.value = '';
}

async function handleIniciarTratamiento(payload) {
  if (!alertaATratamiento.value?.id) {
    return;
  }

  saving.value = true;

  tratamientoError.value = '';

  const id = alertaATratamiento.value.id;

  try {
    await alertaService.iniciarTratamiento(id, payload);

    cerrarTratamiento();

    await refrescarAlerta(id);
  } catch (err) {
    console.error(err);

    tratamientoError.value = obtenerMensajeError(err, 'No se pudo iniciar el tratamiento.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   REGISTRAR SEGUIMIENTO
============================================================ */

function abrirSeguimiento(alerta) {
  alertaASeguimiento.value = alerta;

  seguimientoError.value = '';

  showSeguimientoModal.value = true;
}

function cerrarSeguimiento() {
  showSeguimientoModal.value = false;

  alertaASeguimiento.value = null;

  seguimientoError.value = '';
}

async function handleSeguimiento(payload) {
  if (!alertaASeguimiento.value?.id) {
    return;
  }

  saving.value = true;

  seguimientoError.value = '';

  const id = alertaASeguimiento.value.id;

  try {
    await alertaService.registrarSeguimiento(id, payload);

    cerrarSeguimiento();

    await refrescarAlerta(id);
  } catch (err) {
    console.error(err);

    seguimientoError.value = obtenerMensajeError(err, 'No se pudo registrar el seguimiento.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   RESOLVER ALERTA
============================================================ */

function abrirResolver(alerta) {
  alertaAResolver.value = alerta;

  resolverError.value = '';

  showResolverModal.value = true;
}

function cerrarResolver() {
  showResolverModal.value = false;

  alertaAResolver.value = null;

  resolverError.value = '';
}

async function handleResolver(payload) {
  if (!alertaAResolver.value?.id) {
    return;
  }

  saving.value = true;

  resolverError.value = '';

  const id = alertaAResolver.value.id;

  try {
    await alertaService.resolver(id, payload);

    cerrarResolver();

    await refrescarAlerta(id);
  } catch (err) {
    console.error(err);

    resolverError.value = obtenerMensajeError(err, 'No se pudo resolver la alerta.');
  } finally {
    saving.value = false;
  }
}

/* ============================================================
   REFRESCAR ALERTA Y ESTADÍSTICAS
============================================================ */

async function refrescarAlerta(id) {
  await Promise.all([
    fetchAlertas(pagination.value.current_page || 1),

    fetchNoLeidas(),

    fetchEstadisticas(),
  ]);

  try {
    const { data } = await alertaService.obtener(id);

    alertaSeleccionada.value = data;
  } catch (err) {
    console.error(err);

    alertaSeleccionada.value = null;
  }
}

/* ============================================================
   ACTUALIZAR ALERTA LOCAL
============================================================ */

function actualizarAlertaLocal(id, cambios) {
  alertas.value = alertas.value.map((alerta) => {
    if (alerta.id !== id) {
      return alerta;
    }

    return {
      ...alerta,

      ...cambios,
    };
  });
}

/* ============================================================
   SINCRONIZAR SELECCIÓN
============================================================ */

function sincronizarSeleccion() {
  if (!alertaSeleccionada.value?.id) {
    return;
  }

  const existe = alertas.value.find((alerta) => alerta.id === alertaSeleccionada.value.id);

  if (!existe) {
    alertaSeleccionada.value = null;
  }
}

/* ============================================================
   CERRAR DETALLE
============================================================ */

function limpiarSeleccion() {
  alertaSeleccionada.value = null;

  pageError.value = '';
}
</script>
