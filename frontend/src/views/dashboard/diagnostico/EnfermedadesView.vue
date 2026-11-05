<template>
  <div class="enfermedades-page fade-in">
    <!-- Contextbar -->
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Diagnóstico IA</span>
        <span>›</span>
        <span class="cur">Enfermedades</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nueva enfermedad
        </button>
      </div>
    </div>

    <!-- Header -->
    <div class="pg-header enfermedades-header">
      <div>
        <div class="pg-title">Catálogo de enfermedades</div>
        <div class="pg-sub">
          Gestiona enfermedades agrícolas, síntomas, riesgo fitosanitario y medidas de control.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchEnfermedades">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="enfermedades-error">
      {{ pageError }}
    </div>

    <!-- KPIs -->
    <div v-if="!loading" class="enfermedades-stats mb20">
      <div class="enfermedad-kpi-main">
        <div class="enfermedad-kpi-label">Catálogo fitosanitario</div>
        <div class="enfermedad-kpi-value">
          {{ enfermedades.length }}
        </div>
        <div class="enfermedad-kpi-help">
          enfermedades registradas
        </div>
      </div>

      <div class="sc">
        <div class="slabel">Activas</div>
        <div class="sval">{{ totalActivas }}</div>
        <div class="ssub">
          <i class="ti ti-circle-check up"></i>
          disponibles
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">Riesgo alto/crítico</div>
        <div class="sval">{{ totalRiesgoAlto }}</div>
        <div class="ssub">
          <i class="ti ti-alert-triangle"></i>
          requieren atención
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Con diagnósticos</div>
        <div class="sval">{{ totalConDiagnosticos }}</div>
        <div class="ssub">
          <i class="ti ti-microscope up"></i>
          vinculadas
        </div>
      </div>
    </div>

    <!-- Contenido -->
    <div class="card enfermedades-card">
      <div class="enfermedades-toolbar">
        <div>
          <h3>Listado de enfermedades</h3>
          <p>Filtra por nombre, código, tipo, riesgo o estado.</p>
        </div>

        <div class="enfermedades-filters">
          <select v-model="filtroEstado" class="enfermedades-select">
            <option value="">Todos los estados</option>
            <option value="1">Activas</option>
            <option value="0">Inactivas</option>
          </select>

          <select v-model="filtroTipo" class="enfermedades-select">
            <option value="">Todos los tipos</option>
            <option value="Hongo">Hongo</option>
            <option value="Bacteria">Bacteria</option>
            <option value="Virus">Virus</option>
            <option value="Nematodo">Nematodo</option>
            <option value="Otro">Otro</option>
          </select>

          <select v-model="filtroRiesgo" class="enfermedades-select">
            <option value="">Todos los riesgos</option>
            <option value="Bajo">Bajo</option>
            <option value="Medio">Medio</option>
            <option value="Alto">Alto</option>
            <option value="Critico">Crítico</option>
          </select>

          <div class="search-box enfermedades-search">
            <i class="ti ti-search"></i>
            <input
              v-model="search"
              placeholder="Buscar enfermedad..."
            />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando enfermedades...</span>
      </div>

      <div v-else-if="filteredEnfermedades.length === 0" class="enfermedades-empty">
        <i class="ti ti-virus-off"></i>
        <span>No se encontraron enfermedades</span>
      </div>

      <div v-else class="enfermedades-grid">
        <article
          v-for="enfermedad in filteredEnfermedades"
          :key="enfermedad.id"
          class="enfermedad-card"
          :class="`riesgo-${normalizarClase(enfermedad.nivel_riesgo_base)}`"
        >
          <div class="enfermedad-card-top">
            <div class="enfermedad-icon" :class="`icon-${normalizarClase(enfermedad.nivel_riesgo_base)}`">
              <i class="ti ti-virus"></i>
            </div>

            <div class="enfermedad-status">
              <span class="b" :class="enfermedad.activo ? 'bg' : 'bgr'">
                {{ enfermedad.activo ? 'Activa' : 'Inactiva' }}
              </span>
            </div>
          </div>

          <div class="enfermedad-card-body">
            <span class="enfermedad-code">
              {{ enfermedad.codigo_enfermedad }}
            </span>

            <h3>{{ enfermedad.nombre_comun }}</h3>

            <p v-if="enfermedad.nombre_cientifico" class="enfermedad-scientific">
              {{ enfermedad.nombre_cientifico }}
            </p>

            <p class="enfermedad-desc">
              {{ resumen(enfermedad.descripcion || enfermedad.sintomas) }}
            </p>

            <div class="enfermedad-tags">
              <span class="b bb">{{ enfermedad.tipo }}</span>

              <span class="b" :class="badgeRiesgo(enfermedad.nivel_riesgo_base)">
                Riesgo {{ enfermedad.nivel_riesgo_base }}
              </span>

              <span class="b bgr">
                {{ enfermedad.diagnosticos_count ?? 0 }} diagnósticos
              </span>
            </div>
          </div>

          <div class="enfermedad-card-actions">
            <button class="enfermedad-action view" type="button" @click="openView(enfermedad)">
              <i class="ti ti-eye"></i>
              Ver
            </button>

            <button class="enfermedad-action edit" type="button" @click="openEdit(enfermedad)">
              <i class="ti ti-pencil"></i>
              Editar
            </button>

            <button
              class="enfermedad-action products"
              type="button"
              title="Gestionar productos recomendados"
              @click="openProductos(enfermedad)"
            >
              <i class="ti ti-package"></i>
              Productos
            </button>

            <button
              class="enfermedad-action state"
              :class="enfermedad.activo ? 'danger' : 'success'"
              type="button"
              @click="openEstado(enfermedad)"
            >
              <i :class="enfermedad.activo ? 'ti ti-ban' : 'ti ti-check'"></i>
              {{ enfermedad.activo ? 'Desactivar' : 'Activar' }}
            </button>
          </div>
        </article>
      </div>
    </div>

    <EnfermedadFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :enfermedad="selectedEnfermedad"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <EnfermedadViewModal
      v-if="showViewModal"
      :enfermedad="selectedEnfermedad"
      @close="closeViewModal"
    />

    <EnfermedadEstadoModal
      v-if="showEstadoModal"
      :enfermedad="selectedEnfermedad"
      :saving="saving"
      :error="estadoError"
      @close="closeEstadoModal"
      @confirm="handleCambiarEstado"
    />

    <EnfermedadProductosModal
      v-if="showProductosModal"
      :enfermedad="selectedEnfermedad"
      @close="closeProductosModal"
      @updated="fetchEnfermedades"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import enfermedadService from '@/services/modules/enfermedadService';
