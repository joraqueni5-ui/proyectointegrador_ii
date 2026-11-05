<template>
  <div class="inspecciones-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size: 14px"></i>
        <span>›</span>
        <span>Diagnóstico IA</span>
        <span>›</span>
        <span class="cur">Inspecciones</span>
      </div>

      <div class="cbar-right">
        <button v-if="puedeGestionar" class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nueva inspección
        </button>
      </div>
    </div>

    <!-- Encabezado -->
    <div class="pg-header inspecciones-header">
      <div>
        <div class="pg-title">Inspecciones de campo</div>
        <div class="pg-sub">
          Visitas técnicas a las parcelas. Cada tarjeta te muestra el siguiente paso a seguir.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh" :class="{ girando: loading }"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="inspecciones-error">
      <i class="ti ti-alert-circle"></i>
      <span>{{ pageError }}</span>
    </div>

    <!-- Filtro rápido por estado, con el total real de cada uno -->
    <div class="insp-chips" role="tablist" aria-label="Filtrar por estado">
      <button
        v-for="chip in chips"
        :key="chip.clave"
        type="button"
        role="tab"
        class="insp-chip"
        :class="[`chip-${chip.clave}`, { activo: chipActivo === chip.clave }]"
        :aria-selected="chipActivo === chip.clave"
        @click="seleccionarChip(chip.clave)"
      >
        <i class="ti" :class="chip.icono"></i>
        <span>{{ chip.texto }}</span>
        <strong>{{ chip.total }}</strong>
      </button>
    </div>

    <!-- Filtros -->
    <div class="insp-filtros">
      <label class="insp-campo insp-campo-ancho">
        <span>Parcela</span>
        <select v-model="filtroParcela">
          <option value="">Todas las parcelas</option>
          <option v-for="p in parcelas" :key="p.id" :value="p.id">
            {{ p.codigo_parcela }}{{ p.nombre_referencial ? ` — ${p.nombre_referencial}` : '' }}
          </option>
        </select>
      </label>

      <label class="insp-campo">
        <span>Tipo</span>
        <select v-model="filtroTipoInspeccion">
          <option value="">Todos</option>
          <option value="Cultivo">Cultivo</option>
          <option value="Pre_siembra">Pre siembra</option>
        </select>
      </label>

      <label class="insp-campo">
        <span>Desde</span>
        <input v-model="filtroFechaDesde" type="date" :max="filtroFechaHasta || undefined" />
      </label>

      <label class="insp-campo">
        <span>Hasta</span>
        <input v-model="filtroFechaHasta" type="date" :min="filtroFechaDesde || undefined" />
      </label>

      <button v-if="hayFiltros" class="btn btn-s insp-limpiar" type="button" @click="limpiarFiltros">
        <i class="ti ti-x"></i>
        Limpiar filtros
      </button>
    </div>

    <!-- Listado -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Cargando inspecciones...</span>
    </div>

    <div v-else-if="inspecciones.length" class="insp-grid">
      <InspeccionCard
        v-for="i in inspecciones"
        :key="i.id"
        :inspeccion="i"
        :saving="saving"
        :puede-gestionar="puedeGestionar"
        :puede-diagnosticar="puedeDiagnosticar"
        @ver="openView"
        @editar="openEdit"
        @estado="openEstado"
        @iniciar="iniciarVisita"
        @completar="completarVisita"
      />
    </div>

    <div v-else class="insp-vacio">
      <i class="ti" :class="hayFiltros ? 'ti-filter-off' : 'ti-clipboard-list'"></i>
      <h3>{{ hayFiltros ? 'No hay inspecciones con esos filtros' : 'Todavía no hay inspecciones' }}</h3>
      <p>
        {{
          hayFiltros
            ? 'Prueba quitando algún filtro para ver más resultados.'
            : 'Programa la primera visita técnica a una parcela para empezar.'
        }}
      </p>
      <button v-if="hayFiltros" class="btn btn-s" type="button" @click="limpiarFiltros">
        <i class="ti ti-x"></i>
        Limpiar filtros
      </button>
      <button v-else-if="puedeGestionar" class="btn btn-p" type="button" @click="openCreate">
        <i class="ti ti-plus"></i>
        Nueva inspección
      </button>
    </div>

    <PaginacionTabla
      class="insp-paginacion"
      :pagination="pagination"
      etiqueta="inspecciones"
      :disabled="loading"
      @change-page="cambiarPagina"
    />

    <InspeccionFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :inspeccion="selectedInspeccion"
      :parcelas="parcelas"
      :cultivos="cultivos"
      :etapas="etapas"
      :tecnicos="tecnicos"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <InspeccionViewModal v-if="showViewModal" :inspeccion="selectedInspeccion" @close="closeViewModal" />

    <InspeccionEstadoModal
      v-if="showEstadoModal"
      :inspeccion="selectedInspeccion"
      :saving="saving"
      :error="estadoError"
      @close="closeEstadoModal"
      @confirm="handleCambiarEstado"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';

