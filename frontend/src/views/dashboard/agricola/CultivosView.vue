<template>
  <div class="cultivos-page fade-in">
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">Cultivos y variedades</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" @click="exportCultivosExcel">
          <i class="ti ti-file-spreadsheet"></i>
          Excel
        </button>

        <button class="btn btn-s" type="button" @click="exportCultivosPdf">
          <i class="ti ti-file-type-pdf"></i>
          PDF
        </button>
        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nuevo cultivo
        </button>
      </div>
    </div>

    <div class="pg-header cultivos-header">
      <div>
        <div class="pg-title">Cultivos y variedades</div>
        <div class="pg-sub">
          Administra variedades sembradas por parcela, fechas de siembra y estado productivo.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="cultivos-error">
      {{ pageError }}
    </div>

    <div v-if="!loading" class="cultivos-stats mb20">
      <div class="sc">
        <div class="slabel">Total cultivos</div>
        <div class="sval">{{ cultivos.length }}</div>
        <div class="ssub">
          <i class="ti ti-seeding up"></i>
          registrados
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Activos</div>
        <div class="sval">{{ totalActivos }}</div>
        <div class="ssub">
          <i class="ti ti-check up"></i>
          en producción
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Parcelas vinculadas</div>
        <div class="sval">{{ parcelasVinculadas }}</div>
        <div class="ssub">
          <i class="ti ti-map-2"></i>
          con cultivo
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Variedades únicas</div>
        <div class="sval">{{ variedadesUnicas }}</div>
        <div class="ssub">
          <i class="ti ti-plant-2"></i>
          registradas
        </div>
      </div>
    </div>

    <div class="card cultivos-card">
      <div class="cultivos-toolbar">
        <div>
          <h3>Listado visual de cultivos</h3>
          <p>Filtra por parcela, estado o búsqueda general.</p>
        </div>

        <div class="cultivos-filters">
          <select v-model="filtroEstado" class="cultivos-select">
            <option value="">Todos los estados</option>
            <option value="Activo">Activo</option>
            <option value="Cosechado">Cosechado</option>
            <option value="Perdido">Perdido</option>
            <option value="Abandonado">Abandonado</option>
          </select>

          <select v-model="filtroParcela" class="cultivos-select cultivos-select-lg">
            <option value="">Todas las parcelas</option>
            <option v-for="p in parcelas" :key="p.id" :value="p.id">
              {{ p.codigo_parcela }}{{ p.nombre_referencial ? ` — ${p.nombre_referencial}` : '' }}
            </option>
          </select>

          <div class="search-box cultivos-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar variedad, parcela o productor..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando cultivos...</span>
      </div>

      <div v-else-if="filteredCultivos.length === 0" class="cultivos-empty">
        <i class="ti ti-seeding"></i>
        <span>No se encontraron cultivos</span>
      </div>

      <div v-else class="cultivos-grid">
        <CultivoCard
          v-for="cultivo in filteredCultivos"
          :key="cultivo.id"
          :cultivo="cultivo"
          @view="openView"
          @edit="openEdit"
          @status="openEstado"
        />
      </div>
    </div>

    <CultivoFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :cultivo="selectedCultivo"
      :parcelas="parcelas"
      :etapas="etapas"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />
    <CultivoViewModal
      v-if="showViewModal"
      :cultivo="selectedCultivo"
      @close="closeViewModal"
    />

    <CultivoEstadoModal
      v-if="showEstadoModal"
      :cultivo="selectedCultivo"
      :saving="saving"
      :error="estadoError"
      @close="closeEstadoModal"
      @confirm="handleCambiarEstado"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import cultivoVariedadService from '@/services/modules/cultivoVariedadService';
import parcelaService from '@/services/modules/parcelaService';
import { obtenerMensajeError } from '@/services/errorService';

import CultivoCard from '@/components/cultivos/CultivoCard.vue';
import CultivoFormModal from '@/components/cultivos/CultivoFormModal.vue';
import CultivoViewModal from '@/components/cultivos/CultivoViewModal.vue';
import CultivoEstadoModal from '@/components/cultivos/CultivoEstadoModal.vue';

