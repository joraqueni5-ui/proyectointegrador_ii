<template>
  <div class="diagnosticos-page fade-in">
    <!-- CONTEXTBAR -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Diagnóstico IA</span>
        <span>›</span>
        <span class="cur">Diagnósticos CNN</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" :disabled="loading" @click="cargarTodo">
          <i class="ti ti-refresh"></i>
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </div>
    </div>

    <!-- HEADER -->
    <div class="pg-header diagnosticos-header">
      <div>
        <div class="pg-title">Diagnóstico fitosanitario</div>
        <div class="pg-sub">
          Análisis preliminar de enfermedades en cultivos de papa mediante imágenes.
        </div>

        <div
          class="cnn-status"
          :class="cnnStatus.disponible ? 'cnn-online' : 'cnn-offline'"
        >
          <span class="cnn-status-dot"></span>
          <span v-if="cnnStatus.cargando">Verificando servicio CNN...</span>
          <span v-else-if="cnnStatus.disponible">
            CNN disponible
            <strong v-if="cnnStatus.version">— {{ cnnStatus.version }}</strong>
          </span>
          <span v-else>CNN no disponible</span>
        </div>
      </div>
    </div>

    <div v-if="pageError" class="diagnosticos-error">
      {{ pageError }}
    </div>

    <!-- PANEL TIPO MOCKUP -->
    <div class="diagnostico-cnn-layout">
      <DiagnosticoAnalizador
        v-if="puedeCrear"
        ref="analizadorRef"
        :inspecciones="inspecciones"
        :saving="saving"
        :cnn-disponible="cnnStatus.disponible"
        :cnn-version="cnnStatus.version"
        :rechazo="rechazoFoto"
        @save="handleSave"
        @limpiar-rechazo="rechazoFoto = null"
      />

      <DiagnosticoResultadoPanel
        :diagnostico="diagnosticoSeleccionado"
      />
    </div>

    <!-- KPIS -->
    <DiagnosticosKpis
      v-if="!loading"
      :total-diagnosticos="pagination?.total ?? diagnosticos.length"
      :pendientes="totalPendientes"
      :confirmados="totalConfirmados"
      :corregidos="totalCorregidos"
      :rechazados="totalRechazados"
    />

    <!-- HISTORIAL -->
    <div class="card diagnosticos-card">
      <DiagnosticosFiltros
        v-model:search="search"
        v-model:id-inspeccion="filtros.id_inspeccion"
        v-model:estado-validacion="filtros.estado_validacion"
        :inspecciones="inspecciones"
        @buscar="buscarDiagnosticos"
        @limpiar="limpiarFiltros"
      />

      <div v-if="loading" class="loading-state diagnosticos-loading">
        <div class="spinner"></div>
        <span>Cargando diagnósticos...</span>
      </div>

      <template v-else>
        <DiagnosticosTabla
          :diagnosticos="diagnosticosFiltrados"
          :puede-validar="puedeValidar"
          @select="seleccionarDiagnostico"
          @view="verDetalle"
          @validar="openValidar"
        />

        <DiagnosticosPaginacion
          :pagination="pagination"
          @change-page="cambiarPagina"
        />
      </template>
    </div>

    <!-- MODAL DETALLE -->
    <DiagnosticoDetalleModal
      v-if="showDetalle"
      :diagnostico="diagnosticoDetalle"
      @close="closeDetalle"
      @ir-movimientos="irAMovimientos"
    />

    <!-- MODAL VALIDAR -->
    <DiagnosticoValidarModal
      v-if="showValidarModal"
      :diagnostico="diagnosticoValidar"
      :enfermedades="enfermedades"
      :saving="saving"
      :error="validarError"
      @close="closeValidar"
      @save="handleValidar"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

import '@/styles/diagnosticos.css';

import diagnosticoService from '@/services/modules/diagnosticoService';
import inspeccionService from '@/services/modules/inspeccionService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';
import enfermedadService from '@/services/modules/enfermedadService';
import { obtenerMensajeError } from '@/services/errorService';

import DiagnosticoAnalizador from '@/components/diagnosticos/DiagnosticoAnalizador.vue';
import DiagnosticoResultadoPanel from '@/components/diagnosticos/DiagnosticoResultadoPanel.vue';
import DiagnosticosKpis from '@/components/diagnosticos/DiagnosticosKpis.vue';
import DiagnosticosFiltros from '@/components/diagnosticos/DiagnosticosFiltros.vue';
import DiagnosticosTabla from '@/components/diagnosticos/DiagnosticosTabla.vue';
import DiagnosticoDetalleModal from '@/components/diagnosticos/DiagnosticoDetalleModal.vue';
import DiagnosticoValidarModal from '@/components/diagnosticos/DiagnosticoValidarModal.vue';
import DiagnosticosPaginacion from '@/components/diagnosticos/DiagnosticosPaginacion.vue';