import inspeccionService from '@/services/modules/inspeccionService';
import parcelaService from '@/services/modules/parcelaService';
import cultivoVariedadService from '@/services/modules/cultivoVariedadService';
import catalogoAgricolaService from '@/services/modules/catalogoAgricolaService';
import { obtenerMensajeError } from '@/services/errorService';
import usuarioService from '@/services/modules/usuarioService';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

import InspeccionCard from '@/components/inspecciones/InspeccionCard.vue';
import InspeccionFormModal from '@/components/inspecciones/InspeccionFormModal.vue';
import InspeccionViewModal from '@/components/inspecciones/InspeccionViewModal.vue';
import InspeccionEstadoModal from '@/components/inspecciones/InspeccionEstadoModal.vue';
import PaginacionTabla from '@/components/ui/PaginacionTabla.vue';

import '@/styles/inspecciones.css';
import { normalizeList, paginacionDe } from '@/utils/datos';

const inspecciones = ref([]);
const parcelas = ref([]);
const cultivos = ref([]);
const etapas = ref([]);
const tecnicos = ref([]);
const resumen = ref({ total: 0, por_estado: {}, atrasadas: 0 });

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const estadoError = ref('');

const pagination = ref(null);
const paginaActual = ref(1);

const filtroParcela = ref('');
const filtroTipoInspeccion = ref('');
const filtroEstado = ref('');
const filtroAtrasadas = ref(false);
const filtroFechaDesde = ref('');
const filtroFechaHasta = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showEstadoModal = ref(false);

const modalMode = ref('create');
const selectedInspeccion = ref(null);

const usuario = usuarioGuardado();
const puedeGestionar = tienePermiso(usuario, 'crear_inspecciones');
const puedeDiagnosticar = tienePermiso(usuario, 'crear_diagnosticos');

const chips = computed(() => {
  const por = resumen.value.por_estado || {};
  const lista = [
    { clave: 'todas', texto: 'Todas', icono: 'ti-list', total: resumen.value.total },
    { clave: 'Pendiente', texto: 'Pendientes', icono: 'ti-clock', total: por.Pendiente ?? 0 },
    { clave: 'En_proceso', texto: 'En proceso', icono: 'ti-player-play', total: por.En_proceso ?? 0 },
    { clave: 'Completada', texto: 'Completadas', icono: 'ti-circle-check', total: por.Completada ?? 0 },
    { clave: 'Validada', texto: 'Validadas', icono: 'ti-shield-check', total: por.Validada ?? 0 },
    { clave: 'No_realizada', texto: 'No realizadas', icono: 'ti-circle-x', total: por.No_realizada ?? 0 },
  ];

  // Las atrasadas solo aparecen cuando hay alguna que atender.
  if (resumen.value.atrasadas > 0) {
    lista.splice(2, 0, {
      clave: 'atrasadas',
      texto: 'Atrasadas',
      icono: 'ti-alert-triangle',
      total: resumen.value.atrasadas,
    });
  }

  return lista;
});

const chipActivo = computed(() => {
  if (filtroAtrasadas.value) return 'atrasadas';

  return filtroEstado.value || 'todas';
});

const hayFiltros = computed(() =>
  Boolean(
    filtroParcela.value ||
      filtroTipoInspeccion.value ||
      filtroEstado.value ||
      filtroAtrasadas.value ||
      filtroFechaDesde.value ||
      filtroFechaHasta.value
  )
);

onMounted(fetchAll);

// Los filtros se aplican solos al cambiar; no hace falta un botón "Filtrar".
watch(
  [filtroParcela, filtroTipoInspeccion, filtroEstado, filtroAtrasadas, filtroFechaDesde, filtroFechaHasta],
  aplicarFiltros
);

async function fetchAll() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchInspecciones(),
      fetchResumen(),
      fetchParcelas(),
      fetchCultivos(),
      fetchEtapas(),
      fetchTecnicosInspeccion(),
    ]);
  } finally {
    loading.value = false;
  }
}

// Evita que una respuesta lenta pise a otra más reciente al cambiar filtros rápido.
let ultimaPeticion = 0;