import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

import '@/styles/cultivos.css';
import { normalizeList } from '@/utils/datos';

const cultivos = ref([]);
const parcelas = ref([]);
const etapas = ref([]);

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const estadoError = ref('');
const formError = ref('');;

const search = ref('');
const filtroEstado = ref('');
const filtroParcela = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showEstadoModal = ref(false);

const modalMode = ref('create');
const selectedCultivo = ref(null);

const totalActivos = computed(() => {
  return cultivos.value.filter((c) => c.estado === 'Activo').length;
});

const parcelasVinculadas = computed(() => {
  return new Set(
    cultivos.value
      .map((c) => c.id_parcela || c.parcela?.id)
      .filter(Boolean)
  ).size;
});

const variedadesUnicas = computed(() => {
  return new Set(
    cultivos.value
      .map((c) => String(c.variedad_papa || '').trim().toLowerCase())
      .filter(Boolean)
  ).size;
});

const filteredCultivos = computed(() => {
  const q = search.value.trim().toLowerCase();

  return cultivos.value.filter((c) => {
    const productor = c.parcela?.productor;

    const nombreProductor = productor?.nombre_completo || [
      productor?.nombres,
      productor?.apellido_paterno,
      productor?.apellido_materno,
    ]
      .filter(Boolean)
      .join(' ');

    const etapa = c.etapa_fenologica?.nombre || c.etapaFenologica?.nombre || '';

    const texto = [
      c.variedad_papa,
      c.procedencia_semilla,
      c.estado,
      c.parcela?.codigo_parcela,
      c.parcela?.nombre_referencial,
      nombreProductor,
      productor?.carnet_identidad,
      etapa,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);

    const matchEstado =
      !filtroEstado.value || c.estado === filtroEstado.value;

    const parcelaId = c.id_parcela || c.parcela?.id;

    const matchParcela =
      !filtroParcela.value || Number(parcelaId) === Number(filtroParcela.value);

    return matchSearch && matchEstado && matchParcela;
  });
});

const currentUser = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user_data') || '{}');
  } catch {
    return {};
  }
});

onMounted(fetchAll);

async function fetchAll() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchCultivos(),
      fetchParcelas(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchCultivos() {
  try {
    const { data } = await cultivoVariedadService.listar({
      todos: 1,
    });

    cultivos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los cultivos.');
    cultivos.value = [];
  }
}

async function fetchParcelas() {
  try {
    const { data } = await parcelaService.listar({
      todos: 1,
    });

    parcelas.value = normalizeList(data)
      .filter((p) => p.estado === 'Activa');
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas.');
    parcelas.value = [];
  }
}

function openCreate() {
  selectedCultivo.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  formError.value = '';
  showFormModal.value = true;
}

async function openEdit(cultivo) {
  pageError.value = '';
  formError.value = '';

  try {
    const { data } = await cultivoVariedadService.obtener(cultivo.id);
    selectedCultivo.value = data;
    modalMode.value = 'edit';
    showFormModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el cultivo para editar.');
  }
}

async function openView(cultivo) {
  pageError.value = '';

  try {
    const { data } = await cultivoVariedadService.obtener(cultivo.id);
    selectedCultivo.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle del cultivo.');
  }
}

function openEstado(cultivo) {
  selectedCultivo.value = cultivo;
  estadoError.value = '';
  showEstadoModal.value = true;
}

function closeFormModal() {
  showFormModal.value = false;
  selectedCultivo.value = null;
  formError.value = '';
}

function closeViewModal() {
  showViewModal.value = false;
  selectedCultivo.value = null;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedCultivo.value = null;
  estadoError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  pageError.value = '';
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      const cultivoActivo = buscarCultivoActivoPorParcela(payload.id_parcela);

      if (cultivoActivo) {
        formError.value = `Esta parcela ya tiene un cultivo activo (${cultivoActivo.variedad_papa || 'sin variedad'}). Para registrar un nuevo cultivo, primero debes cambiar el estado del cultivo actual a Cosechado, Perdido o Abandonado.`;
        return;
      }

      await cultivoVariedadService.crear(payload);
    } else {
      await cultivoVariedadService.actualizar(selectedCultivo.value.id, payload);
    }

    await fetchCultivos();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeErrorFormulario(
      err,
      'No se pudo guardar el cultivo.'
    );
  } finally {
    saving.value = false;
  }
}