const router = useRouter();

const diagnosticos = ref([]);
const inspecciones = ref([]);
const enfermedades = ref([]);

const loading = ref(true);
const saving = ref(false);
const pageError = ref('');
// Motivo por el que la CNN rechazó la última foto (mensaje + consejo + código).
const rechazoFoto = ref(null);

const cnnStatus = ref({
  disponible: false,
  cargando: true,
  version: null,
});

const pagination = ref(null);
const paginaActual = ref(1);

const search = ref('');

const filtros = ref({
  id_inspeccion: '',
  estado_validacion: '',
});

const diagnosticoSeleccionado = ref(null);
const diagnosticoDetalle = ref(null);
const diagnosticoValidar = ref(null);

const showDetalle = ref(false);
const showValidarModal = ref(false);

const analizadorRef = ref(null);

const validarError = ref('');

// Se usan los mismos permisos que comprueba el backend.
const usuario = usuarioGuardado();
const puedeValidar = tienePermiso(usuario, 'validar_diagnosticos');
const puedeCrear = tienePermiso(usuario, 'crear_diagnosticos');

const totalPendientes = computed(() => {
  return diagnosticos.value.filter((d) => d.estado_validacion === 'Pendiente').length;
});

const totalConfirmados = computed(() => {
  return diagnosticos.value.filter((d) => d.estado_validacion === 'Confirmado').length;
});

const totalCorregidos = computed(() => {
  return diagnosticos.value.filter((d) => d.estado_validacion === 'Corregido').length;
});

const totalRechazados = computed(() => {
  return diagnosticos.value.filter((d) => d.estado_validacion === 'Rechazado').length;
});

const diagnosticosFiltrados = computed(() => {
  const q = search.value.trim().toLowerCase();

  if (!q) {
    return diagnosticos.value;
  }

  return diagnosticos.value.filter((d) => {
    const texto = [
      d.id,
      d.enfermedad?.nombre_comun,
      d.enfermedad?.nombre_cientifico,
      d.enfermedad?.codigo_enfermedad,
      d.inspeccion?.parcela?.codigo_parcela,
      d.inspeccion?.parcela?.nombre_referencial,
      d.inspeccion?.parcela?.productor?.nombre_completo,
      d.estado_validacion,
      d.nivel_severidad,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return texto.includes(q);
  });
});

onMounted(cargarTodo);

async function cargarTodo() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchDiagnosticos(),
      fetchInspecciones(),
      fetchEnfermedades(),
      fetchCnnStatus(),
    ]);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar la información de diagnósticos.'
    );
  } finally {
    loading.value = false;
  }
}

async function fetchDiagnosticos() {
  const params = {
    page: paginaActual.value,
    per_page: 15,
  };

  if (filtros.value.id_inspeccion) {
    params.id_inspeccion = filtros.value.id_inspeccion;
  }

  if (filtros.value.estado_validacion) {
    params.estado_validacion = filtros.value.estado_validacion;
  }

  const { data } = await diagnosticoService.listar(params);

  diagnosticos.value = Array.isArray(data)
    ? data
    : data.data ?? [];

  pagination.value = data.current_page
    ? {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    : null;

  if (!diagnosticoSeleccionado.value && diagnosticos.value.length > 0) {
    await seleccionarDiagnostico(diagnosticos.value[0]);
  }
}

async function fetchInspecciones() {
  const { data } = await inspeccionService.listar({
    per_page: 100,
    diagnostico_elegible: 1,
  });

  // Filtro defensivo adicional: el backend ya devuelve solamente elegibles.
  inspecciones.value = (
    Array.isArray(data)
      ? data
      : data.data ?? []
  ).filter((i) => {
    const tipo = i.tipo_inspeccion || 'Cultivo';

    return (
      tipo === 'Cultivo' &&
      ['En_proceso', 'Completada'].includes(i.estado) &&
      i.cultivo?.estado === 'Activo'
    );
  });
}

async function fetchCnnStatus() {
  cnnStatus.value = {
    disponible: false,
    cargando: true,
    version: null,
  };

  try {
    const { data } = await diagnosticoService.estadoCnn();

    cnnStatus.value = {
      disponible: data?.status === 'ready' && data?.model_loaded === true,
      cargando: false,
      version: data?.model_version || null,
    };
  } catch (err) {
    console.warn('Servicio CNN no disponible:', err);
    cnnStatus.value = {
      disponible: false,
      cargando: false,
      version: null,
    };
  }
}

async function fetchEnfermedades() {
  const { data } = await enfermedadService.listar({
    activo: true,
  });

  enfermedades.value = Array.isArray(data)
    ? data
    : data.data ?? [];
}

async function seleccionarDiagnostico(diagnostico) {
  if (!diagnostico?.id) {
    return;
  }

  try {
    const { data } = await diagnosticoService.obtener(diagnostico.id);
    diagnosticoSeleccionado.value = data;
  } catch (err) {
    console.error(err);
    diagnosticoSeleccionado.value = diagnostico;
  }
}

async function verDetalle(diagnostico) {
  if (!diagnostico?.id) {
    return;
  }

  pageError.value = '';

  try {
    const { data } = await diagnosticoService.obtener(diagnostico.id);
    diagnosticoDetalle.value = data;
    diagnosticoSeleccionado.value = data;
    showDetalle.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar el detalle del diagnóstico.'
    );
  }
}

