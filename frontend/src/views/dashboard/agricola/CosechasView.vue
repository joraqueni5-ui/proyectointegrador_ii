<template>
  <div class="cosechas-page fade-in">
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">{{ trashMode ? 'Papelera de cosechas' : 'Cosechas' }}</span>
      </div>

      <div class="cbar-right">
        <button
          class="btn"
          :class="trashMode ? 'btn-s' : 'btn-d'"
          type="button"
          :disabled="loading"
          @click="toggleTrashMode"
        >
          <i :class="trashMode ? 'ti ti-arrow-left' : 'ti ti-trash'"></i>
          {{ trashMode ? 'Volver a cosechas' : 'Papelera' }}
        </button>

        <button
          v-if="!trashMode"
          class="btn btn-p"
          type="button"
          @click="openCreate"
        >
          <i class="ti ti-plus"></i>
          Registrar cosecha
        </button>
      </div>
    </div>

    <div class="pg-header cosechas-header">
      <div>
        <div class="pg-title">
          {{ trashMode ? 'Papelera de cosechas' : 'Control de Producción' }}
        </div>
        <div class="pg-sub">
          {{ trashMode
            ? 'Cosechas eliminadas lógicamente que pueden ser restauradas.'
            : 'Registro de cosechas por parcela, variedad y rendimiento productivo.'
          }}
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="cosechas-error">
      {{ pageError }}
    </div>

    <div v-if="trashMode" class="cosechas-trash-info">
      <i class="ti ti-info-circle"></i>
      <span>
        Las cosechas en papelera no aparecen en el listado principal. Puedes restaurarlas si fueron eliminadas por error.
      </span>
    </div>

    <div v-if="!loading && !trashMode" class="cosechas-stats mb20">
      <div class="cosecha-kpi-main">
        <div class="cosecha-kpi-label">Producción total campaña</div>
        <div class="cosecha-kpi-value">
          {{ formatNumber(totalToneladas, 1) }} t
        </div>
        <div class="cosecha-kpi-help">
          {{ cosechas.length }} cosechas registradas
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Rendimiento promedio</div>
        <div class="sval">
          {{ rendimientoPromedio }}
          <span class="cosecha-unit">t/ha</span>
        </div>
        <div class="ssub">
          <i class="ti ti-trending-up up"></i>
          promedio calculado
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Ingreso estimado</div>
        <div class="sval cosecha-money">
          Bs. {{ formatNumber(totalIngreso, 0) }}
        </div>
        <div class="ssub">
          valor acumulado
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Parcelas cosechadas</div>
        <div class="sval">{{ parcelasCosechadas }}</div>
        <div class="ssub">
          <i class="ti ti-map-2"></i>
          con producción
        </div>
      </div>
    </div>

    <div class="card cosechas-card">
      <div class="cosechas-toolbar">
        <div>
          <h3>{{ trashMode ? 'Cosechas en papelera' : 'Cosechas registradas' }}</h3>
          <p>
            {{ trashMode
              ? 'Revisa y restaura cosechas eliminadas por error.'
              : 'Filtra por parcela, productor, variedad, calidad o destino.'
            }}
          </p>
        </div>

        <div class="cosechas-filters">
          <select v-if="!trashMode" v-model="filtroCalidad" class="cosechas-select">
            <option value="">Calidad: todas</option>
            <option value="Primera">Primera</option>
            <option value="Segunda">Segunda</option>
            <option value="Tercera">Tercera</option>
            <option value="Descarte">Descarte</option>
          </select>

          <select v-if="!trashMode" v-model="filtroDestino" class="cosechas-select">
            <option value="">Destino: todos</option>
            <option value="Venta">Venta</option>
            <option value="Semilla">Semilla</option>
            <option value="Autoconsumo">Autoconsumo</option>
            <option value="Mixto">Mixto</option>
          </select>

          <select v-model="filtroParcela" class="cosechas-select cosechas-select-lg">
            <option value="">Todas las parcelas</option>
            <option v-for="p in parcelas" :key="p.id" :value="p.id">
              {{ p.codigo_parcela }}
              {{ p.nombre_referencial ? `— ${p.nombre_referencial}` : '' }}
            </option>
          </select>

          <div class="search-box cosechas-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar cosecha..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>{{ trashMode ? 'Cargando papelera...' : 'Cargando cosechas...' }}</span>
      </div>

      <div v-else class="cosechas-table-wrap">
        <table class="tbl cosechas-table">
          <thead>
            <tr>
              <th>Parcela</th>
              <th>Productor</th>
              <th>Variedad</th>
              <th>Fecha</th>
              <th>Toneladas</th>
              <th>Rendimiento</th>
              <th>Calidad</th>
              <th>Destino</th>
              <th>Ingreso</th>
              <th v-if="trashMode">Eliminada</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="c in filteredCosechas" :key="c.id" :class="{ 'trash-row': trashMode }">
              <td>
                <div class="cosecha-parcela-cell">
                  <strong>{{ c.parcela?.codigo_parcela || '—' }}</strong>
                  <small>{{ c.parcela?.nombre_referencial || 'Sin referencia' }}</small>
                </div>
              </td>

              <td>
                <div class="cosecha-productor-cell">
                  <div class="cosecha-avatar">
                    {{ inicialProductor(c.parcela?.productor) }}
                  </div>
                  <span>{{ nombreProductor(c.parcela?.productor) }}</span>
                </div>
              </td>

              <td>
                <span class="b bb">
                  {{ c.cultivo?.variedad_papa || 'Sin especificar' }}
                </span>
              </td>

              <td>{{ formatDate(c.fecha_cosecha) }}</td>

              <td>
                <strong>{{ formatNumber(c.cantidad_toneladas, 2) }} t</strong>
              </td>

              <td>
                <span v-if="c.rendimiento_t_ha" class="b bg">
                  {{ formatNumber(c.rendimiento_t_ha, 2) }} t/ha
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>
                <span class="b" :class="calidadClass(c.calidad)">
                  {{ c.calidad || '—' }}
                </span>
              </td>

              <td>
                <span class="b bgr">
                  {{ c.destino || '—' }}
                </span>
              </td>

              <td>
                <span v-if="c.ingreso_estimado_bs" class="cosecha-ingreso">
                  Bs. {{ formatNumber(c.ingreso_estimado_bs, 0) }}
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td v-if="trashMode">
                <span class="b bgr">
                  {{ formatDate(c.deleted_at) }}
                </span>
              </td>

              <td>
                <div class="cosechas-actions">
                  <button
                    class="cosecha-action view"
                    type="button"
                    title="Ver detalle"
                    :disabled="saving"
                    @click="openView(c)"
                  >
                    <i class="ti ti-eye"></i>
                  </button>

                  <button
                    v-if="!trashMode"
                    class="cosecha-action edit"
                    type="button"
                    title="Editar"
                    :disabled="saving"
                    @click="openEdit(c)"
                  >
                    <i class="ti ti-pencil"></i>
                  </button>

                  <button
                    v-if="!trashMode"
                    class="cosecha-action delete"
                    type="button"
                    title="Enviar a papelera"
                    :disabled="saving"
                    @click="openDelete(c)"
                  >
                    <i class="ti ti-trash"></i>
                  </button>

                  <button
                    v-if="trashMode"
                    class="cosecha-action restore"
                    type="button"
                    title="Restaurar"
                    :disabled="saving"
                    @click="openRestore(c)"
                  >
                    <i class="ti ti-restore"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredCosechas.length === 0">
              <td :colspan="trashMode ? 11 : 10">
                <div class="cosechas-empty">
                  <i :class="trashMode ? 'ti ti-trash-off' : 'ti ti-chart-histogram'"></i>
                  <span>
                    {{ trashMode
                      ? 'No hay cosechas en papelera'
                      : 'No se encontraron cosechas registradas'
                    }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <CosechaFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :cosecha="selectedCosecha"
      :parcelas="parcelas"
      :cultivos="cultivos"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <CosechaViewModal
      v-if="showViewModal"
      :cosecha="selectedCosecha"
      @close="closeViewModal"
    />

    <CosechaDeleteModal
      v-if="showDeleteModal"
      :cosecha="selectedCosecha"
      :saving="saving"
      :error="deleteError"
      @close="closeDeleteModal"
      @confirm="handleDelete"
    />

    <CosechaRestoreModal
      v-if="showRestoreModal"
      :cosecha="selectedCosecha"
      :saving="saving"
      :error="restoreError"
      @close="closeRestoreModal"
      @confirm="handleRestore"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import cosechaService from '@/services/modules/cosechaService';
import parcelaService from '@/services/modules/parcelaService';
import cultivoVariedadService from '@/services/modules/cultivoVariedadService';
import { obtenerMensajeError } from '@/services/errorService';

import CosechaFormModal from '@/components/cosechas/CosechaFormModal.vue';
import CosechaViewModal from '@/components/cosechas/CosechaViewModal.vue';
import CosechaDeleteModal from '@/components/cosechas/CosechaDeleteModal.vue';
import CosechaRestoreModal from '@/components/cosechas/CosechaRestoreModal.vue';

import '@/styles/cosechas.css';
import { normalizeList } from '@/utils/datos';

const cosechas = ref([]);
const parcelas = ref([]);
const cultivos = ref([]);

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const deleteError = ref('');
const restoreError = ref('');

const search = ref('');
const filtroCalidad = ref('');
const filtroDestino = ref('');
const filtroParcela = ref('');

const trashMode = ref(false);

const showFormModal = ref(false);
const showViewModal = ref(false);
const showDeleteModal = ref(false);
const showRestoreModal = ref(false);

const modalMode = ref('create');
const selectedCosecha = ref(null);

const totalToneladas = computed(() => {
  return cosechas.value.reduce((sum, c) => sum + Number(c.cantidad_toneladas || 0), 0);
});

const rendimientoPromedio = computed(() => {
  const conRendimiento = cosechas.value.filter((c) => Number(c.rendimiento_t_ha) > 0);

  if (!conRendimiento.length) {
    return '—';
  }

  const total = conRendimiento.reduce((sum, c) => sum + Number(c.rendimiento_t_ha || 0), 0);

  return (total / conRendimiento.length).toFixed(2);
});

const totalIngreso = computed(() => {
  return cosechas.value.reduce((sum, c) => sum + Number(c.ingreso_estimado_bs || 0), 0);
});

const parcelasCosechadas = computed(() => {
  return new Set(
    cosechas.value
      .map((c) => c.id_parcela || c.parcela?.id)
      .filter(Boolean)
  ).size;
});

const filteredCosechas = computed(() => {
  const q = search.value.trim().toLowerCase();

  return cosechas.value.filter((c) => {
    const texto = [
      c.parcela?.codigo_parcela,
      c.parcela?.nombre_referencial,
      nombreProductor(c.parcela?.productor),
      c.parcela?.productor?.carnet_identidad,
      c.cultivo?.variedad_papa,
      c.calidad,
      c.destino,
      c.observaciones,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);

    const matchCalidad =
      trashMode.value || !filtroCalidad.value || c.calidad === filtroCalidad.value;

    const matchDestino =
      trashMode.value || !filtroDestino.value || c.destino === filtroDestino.value;

    const idParcela = c.id_parcela || c.parcela?.id;

    const matchParcela =
      !filtroParcela.value || Number(idParcela) === Number(filtroParcela.value);

    return matchSearch && matchCalidad && matchDestino && matchParcela;
  });
});

onMounted(fetchAll);

async function fetchAll() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchCosechas(),
      fetchParcelas(),
      fetchCultivos(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchCosechas() {
  try {
    const { data } = trashMode.value
      ? await cosechaService.papelera({ todos: 1 })
      : await cosechaService.listar({ todos: 1 });

    cosechas.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      trashMode.value
        ? 'No se pudieron cargar las cosechas en papelera.'
        : 'No se pudieron cargar las cosechas.'
    );
    cosechas.value = [];
  }
}

async function fetchParcelas() {
  try {
    const { data } = await parcelaService.listar({
      todos: 1,
    });

    parcelas.value = normalizeList(data)
      .filter((p) => p.estado !== 'Inactiva');
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas.');
    parcelas.value = [];
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

async function toggleTrashMode() {
  trashMode.value = !trashMode.value;
  search.value = '';
  filtroCalidad.value = '';
  filtroDestino.value = '';
  filtroParcela.value = '';
  await fetchAll();
}

function openCreate() {
  selectedCosecha.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  showFormModal.value = true;
}

async function openEdit(cosecha) {
  pageError.value = '';

  try {
    const { data } = await cosechaService.obtener(cosecha.id);
    selectedCosecha.value = data;
    modalMode.value = 'edit';
    showFormModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar la cosecha para editar.');
  }
}

function openView(cosecha) {
  selectedCosecha.value = cosecha;
  showViewModal.value = true;
}

function openDelete(cosecha) {
  selectedCosecha.value = cosecha;
  deleteError.value = '';
  showDeleteModal.value = true;
}

function openRestore(cosecha) {
  selectedCosecha.value = cosecha;
  restoreError.value = '';
  showRestoreModal.value = true;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedCosecha.value = null;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedCosecha.value = null;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedCosecha.value = null;
  deleteError.value = '';
}

function closeRestoreModal() {
  showRestoreModal.value = false;
  selectedCosecha.value = null;
  restoreError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await cosechaService.crear(payload);
    } else {
      await cosechaService.actualizar(selectedCosecha.value.id, payload);
    }

    await Promise.all([
  fetchCosechas(),
  fetchCultivos(),
]);

closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar cosecha.');
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  if (!selectedCosecha.value?.id) {
    return;
  }

  saving.value = true;
  deleteError.value = '';

  try {
    await cosechaService.eliminar(selectedCosecha.value.id);
    await Promise.all([
  fetchCosechas(),
  fetchCultivos(),
]);
    closeDeleteModal();
  } catch (err) {
    console.error(err);
    deleteError.value = obtenerMensajeError(err, 'Error al enviar la cosecha a papelera.');
  } finally {
    saving.value = false;
  }
}

async function handleRestore() {
  if (!selectedCosecha.value?.id) {
    return;
  }

  saving.value = true;
  restoreError.value = '';

  try {
    await cosechaService.restaurar(selectedCosecha.value.id);
    await Promise.all([
  fetchCosechas(),
  fetchCultivos(),
]);
    closeRestoreModal();
  } catch (err) {
    console.error(err);
    restoreError.value = obtenerMensajeError(err, 'Error al restaurar la cosecha.');
  } finally {
    saving.value = false;
  }
}

function nombreProductor(productor) {
  if (!productor) {
    return '—';
  }

  return productor.nombre_completo || [
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

function calidadClass(calidad) {
  return {
    Primera: 'bg',
    Segunda: 'bb',
    Tercera: 'ba',
    Descarte: 'bgr',
  }[calidad] || 'badge-gray';
}

function formatDate(value) {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value).slice(0, 10);
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatNumber(value, decimals = 2) {
  if (value === null || value === undefined || value === '') {
    return '0';
  }

  return Number(value).toLocaleString('es-BO', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}
</script>