function buscarCultivoActivoPorParcela(idParcela) {
  if (!idParcela) {
    return null;
  }

  return cultivos.value.find((cultivo) => {
    const parcelaId = cultivo.id_parcela || cultivo.parcela?.id;

    return Number(parcelaId) === Number(idParcela) &&
      cultivo.estado === 'Activo';
  }) || null;
}

function obtenerMensajeErrorFormulario(err, fallback) {
  const data = err?.response?.data;

  if (data?.message) {
    return data.message;
  }

  if (data?.error) {
    return data.error;
  }

  const errores = data?.errors;

  if (errores && typeof errores === 'object') {
    const primerError = Object.values(errores)
      .flat()
      .filter(Boolean)[0];

    if (primerError) {
      return primerError;
    }
  }

  return fallback;
}

async function handleCambiarEstado(estado) {
  if (!selectedCultivo.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await cultivoVariedadService.cambiarEstado(selectedCultivo.value.id, estado);
    await fetchCultivos();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(err, 'Error al cambiar estado del cultivo.');
  } finally {
    saving.value = false;
  }
}

function nombreProductorCultivo(cultivo) {
  const productor = cultivo?.parcela?.productor;

  if (!productor) {
    return '—';
  }

  return productor.nombre_completo || [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ].filter(Boolean).join(' ') || '—';
}