function closeDetalle() {
  showDetalle.value = false;
  diagnosticoDetalle.value = null;
}

function openValidar(diagnostico) {
  diagnosticoValidar.value = diagnostico;
  pageError.value = '';
  showValidarModal.value = true;
}

function closeValidar() {
  validarError.value = '';
  showValidarModal.value = false;
  diagnosticoValidar.value = null;
}

async function handleSave(formData) {
  if (saving.value) {
    return; // evita registros duplicados por doble clic
  }

  saving.value = true;
  pageError.value = '';
  rechazoFoto.value = null;

  try {
    const { data } = await diagnosticoService.crear(formData);

    if (data?.id) {
      try {
        const detalle = await diagnosticoService.obtener(data.id);
        diagnosticoSeleccionado.value = detalle.data;
      } catch {
        diagnosticoSeleccionado.value = data;
      }
    }

    // La inspección diagnosticada deja de ser elegible hasta que se valide.
    await Promise.all([fetchDiagnosticos(), fetchInspecciones()]);
    analizadorRef.value?.reset();
  } catch (err) {
    console.error(err);

    const motivo = err?.response?.data?.motivo;

    if (motivo?.codigo) {
      // La CNN rechazó la foto: se muestra el motivo exacto y qué hacer.
      rechazoFoto.value = {
        codigo: motivo.codigo,
        consejo: motivo.consejo,
        mensaje: err.response.data.message,
      };
    } else {
      pageError.value = obtenerMensajeError(
        err,
        'Error al registrar el diagnóstico.'
      );
    }
  } finally {
    saving.value = false;
  }
}

async function handleValidar(payload) {
  if (!diagnosticoValidar.value?.id) {
    return;
  }

  saving.value = true;
  validarError.value = '';

  try {
    await diagnosticoService.validar(
      diagnosticoValidar.value.id,
      payload
    );

    await Promise.all([fetchDiagnosticos(), fetchInspecciones()]);

    if (diagnosticoSeleccionado.value?.id === diagnosticoValidar.value.id) {
      await seleccionarDiagnostico(diagnosticoValidar.value);
    }

    closeValidar();
  } catch (err) {
    console.error(err);
    validarError.value = obtenerMensajeError(
      err,
      'Error al validar el diagnóstico.'
    );
  } finally {
    saving.value = false;
  }
}

function buscarDiagnosticos() {
  paginaActual.value = 1;
  loading.value = true;
  pageError.value = '';

  fetchDiagnosticos()
    .catch((err) => {
      console.error(err);
      pageError.value = obtenerMensajeError(
        err,
        'No se pudieron filtrar los diagnósticos.'
      );
    })
    .finally(() => {
      loading.value = false;
    });
}

function limpiarFiltros() {
  search.value = '';

  filtros.value = {
    id_inspeccion: '',
    estado_validacion: '',
  };

  paginaActual.value = 1;
  buscarDiagnosticos();
}

function cambiarPagina(pagina) {
  paginaActual.value = pagina;
  loading.value = true;
  pageError.value = '';

  fetchDiagnosticos()
    .catch((err) => {
      console.error(err);
      pageError.value = obtenerMensajeError(
        err,
        'No se pudo cambiar de página.'
      );
    })
    .finally(() => {
      loading.value = false;
    });
}

function irAMovimientos() {
  router.push('/dashboard/inventario/movimientos');
}
</script>