async function fetchInspecciones() {
  const peticion = ++ultimaPeticion;

  try {
    const params = {
      page: paginaActual.value,
      per_page: 12,
    };

    if (filtroParcela.value) params.id_parcela = filtroParcela.value;
    if (filtroTipoInspeccion.value) params.tipo_inspeccion = filtroTipoInspeccion.value;
    if (filtroEstado.value) params.estado = filtroEstado.value;
    if (filtroAtrasadas.value) params.atrasadas = 1;
    if (filtroFechaDesde.value) params.fecha_desde = filtroFechaDesde.value;
    if (filtroFechaHasta.value) params.fecha_hasta = filtroFechaHasta.value;

    const { data } = await inspeccionService.listar(params);

    if (peticion !== ultimaPeticion) return;

    inspecciones.value = normalizeList(data);
    pagination.value = paginacionDe(data);
  } catch (err) {
    if (peticion !== ultimaPeticion) return;

    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las inspecciones.');
    inspecciones.value = [];
    pagination.value = null;
  }
}

async function fetchResumen() {
  try {
    const { data } = await inspeccionService.resumen();

    resumen.value = data;
  } catch (err) {
    console.error(err);
  }
}

async function fetchParcelas() {
  try {
    const { data } = await parcelaService.listar({ todos: 1 });

    parcelas.value = normalizeList(data).filter((p) => p.estado !== 'Inactiva');
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas.');
    parcelas.value = [];
  }
}

async function fetchCultivos() {
  try {
    const { data } = await cultivoVariedadService.listar({ todos: 1 });

    cultivos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los cultivos.');
    cultivos.value = [];
  }
}

async function fetchEtapas() {
  try {
    const { data } = await catalogoAgricolaService.etapasFenologicas();

    etapas.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las etapas fenológicas.');
    etapas.value = [];
  }
}

async function fetchTecnicosInspeccion() {
  try {
    const { data } = await usuarioService.listarTecnicosInspeccion();

    tecnicos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los técnicos.');
    tecnicos.value = [];
  }
}

// Recarga el listado y los totales tras cualquier cambio.
function refrescar() {
  return Promise.all([fetchInspecciones(), fetchResumen()]);
}

async function aplicarFiltros() {
  paginaActual.value = 1;
  pageError.value = '';

  if (
    filtroFechaDesde.value &&
    filtroFechaHasta.value &&
    filtroFechaDesde.value > filtroFechaHasta.value
  ) {
    pageError.value = 'La fecha "desde" no puede ser posterior a la fecha "hasta".';
    return;
  }

  await fetchInspecciones();
}

function limpiarFiltros() {
  filtroParcela.value = '';
  filtroTipoInspeccion.value = '';
  filtroEstado.value = '';
  filtroAtrasadas.value = false;
  filtroFechaDesde.value = '';
  filtroFechaHasta.value = '';
}

function seleccionarChip(clave) {
  filtroAtrasadas.value = clave === 'atrasadas';
  filtroEstado.value = clave === 'todas' || clave === 'atrasadas' ? '' : clave;
}

async function cambiarPagina(pagina) {
  paginaActual.value = pagina;
  await fetchInspecciones();
}

function openCreate() {
  selectedInspeccion.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  showFormModal.value = true;
}

async function openEdit(inspeccion) {
  pageError.value = '';

  try {
    const { data } = await inspeccionService.obtener(inspeccion.id);
    selectedInspeccion.value = data;
    modalMode.value = 'edit';
    showFormModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar la inspección para editar.');
  }
}

async function openView(inspeccion) {
  pageError.value = '';

  try {
    const { data } = await inspeccionService.obtener(inspeccion.id);
    selectedInspeccion.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle de la inspección.');
  }
}

function openEstado(inspeccion) {
  selectedInspeccion.value = inspeccion;
  estadoError.value = '';
  showEstadoModal.value = true;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedInspeccion.value = null;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedInspeccion.value = null;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedInspeccion.value = null;
  estadoError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await inspeccionService.crear(payload);
    } else {
      await inspeccionService.actualizar(selectedInspeccion.value.id, payload);
    }

    await Promise.all([refrescar(), fetchCultivos()]);

    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar inspección.');
  } finally {
    saving.value = false;
  }
}

async function handleCambiarEstado(payload) {
  if (!selectedInspeccion.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await inspeccionService.cambiarEstado(selectedInspeccion.value.id, payload);
    await refrescar();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(err, 'Error al cambiar estado.');
  } finally {
    saving.value = false;
  }
}

// Acciones rápidas de la tarjeta: cambian el estado sin abrir ningún formulario.
async function cambiarEstadoRapido(inspeccion, estado, mensajeError) {
  saving.value = true;
  pageError.value = '';

  try {
    await inspeccionService.cambiarEstado(inspeccion.id, { estado });
    await refrescar();
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, mensajeError);
  } finally {
    saving.value = false;
  }
}

function iniciarVisita(inspeccion) {
  return cambiarEstadoRapido(inspeccion, 'En_proceso', 'No se pudo iniciar la visita.');
}

function completarVisita(inspeccion) {
  return cambiarEstadoRapido(inspeccion, 'Completada', 'No se pudo marcar la inspección como completada.');
}
</script>