function formatDateReport(dateStr) {
  if (!dateStr) {
    return '—';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return dateStr;
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function cultivosForReport() {
  return filteredCultivos.value.map((c) => ({
    ID: c.id,
    Variedad: c.variedad_papa || '—',
    Parcela: c.parcela?.codigo_parcela || '—',
    Productor: nombreProductorCultivo(c),
    Siembra: formatDateReport(c.fecha_siembra),
    'Cosecha estimada': formatDateReport(c.fecha_estimada_cosecha),
    Semilla: c.cantidad_semilla_kg ? `${Number(c.cantidad_semilla_kg).toFixed(2)} kg` : '—',
    Procedencia: c.procedencia_semilla || '—',
    Etapa: c.etapa_fenologica?.nombre || c.etapaFenologica?.nombre || '—',
    Estado: c.estado || '—',
  }));
}

function getCultivosReportMetadata() {
  const user = currentUser.value || {};
  const nombre = [
    user.name,
    user.paterno,
    user.materno,
  ].filter(Boolean).join(' ') || 'Usuario no identificado';

  const activos = filteredCultivos.value.filter((c) => c.estado === 'Activo').length;
  const cosechados = filteredCultivos.value.filter((c) => c.estado === 'Cosechado').length;
  const perdidos = filteredCultivos.value.filter((c) => c.estado === 'Perdido').length;
  const abandonados = filteredCultivos.value.filter((c) => c.estado === 'Abandonado').length;

  return {
    titulo: 'Reporte de Cultivos y Variedades',
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    generadoPor: nombre,
    correo: user.email || '—',
    rol: user.rol?.nombre || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: filteredCultivos.value.length,
    activos,
    cosechados,
    perdidos,
    abandonados,
    filtroEstado: filtroEstado.value || 'Todos los estados',
    filtroParcela: filtroParcela.value
      ? parcelas.value.find((p) => Number(p.id) === Number(filtroParcela.value))?.codigo_parcela || '—'
      : 'Todas las parcelas',
    busqueda: search.value || 'Sin búsqueda',
  };
}

function exportCultivosPdf() {
  const doc = new jsPDF('landscape');
  const meta = getCultivosReportMetadata();

  doc.setFillColor(22, 101, 52);
  doc.rect(0, 0, 297, 28, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFontSize(16);
  doc.setFont('helvetica', 'bold');
  doc.text(meta.sistema, 14, 10);

  doc.setFontSize(12);
  doc.setFont('helvetica', 'normal');
  doc.text(meta.titulo, 14, 19);

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(10);

  doc.text(`Generado por: ${meta.generadoPor}`, 14, 38);
  doc.text(`Rol: ${meta.rol}`, 14, 44);
  doc.text(`Correo: ${meta.correo}`, 14, 50);

  doc.text(`Fecha y hora: ${meta.fecha}`, 160, 38);
  doc.text(`Total cultivos: ${meta.total}`, 160, 44);
  doc.text(`Activos: ${meta.activos} | Cosechados: ${meta.cosechados}`, 160, 50);

  doc.text(`Estado: ${meta.filtroEstado}`, 14, 58);
  doc.text(`Parcela: ${meta.filtroParcela}`, 90, 58);
  doc.text(`Búsqueda: ${meta.busqueda}`, 14, 65);

  autoTable(doc, {
    startY: 72,
    head: [[
      'ID',
      'Variedad',
      'Parcela',
      'Productor',
      'Siembra',
      'Cosecha estimada',
      'Semilla',
      'Procedencia',
      'Etapa',
      'Estado',
    ]],
    body: cultivosForReport().map((c) => [
      c.ID,
      c.Variedad,
      c.Parcela,
      c.Productor,
      c.Siembra,
      c['Cosecha estimada'],
      c.Semilla,
      c.Procedencia,
      c.Etapa,
      c.Estado,
    ]),
    theme: 'grid',
    styles: {
      fontSize: 7.5,
      cellPadding: 2,
      textColor: [30, 41, 59],
      lineColor: [203, 213, 225],
      lineWidth: 0.2,
    },
    headStyles: {
      fillColor: [22, 101, 52],
      textColor: [255, 255, 255],
      fontStyle: 'bold',
    },
    alternateRowStyles: {
      fillColor: [248, 250, 252],
    },
    didDrawPage: function () {
      const pageCount = doc.internal.getNumberOfPages();
      const pageSize = doc.internal.pageSize;
      const pageWidth = pageSize.width || pageSize.getWidth();
      const pageHeight = pageSize.height || pageSize.getHeight();

      doc.setFontSize(8);
      doc.setTextColor(100, 116, 139);
      doc.text(
        'Documento generado automáticamente por el Sistema PROINPA',
        14,
        pageHeight - 10
      );
      doc.text(
        `Página ${doc.internal.getCurrentPageInfo().pageNumber} de ${pageCount}`,
        pageWidth - 40,
        pageHeight - 10
      );
    },
  });

  doc.save(`reporte_cultivos_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function exportCultivosExcel() {
  const rows = cultivosForReport();
  const meta = getCultivosReportMetadata();

  const metadataRows = [
    ['Sistema', meta.sistema],
    ['Reporte', meta.titulo],
    ['Generado por', meta.generadoPor],
    ['Rol', meta.rol],
    ['Correo', meta.correo],
    ['Fecha y hora', meta.fecha],
    ['Total cultivos', meta.total],
    ['Activos', meta.activos],
    ['Cosechados', meta.cosechados],
    ['Perdidos', meta.perdidos],
    ['Abandonados', meta.abandonados],
    ['Filtro estado', meta.filtroEstado],
    ['Filtro parcela', meta.filtroParcela],
    ['Búsqueda', meta.busqueda],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(metadataRows);

  XLSX.utils.sheet_add_json(worksheet, rows, {
    origin: `A${metadataRows.length + 1}`,
  });

  worksheet['!cols'] = [
    { wch: 8 },
    { wch: 24 },
    { wch: 18 },
    { wch: 32 },
    { wch: 16 },
    { wch: 18 },
    { wch: 14 },
    { wch: 22 },
    { wch: 22 },
    { wch: 14 },
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Cultivos');

  const buffer = XLSX.write(workbook, {
    bookType: 'xlsx',
    type: 'array',
  });

  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });

  saveAs(blob, `reporte_cultivos_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
</script>