import { obtenerMensajeError } from '@/services/errorService';

import EnfermedadFormModal from '@/components/enfermedades/EnfermedadFormModal.vue';
import EnfermedadViewModal from '@/components/enfermedades/EnfermedadViewModal.vue';
import EnfermedadEstadoModal from '@/components/enfermedades/EnfermedadEstadoModal.vue';
import EnfermedadProductosModal from '@/components/enfermedades/EnfermedadProductosModal.vue';

import '@/styles/enfermedades.css';
import { normalizeList } from '@/utils/datos';

const enfermedades = ref([]);

const loading = ref(true);
const saving = ref(false);

const pageError = ref('');
const formError = ref('');
const estadoError = ref('');

const search = ref('');
const filtroTipo = ref('');
const filtroRiesgo = ref('');
const filtroEstado = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showEstadoModal = ref(false);
const showProductosModal = ref(false);

const modalMode = ref('create');
const selectedEnfermedad = ref(null);

const totalActivas = computed(() => {
  return enfermedades.value.filter((e) => e.activo).length;
});

const totalRiesgoAlto = computed(() => {
  return enfermedades.value.filter((e) =>
    ['Alto', 'Critico'].includes(e.nivel_riesgo_base)
  ).length;
});

const totalConDiagnosticos = computed(() => {
  return enfermedades.value.filter((e) => Number(e.diagnosticos_count || 0) > 0).length;
});

const filteredEnfermedades = computed(() => {
  const q = search.value.trim().toLowerCase();

  return enfermedades.value.filter((e) => {
    const texto = [
      e.nombre_comun,
      e.codigo_enfermedad,
      e.nombre_cientifico,
      e.tipo,
      e.nivel_riesgo_base,
      e.sintomas,
      e.descripcion,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);
    const matchTipo = !filtroTipo.value || e.tipo === filtroTipo.value;
    const matchRiesgo = !filtroRiesgo.value || e.nivel_riesgo_base === filtroRiesgo.value;

    const matchEstado =
      filtroEstado.value === ''
        ? true
        : Boolean(e.activo) === Boolean(Number(filtroEstado.value));

    return matchSearch && matchTipo && matchRiesgo && matchEstado;
  });
});

onMounted(fetchEnfermedades);

async function fetchEnfermedades() {
  loading.value = true;
  pageError.value = '';

  try {
    const { data } = await enfermedadService.listar();
    enfermedades.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las enfermedades.');
    enfermedades.value = [];
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  selectedEnfermedad.value = null;
  modalMode.value = 'create';
  pageError.value = '';
  showFormModal.value = true;
}

function openEdit(enfermedad) {
  selectedEnfermedad.value = enfermedad;
  modalMode.value = 'edit';
  pageError.value = '';
  showFormModal.value = true;
}

async function openView(enfermedad) {
  pageError.value = '';

  try {
    const { data } = await enfermedadService.obtener(enfermedad.id);
    selectedEnfermedad.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle de la enfermedad.');
  }
}

function openEstado(enfermedad) {
  selectedEnfermedad.value = enfermedad;
  estadoError.value = '';
  showEstadoModal.value = true;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedEnfermedad.value = null;
}

function closeViewModal() {
  showViewModal.value = false;
  selectedEnfermedad.value = null;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedEnfermedad.value = null;
  estadoError.value = '';
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await enfermedadService.crear(payload);
    } else {
      await enfermedadService.actualizar(selectedEnfermedad.value.id, payload);
    }

    await fetchEnfermedades();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar enfermedad.');
  } finally {
    saving.value = false;
  }
}

async function handleCambiarEstado() {
  if (!selectedEnfermedad.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await enfermedadService.cambiarEstado(selectedEnfermedad.value.id);
    await fetchEnfermedades();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(err, 'Error al cambiar el estado.');
  } finally {
    saving.value = false;
  }
}

function badgeRiesgo(riesgo) {
  return {
    Bajo: 'bg',
    Medio: 'bb',
    Alto: 'ba',
    Critico: 'br',
  }[riesgo] || 'bgr';
}

function normalizarClase(value) {
  return String(value || 'medio')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function resumen(texto) {
  if (!texto) {
    return 'Sin descripción registrada. Agrega síntomas o medidas de control para enriquecer el catálogo.';
  }

  const limpio = String(texto).trim();

  if (limpio.length <= 120) {
    return limpio;
  }

  return `${limpio.slice(0, 120)}...`;
}

function openProductos(enfermedad) {
  selectedEnfermedad.value = enfermedad;
  pageError.value = '';
  showProductosModal.value = true;
}

function closeProductosModal() {
  showProductosModal.value = false;
  selectedEnfermedad.value = null;
}
</script>