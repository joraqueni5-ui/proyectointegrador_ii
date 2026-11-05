<template>
  <div class="productores-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">Productores</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" @click="exportProductoresExcel">
          <i class="ti ti-file-spreadsheet"></i>
          Excel
        </button>

        <button class="btn btn-s" type="button" @click="exportProductoresPdf">
          <i class="ti ti-file-type-pdf"></i>
          PDF
        </button>
        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nuevo productor
        </button>
      </div>
    </div>

    <!-- Header -->
    <div class="pg-header productores-header">
      <div>
        <div class="pg-title">Productores</div>
        <div class="pg-sub">
          Administra el padrón de productores agrícolas y sus datos productivos.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="productores-error">
      {{ pageError }}
    </div>

    <!-- Stats -->
    <div v-if="!loading" class="productores-stats mb20">
      <div class="sc">
        <div class="slabel">Total productores</div>
        <div class="sval">{{ productores.length }}</div>
        <div class="ssub">
          <i class="ti ti-user-check up"></i>
          registrados
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Activos</div>
        <div class="sval">{{ productoresActivos }}</div>
        <div class="ssub">
          <i class="ti ti-check up"></i>
          en producción
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Comunidades</div>
        <div class="sval">{{ comunidadesUnicas }}</div>
        <div class="ssub">
          <i class="ti ti-building-community"></i>
          representadas
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Superficie total</div>
        <div class="sval">
          {{ superficieTotal.toFixed(1) }}
          <span class="productores-unit">ha</span>
        </div>
        <div class="ssub">
          <i class="ti ti-ruler-measure"></i>
          registradas
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="card productores-card">
      <div class="productores-toolbar">
        <div>
          <h3>Listado de productores</h3>
          <p>Filtra por comunidad, estado, sexo o búsqueda general.</p>
        </div>

        <div class="productores-filters">
          <select v-model="filtroEstado" class="productores-select">
            <option value="">Todos</option>
            <option value="true">Activos</option>
            <option value="false">Inactivos</option>
          </select>

          <select v-model="filtroComunidad" class="productores-select productores-select-lg">
            <option value="">Todas las comunidades</option>
            <option v-for="c in comunidades" :key="c.id" :value="c.id">
              {{ c.nombre_comunidad }}
            </option>
          </select>

          <select v-model="filtroSexo" class="productores-select">
            <option value="">Sexo: todos</option>
            <option value="M">Masculino</option>
            <option value="F">Femenino</option>
            <option value="Otro">Otro</option>
          </select>

          <div class="search-box productores-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar productor..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando productores...</span>
      </div>

      <div v-else class="productores-table-wrap">
        <table class="tbl productores-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Productor</th>
              <th>CI</th>
              <th>Teléfono</th>
              <th>Comunidad</th>
              <th>Sexo</th>
              <th>Superficie</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="p in filteredProductores" :key="p.id">
              <td class="productor-id">
                {{ p.id }}
              </td>

              <td>
                <div class="productor-name-cell">
                  <div
                    class="productor-avatar"
                    :class="{ inactive: !p.activo }"
                  >
                    {{ inicialProductor(p) }}
                  </div>

                  <div>
                    <strong>{{ nombreProductor(p) }}</strong>
                    <small>{{ p.direccion || 'Sin dirección registrada' }}</small>
                  </div>
                </div>
              </td>

              <td class="productor-ci">
                {{ ciCompleto(p) }}
              </td>

              <td>
                {{ p.telefono || '—' }}
              </td>

              <td>
                <span class="b bb">
                  {{ p.comunidad?.nombre_comunidad || '—' }}
                </span>
              </td>

              <td>
                <span v-if="p.sexo" class="b" :class="badgeSexo(p.sexo)">
                  {{ textoSexo(p.sexo) }}
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>
                <span v-if="p.superficie_total_ha" class="b bg">
                  {{ p.superficie_total_ha }} ha
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>
                <span class="b" :class="p.activo ? 'bg' : 'bgr'">
                  <i :class="p.activo ? 'ti ti-check' : 'ti ti-x'"></i>
                  {{ p.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>

              <td>
                <div class="productores-actions">
                  <button
                    class="productor-action view"
                    type="button"
                    title="Ver detalle"
                    :disabled="saving"
                    @click="openView(p)"
                  >
                    <i class="ti ti-eye"></i>
                  </button>

                  <button
                    class="productor-action parcelas"
                    type="button"
                    title="Ver parcelas"
                    :disabled="saving"
                    @click="openParcelas(p)"
                  >
                    <i class="ti ti-map-2"></i>
                  </button>

                  <button
                    class="productor-action edit"
                    type="button"
                    title="Editar"
                    :disabled="saving"
                    @click="openEdit(p)"
                  >
                    <i class="ti ti-pencil"></i>
                  </button>

                  <button
                    v-if="p.activo"
                    class="productor-action delete"
                    type="button"
                    title="Desactivar"
                    :disabled="saving"
                    @click="openDelete(p)"
                  >
                    <i class="ti ti-user-off"></i>
                  </button>

                  <button
                    v-else
                    class="productor-action activate"
                    type="button"
                    title="Activar"
                    :disabled="saving"
                    @click="handleActivate(p)"
                  >
                    <i class="ti ti-user-check"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredProductores.length === 0">
              <td colspan="9">
                <div class="productores-empty">
                  <i class="ti ti-users"></i>
                  <span>No se encontraron productores</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ProductorFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :productor="selectedProductor"
      :comunidades="comunidades"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <ProductorViewModal
      v-if="showViewModal"
      :productor="selectedProductor"
      @close="closeViewModal"
    />

    <ProductorParcelasModal
      v-if="showParcelasModal"
      :productor="selectedProductor"
      :parcelas="parcelas"
      :loading="loadingParcelas"
      @close="closeParcelasModal"
    />

    <ProductorDeleteModal
      v-if="showDeleteModal"
      :productor="selectedProductor"
      :saving="saving"
      :error="deleteError"
      @close="closeDeleteModal"
      @confirm="handleDelete"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import productorService from '@/services/modules/productorService';
import comunidadService from '@/services/modules/comunidadService';
import { obtenerMensajeError } from '@/services/errorService';

import ProductorFormModal from '@/components/productores/ProductorFormModal.vue';
import ProductorViewModal from '@/components/productores/ProductorViewModal.vue';
import ProductorParcelasModal from '@/components/productores/ProductorParcelasModal.vue';
import ProductorDeleteModal from '@/components/productores/ProductorDeleteModal.vue';

import * as XLSX from 'xlsx';
import { saveAs } from 'file-saver';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

import '@/styles/productores.css';
import { normalizeList } from '@/utils/datos';

const productores = ref([]);
const comunidades = ref([]);
const parcelas = ref([]);

const loading = ref(true);
const loadingParcelas = ref(false);
const saving = ref(false);

const search = ref('');
const filtroComunidad = ref('');
const filtroEstado = ref('');
const filtroSexo = ref('');

const pageError = ref('');
const formError = ref('');
const deleteError = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showParcelasModal = ref(false);
const showDeleteModal = ref(false);

const modalMode = ref('create');
const selectedProductor = ref(null);

const productoresActivos = computed(() => {
  return productores.value.filter((p) => Boolean(p.activo)).length;
});

const comunidadesUnicas = computed(() => {
  return new Set(productores.value.map((p) => p.id_comunidad).filter(Boolean)).size;
});

const superficieTotal = computed(() => {
  return productores.value.reduce((sum, p) => {
    return sum + Number(p.superficie_total_ha || 0);
  }, 0);
});

const filteredProductores = computed(() => {
  const q = search.value.trim().toLowerCase();

  return productores.value.filter((p) => {
    const texto = [
      nombreProductor(p),
      p.carnet_identidad,
      p.ci_extension,
      p.complemento_ci,
      p.telefono,
      p.direccion,
      p.comunidad?.nombre_comunidad,
      p.comunidad?.municipio,
      p.sexo,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);

    const matchComunidad =
      !filtroComunidad.value || Number(p.id_comunidad) === Number(filtroComunidad.value);

    const matchEstado =
      filtroEstado.value === ''
        ? true
        : Boolean(p.activo) === (filtroEstado.value === 'true');

    const matchSexo =
      !filtroSexo.value || p.sexo === filtroSexo.value;

    return matchSearch && matchComunidad && matchEstado && matchSexo;
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
      fetchProductores(),
      fetchComunidades(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchProductores() {
  try {
    const { data } = await productorService.listar({
      todos: 1,
    });

    productores.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los productores.');
    productores.value = [];
  }
}

async function fetchComunidades() {
  try {
    const { data } = await comunidadService.listar({
      todos: 1,
    });

    comunidades.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las comunidades.');
    comunidades.value = [];
  }
}

function openCreate() {
  selectedProductor.value = null;
  modalMode.value = 'create';
  showFormModal.value = true;
}

function openEdit(productor) {
  selectedProductor.value = productor;
  modalMode.value = 'edit';
  showFormModal.value = true;
}

async function openView(productor) {
  pageError.value = '';

  try {
    const { data } = await productorService.obtener(productor.id);
    selectedProductor.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle del productor.');
  }
}

function closeViewModal() {
  showViewModal.value = false;
  selectedProductor.value = null;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedProductor.value = null;
}

async function openParcelas(productor) {
  selectedProductor.value = productor;
  parcelas.value = [];
  showParcelasModal.value = true;
  loadingParcelas.value = true;

  try {
    const { data } = await productorService.obtener(productor.id);
    parcelas.value = data.parcelas || [];
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas del productor.');
  } finally {
    loadingParcelas.value = false;
  }
}

function closeParcelasModal() {
  showParcelasModal.value = false;
  selectedProductor.value = null;
  parcelas.value = [];
}

function openDelete(productor) {
  selectedProductor.value = productor;
  deleteError.value = '';
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedProductor.value = null;
  deleteError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await productorService.crear(payload);
    } else {
      await productorService.actualizar(selectedProductor.value.id, payload);
    }

    await fetchProductores();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar productor.');
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  if (!selectedProductor.value?.id) {
    return;
  }

  saving.value = true;
  deleteError.value = '';

  try {
    await productorService.eliminar(selectedProductor.value.id);
    await fetchProductores();
    closeDeleteModal();
  } catch (err) {
    console.error(err);
    deleteError.value = obtenerMensajeError(err, 'Error al desactivar productor.');
  } finally {
    saving.value = false;
  }
}

async function handleActivate(productor) {
  if (!productor?.id) {
    return;
  }

  saving.value = true;
  pageError.value = '';

  try {
    await productorService.activar(productor.id);
    await fetchProductores();
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'Error al activar productor.');
  } finally {
    saving.value = false;
  }
}

function nombreProductor(productor) {
  if (!productor) {
    return '—';
  }

  if (productor.nombre_completo) {
    return productor.nombre_completo;
  }

  return [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function inicialProductor(productor) {
  const nombre = nombreProductor(productor);

  return nombre && nombre !== '—'
    ? nombre.charAt(0).toUpperCase()
    : '?';
}

function ciCompleto(productor) {
  if (!productor?.carnet_identidad) {
    return '—';
  }

  const numero = productor.carnet_identidad;
  const complemento = productor.complemento_ci ? `-${productor.complemento_ci}` : '';
  const extension = productor.ci_extension ? ` ${productor.ci_extension}` : '';

  return `${numero}${complemento}${extension}`;
}

function textoSexo(sexo) {
  return {
    M: 'Masculino',
    F: 'Femenino',
    Otro: 'Otro',
  }[sexo] || '—';
}

function badgeSexo(sexo) {
  return {
    M: 'bb',
    F: 'ba',
    Otro: 'badge-gray',
  }[sexo] || 'badge-gray';
}

function productoresForReport() {
  return filteredProductores.value.map((p) => ({
    ID: p.id,
    Productor: nombreProductor(p),
    CI: ciCompleto(p),
    Teléfono: p.telefono || '—',
    Comunidad: p.comunidad?.nombre_comunidad || '—',
    Municipio: p.comunidad?.municipio || '—',
    Sexo: p.sexo ? textoSexo(p.sexo) : '—',
    Superficie: p.superficie_total_ha ? `${p.superficie_total_ha} ha` : '—',
    Estado: p.activo ? 'Activo' : 'Inactivo',
  }));
}

function getProductoresReportMetadata() {
  const user = currentUser.value || {};
  const nombre = [
    user.name,
    user.paterno,
    user.materno,
  ].filter(Boolean).join(' ') || 'Usuario no identificado';

  return {
    titulo: 'Reporte de Productores',
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    generadoPor: nombre,
    correo: user.email || '—',
    rol: user.rol?.nombre || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: filteredProductores.value.length,
    activos: filteredProductores.value.filter((p) => Boolean(p.activo)).length,
    inactivos: filteredProductores.value.filter((p) => !Boolean(p.activo)).length,
    superficie: filteredProductores.value.reduce((sum, p) => sum + Number(p.superficie_total_ha || 0), 0),
    filtroEstado: filtroEstado.value === ''
      ? 'Todos'
      : filtroEstado.value === 'true'
        ? 'Activos'
        : 'Inactivos',
    filtroComunidad: filtroComunidad.value
      ? comunidades.value.find((c) => Number(c.id) === Number(filtroComunidad.value))?.nombre_comunidad || '—'
      : 'Todas las comunidades',
    filtroSexo: filtroSexo.value
      ? textoSexo(filtroSexo.value)
      : 'Todos',
    busqueda: search.value || 'Sin búsqueda',
  };
}

function exportProductoresPdf() {
  const doc = new jsPDF('landscape');
  const meta = getProductoresReportMetadata();

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
  doc.text(`Total productores: ${meta.total}`, 160, 44);
  doc.text(`Superficie total: ${meta.superficie.toFixed(2)} ha`, 160, 50);

  doc.text(`Estado: ${meta.filtroEstado}`, 14, 58);
  doc.text(`Comunidad: ${meta.filtroComunidad}`, 90, 58);
  doc.text(`Sexo: ${meta.filtroSexo}`, 190, 58);
  doc.text(`Búsqueda: ${meta.busqueda}`, 14, 65);

  autoTable(doc, {
    startY: 72,
    head: [[
      'ID',
      'Productor',
      'CI',
      'Teléfono',
      'Comunidad',
      'Municipio',
      'Sexo',
      'Superficie',
      'Estado',
    ]],
    body: productoresForReport().map((p) => [
      p.ID,
      p.Productor,
      p.CI,
      p.Teléfono,
      p.Comunidad,
      p.Municipio,
      p.Sexo,
      p.Superficie,
      p.Estado,
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

  doc.save(`reporte_productores_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function exportProductoresExcel() {
  const rows = productoresForReport();
  const meta = getProductoresReportMetadata();

  const metadataRows = [
    ['Sistema', meta.sistema],
    ['Reporte', meta.titulo],
    ['Generado por', meta.generadoPor],
    ['Rol', meta.rol],
    ['Correo', meta.correo],
    ['Fecha y hora', meta.fecha],
    ['Total productores', meta.total],
    ['Productores activos', meta.activos],
    ['Productores inactivos', meta.inactivos],
    ['Superficie total', `${meta.superficie.toFixed(2)} ha`],
    ['Filtro estado', meta.filtroEstado],
    ['Filtro comunidad', meta.filtroComunidad],
    ['Filtro sexo', meta.filtroSexo],
    ['Búsqueda', meta.busqueda],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(metadataRows);

  XLSX.utils.sheet_add_json(worksheet, rows, {
    origin: `A${metadataRows.length + 1}`,
  });

  worksheet['!cols'] = [
    { wch: 8 },
    { wch: 34 },
    { wch: 18 },
    { wch: 16 },
    { wch: 28 },
    { wch: 22 },
    { wch: 14 },
    { wch: 16 },
    { wch: 12 },
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Productores');

  const buffer = XLSX.write(workbook, {
    bookType: 'xlsx',
    type: 'array',
  });

  const blob = new Blob([buffer], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  });

  saveAs(blob, `reporte_productores_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
</script>