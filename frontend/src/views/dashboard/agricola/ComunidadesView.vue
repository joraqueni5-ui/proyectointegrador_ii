<template>
  <div class="comunidades-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">Comunidades</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" @click="exportComunidadesExcel">
          <i class="ti ti-file-spreadsheet"></i>
          Excel
        </button>

        <button class="btn btn-s" type="button" @click="exportComunidadesPdf">
          <i class="ti ti-file-type-pdf"></i>
          PDF
        </button>
        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nueva comunidad
        </button>
      </div>
    </div>

    <!-- Header -->
    <div class="pg-header comunidades-header">
      <div>
        <div class="pg-title">Comunidades</div>
        <div class="pg-sub">
          Administra comunidades agrícolas, ubicación territorial y productores asociados.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchComunidades">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <!-- Error general -->
    <div v-if="pageError" class="comunidades-error">
      {{ pageError }}
    </div>

    <!-- Stats -->
    <div v-if="!loading" class="comunidades-stats mb20">
      <div class="sc">
        <div class="slabel">Total comunidades</div>
        <div class="sval">{{ comunidades.length }}</div>
        <div class="ssub">
          <i class="ti ti-building-community up"></i>
          registradas
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Activas</div>
        <div class="sval">{{ comunidadesActivas }}</div>
        <div class="ssub">
          <i class="ti ti-check up"></i>
          en operación
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Total productores</div>
        <div class="sval">{{ totalProductores }}</div>
        <div class="ssub">
          <i class="ti ti-users"></i>
          vinculados
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Con GPS</div>
        <div class="sval">{{ comunidadesConGps }}</div>
        <div class="ssub">
          <i class="ti ti-map-pin"></i>
          georreferenciadas
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card comunidades-card">
      <div class="comunidades-toolbar">
        <div>
          <h3>Listado de comunidades</h3>
          <p>Filtra comunidades por estado, departamento, GPS o búsqueda general.</p>
        </div>

        <div class="comunidades-filters">
          <select v-model="filtroEstado" class="comunidades-select">
            <option value="">Todas</option>
            <option value="true">Activas</option>
            <option value="false">Inactivas</option>
          </select>

          <select v-model="filtroDepartamento" class="comunidades-select">
            <option value="">Todos los departamentos</option>
            <option value="La Paz">La Paz</option>
            <option value="Cochabamba">Cochabamba</option>
            <option value="Potosí">Potosí</option>
            <option value="Oruro">Oruro</option>
            <option value="Santa Cruz">Santa Cruz</option>
            <option value="Tarija">Tarija</option>
            <option value="Beni">Beni</option>
            <option value="Pando">Pando</option>
            <option value="Chuquisaca">Chuquisaca</option>
          </select>

          <select v-model="filtroGps" class="comunidades-select">
            <option value="">GPS: todos</option>
            <option value="true">Con GPS</option>
            <option value="false">Sin GPS</option>
          </select>

          <div class="search-box comunidades-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar comunidad..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando comunidades...</span>
      </div>

      <div v-else class="comunidades-table-wrap">
        <table class="tbl comunidades-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Comunidad</th>
              <th>Municipio</th>
              <th>Provincia</th>
              <th>Departamento</th>
              <th>Altitud</th>
              <th>Productores</th>
              <th>GPS</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="c in filteredComunidades" :key="c.id">
              <td class="comunidad-id">
                {{ c.id }}
              </td>

              <td>
                <div class="comunidad-name-cell">
                  <div class="comunidad-avatar">
                    {{ c.nombre_comunidad?.charAt(0)?.toUpperCase() || 'C' }}
                  </div>

                  <div>
                    <strong>{{ c.nombre_comunidad }}</strong>
                    <small>{{ c.activo ? 'Comunidad activa' : 'Comunidad inactiva' }}</small>
                  </div>
                </div>
              </td>

              <td>{{ c.municipio || '—' }}</td>
              <td>{{ c.provincia || '—' }}</td>
              <td>{{ c.departamento || '—' }}</td>

              <td>
                <span v-if="c.altitud_msnm" class="b bb">
                  {{ Number(c.altitud_msnm).toLocaleString('es-BO') }} m
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>
                <span class="b bg">
                  {{ c.productores_count ?? 0 }} productores
                </span>
              </td>

              <td>
                <span class="b" :class="tieneGps(c) ? 'bg' : 'badge-gray'">
                  <i :class="tieneGps(c) ? 'ti ti-map-pin' : 'ti ti-map-off'"></i>
                  {{ tieneGps(c) ? 'Sí' : 'No' }}
                </span>
              </td>

              <td>
                <span class="b" :class="c.activo ? 'bg' : 'bgr'">
                  <i :class="c.activo ? 'ti ti-check' : 'ti ti-x'"></i>
                  {{ c.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>

              <td>
                <div class="comunidades-actions">
                  <button
                    class="comunidad-action view"
                    type="button"
                    title="Ver detalle"
                    :disabled="saving"
                    @click="openView(c)"
                  >
                    <i class="ti ti-eye"></i>
                  </button>

                  <button
                    class="comunidad-action edit"
                    type="button"
                    title="Editar"
                    :disabled="saving"
                    @click="openEdit(c)"
                  >
                    <i class="ti ti-pencil"></i>
                  </button>

                  <button
                    v-if="c.activo"
                    class="comunidad-action delete"
                    type="button"
                    title="Desactivar"
                    :disabled="saving"
                    @click="openDelete(c)"
                  >
                    <i class="ti ti-building-community"></i>
                  </button>

                  <button
                    v-else
                    class="comunidad-action activate"
                    type="button"
                    title="Activar"
                    :disabled="saving"
                    @click="handleActivate(c)"
                  >
                    <i class="ti ti-circle-check"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredComunidades.length === 0">
              <td colspan="10">
                <div class="comunidades-empty">
                  <i class="ti ti-building-community"></i>
                  <span>No se encontraron comunidades</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ComunidadFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :comunidad="selectedComunidad"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <ComunidadViewModal
      v-if="showViewModal"
      :comunidad="selectedComunidad"
      @close="closeViewModal"
    />

    <ComunidadDeleteModal
      v-if="showDeleteModal"
      :comunidad="selectedComunidad"
      :saving="saving"
      :error="deleteError"
      @close="closeDeleteModal"
      @confirm="handleDelete"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import comunidadService from '@/services/modules/comunidadService';
import { obtenerMensajeError } from '@/services/errorService';
import ComunidadFormModal from '@/components/comunidades/ComunidadFormModal.vue';
import ComunidadViewModal from '@/components/comunidades/ComunidadViewModal.vue';
import ComunidadDeleteModal from '@/components/comunidades/ComunidadDeleteModal.vue';
import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import '@/styles/comunidades.css';
import { normalizeList } from '@/utils/datos';

const comunidades = ref([]);
const loading = ref(true);
const saving = ref(false);

const search = ref('');
const filtroEstado = ref('');
const filtroDepartamento = ref('');
const filtroGps = ref('');

const pageError = ref('');
const formError = ref('');
const deleteError = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showDeleteModal = ref(false);

const modalMode = ref('create');
const selectedComunidad = ref(null);

const comunidadesActivas = computed(() => {
  return comunidades.value.filter((c) => Boolean(c.activo)).length;
});

const comunidadesConGps = computed(() => {
  return comunidades.value.filter((c) => tieneGps(c)).length;
});

const totalProductores = computed(() => {
  return comunidades.value.reduce((sum, c) => sum + (c.productores_count ?? 0), 0);
});

const filteredComunidades = computed(() => {
  const q = search.value.trim().toLowerCase();

  return comunidades.value.filter((c) => {
    const texto = [
      c.nombre_comunidad,
      c.municipio,
      c.provincia,
      c.departamento,
      c.altitud_msnm,
      c.latitud,
      c.longitud,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);

    const matchEstado =
      filtroEstado.value === ''
        ? true
        : Boolean(c.activo) === (filtroEstado.value === 'true');

    const matchDepartamento =
      !filtroDepartamento.value || c.departamento === filtroDepartamento.value;

    const matchGps =
      filtroGps.value === ''
        ? true
        : tieneGps(c) === (filtroGps.value === 'true');

    return matchSearch && matchEstado && matchDepartamento && matchGps;
  });
});

const currentUser = computed(() => {
  try {
    return JSON.parse(localStorage.getItem('user_data') || '{}');
  } catch {
    return {};
  }
});

onMounted(fetchComunidades);

async function fetchComunidades() {
  loading.value = true;
  pageError.value = '';

  try {
    const { data } = await comunidadService.listar({ todos: 1 });
    comunidades.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las comunidades.');
    comunidades.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  selectedComunidad.value = null;
  modalMode.value = 'create';
  showFormModal.value = true;
}

function openEdit(comunidad) {
  selectedComunidad.value = comunidad;
  modalMode.value = 'edit';
  showFormModal.value = true;
}

async function openView(comunidad) {
  pageError.value = '';

  try {
    const { data } = await comunidadService.obtener(comunidad.id);
    selectedComunidad.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle de la comunidad.');
  }
}

function closeViewModal() {
  showViewModal.value = false;
  selectedComunidad.value = null;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedComunidad.value = null;
}

function openDelete(comunidad) {
  selectedComunidad.value = comunidad;
  deleteError.value = '';
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedComunidad.value = null;
  deleteError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await comunidadService.crear(payload);
    } else {
      await comunidadService.actualizar(selectedComunidad.value.id, payload);
    }

    await fetchComunidades();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar comunidad.');
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  if (!selectedComunidad.value?.id) {
    return;
  }

  saving.value = true;
  deleteError.value = '';

  try {
    await comunidadService.eliminar(selectedComunidad.value.id);
    await fetchComunidades();
    closeDeleteModal();
  } catch (err) {
    console.error(err);
    deleteError.value = obtenerMensajeError(err, 'Error al desactivar comunidad.');
  } finally {
    saving.value = false;
  }
}

async function handleActivate(comunidad) {
  if (!comunidad?.id) {
    return;
  }

  saving.value = true;
  pageError.value = '';

  try {
    await comunidadService.activar(comunidad.id);
    await fetchComunidades();
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'Error al activar comunidad.');
  } finally {
    saving.value = false;
  }
}

function tieneGps(comunidad) {
  return comunidad?.latitud !== null &&
    comunidad?.latitud !== undefined &&
    comunidad?.latitud !== '' &&
    comunidad?.longitud !== null &&
    comunidad?.longitud !== undefined &&
    comunidad?.longitud !== '';
}

function comunidadesForReport() {
  return filteredComunidades.value.map((c) => ({
    ID: c.id,
    Comunidad: c.nombre_comunidad || '—',
    Municipio: c.municipio || '—',
    Provincia: c.provincia || '—',
    Departamento: c.departamento || '—',
    Altitud: c.altitud_msnm ? `${Number(c.altitud_msnm).toLocaleString('es-BO')} m` : '—',
    Productores: c.productores_count ?? 0,
    GPS: tieneGps(c) ? 'Sí' : 'No',
    Estado: c.activo ? 'Activa' : 'Inactiva',
  }));
}

function getComunidadesReportMetadata() {
  const user = currentUser.value || {};
  const nombre = [
    user.name,
    user.paterno,
    user.materno,
  ].filter(Boolean).join(' ') || 'Usuario no identificado';

  return {
    titulo: 'Reporte de Comunidades',
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    generadoPor: nombre,
    correo: user.email || '—',
    rol: user.rol?.nombre || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: filteredComunidades.value.length,
    activas: filteredComunidades.value.filter((c) => Boolean(c.activo)).length,
    inactivas: filteredComunidades.value.filter((c) => !Boolean(c.activo)).length,
    conGps: filteredComunidades.value.filter((c) => tieneGps(c)).length,
    totalProductores: filteredComunidades.value.reduce((sum, c) => sum + (c.productores_count ?? 0), 0),
    filtroEstado: filtroEstado.value === ''
      ? 'Todas'
      : filtroEstado.value === 'true'
        ? 'Activas'
        : 'Inactivas',
    filtroDepartamento: filtroDepartamento.value || 'Todos los departamentos',
    filtroGps: filtroGps.value === ''
      ? 'Todos'
      : filtroGps.value === 'true'
        ? 'Con GPS'
        : 'Sin GPS',
    busqueda: search.value || 'Sin búsqueda',
  };
}

function exportComunidadesPdf() {
  const doc = new jsPDF('landscape');
  const meta = getComunidadesReportMetadata();

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
  doc.text(`Total comunidades: ${meta.total}`, 160, 44);
  doc.text(`Productores vinculados: ${meta.totalProductores}`, 160, 50);

  doc.text(`Estado: ${meta.filtroEstado}`, 14, 58);
  doc.text(`Departamento: ${meta.filtroDepartamento}`, 90, 58);
  doc.text(`GPS: ${meta.filtroGps}`, 190, 58);
  doc.text(`Búsqueda: ${meta.busqueda}`, 14, 65);

  autoTable(doc, {
    startY: 72,
    head: [[
      'ID',
      'Comunidad',
      'Municipio',
      'Provincia',
      'Departamento',
      'Altitud',
      'Productores',
      'GPS',
      'Estado',
    ]],
    body: comunidadesForReport().map((c) => [
      c.ID,
      c.Comunidad,
      c.Municipio,
      c.Provincia,
      c.Departamento,
      c.Altitud,
      c.Productores,
      c.GPS,
      c.Estado,
    ]),
    theme: 'grid',
    styles: {
      fontSize: 8,
      cellPadding: 2.5,
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

  doc.save(`reporte_comunidades_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function exportComunidadesExcel() {
  const rows = comunidadesForReport();
  const meta = getComunidadesReportMetadata();

  const metadataRows = [
    ['Sistema', meta.sistema],
    ['Reporte', meta.titulo],
    ['Generado por', meta.generadoPor],
    ['Rol', meta.rol],
    ['Correo', meta.correo],
    ['Fecha y hora', meta.fecha],
    ['Total comunidades', meta.total],
    ['Comunidades activas', meta.activas],
    ['Comunidades inactivas', meta.inactivas],
    ['Comunidades con GPS', meta.conGps],
    ['Productores vinculados', meta.totalProductores],
    ['Filtro estado', meta.filtroEstado],
    ['Filtro departamento', meta.filtroDepartamento],
    ['Filtro GPS', meta.filtroGps],
    ['Búsqueda', meta.busqueda],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(metadataRows);

  XLSX.utils.sheet_add_json(worksheet, rows, {
    origin: `A${metadataRows.length + 1}`,
  });

  worksheet['!cols'] = [
    { wch: 8 },
    { wch: 28 },
    { wch: 22 },
    { wch: 22 },
    { wch: 18 },
    { wch: 14 },
    { wch: 14 },
    { wch: 10 },
    { wch: 12 },
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Comunidades');

  const buffer = XLSX.write(workbook, {
    bookType: 'xlsx',
    type: 'array',
  });

  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });

  saveAs(blob, `reporte_comunidades_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
</script>