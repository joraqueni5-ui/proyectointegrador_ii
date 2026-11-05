<template>
  <div class="parcelas-page fade-in">
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Módulo Agrícola</span>
        <span>›</span>
        <span class="cur">Parcelas</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-p" type="button" @click="openCreate">
          <i class="ti ti-plus"></i>
          Nueva parcela
        </button>
      </div>
    </div>

    <div class="pg-header parcelas-header">
      <div>
        <div class="pg-title">Parcelas</div>
        <div class="pg-sub">
          Registro, ubicación GPS y seguimiento de parcelas agrícolas.
        </div>
      </div>

      <button class="btn btn-s" type="button" :disabled="loading" @click="fetchAll">
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div v-if="pageError" class="parcelas-error">
      {{ pageError }}
    </div>

    <div v-if="!loading" class="parcelas-stats mb20">
      <div class="sc">
        <div class="slabel">Total parcelas</div>
        <div class="sval">{{ parcelas.length }}</div>
        <div class="ssub">
          <i class="ti ti-map-2 up"></i>
          registradas
        </div>
      </div>

      <div class="sc blue">
        <div class="slabel">Activas</div>
        <div class="sval">{{ parcelasActivas }}</div>
        <div class="ssub">
          <i class="ti ti-check up"></i>
          en producción
        </div>
      </div>

      <div class="sc amber">
        <div class="slabel">En descanso</div>
        <div class="sval">{{ parcelasDescanso }}</div>
        <div class="ssub">
          <i class="ti ti-zzz"></i>
          temporalmente
        </div>
      </div>

      <div class="sc purple">
        <div class="slabel">Superficie total</div>
        <div class="sval">
          {{ superficieTotal.toFixed(1) }}
          <span class="parcelas-unit">ha</span>
        </div>
        <div class="ssub">
          <i class="ti ti-ruler-measure"></i>
          hectáreas
        </div>
      </div>
    </div>

    <div class="card parcelas-card">
      <div class="parcelas-toolbar">
        <div>
          <h3>Listado de parcelas</h3>
          <p>Filtra por productor, estado, sistema de riego, GPS o búsqueda general.</p>
        </div>

        <div class="parcelas-filters">
          <select v-model="filtroProductor" class="parcelas-select parcelas-select-lg">
            <option value="">Todos los productores</option>
            <option v-for="p in productores" :key="p.id" :value="p.id">
              {{ nombreProductor(p) }}
            </option>
          </select>

          <select v-model="filtroEstado" class="parcelas-select">
            <option value="">Todos los estados</option>
            <option value="Activa">Activas</option>
            <option value="En_descanso">En descanso</option>
            <option value="Inactiva">Inactivas</option>
          </select>

          <select v-model="filtroRiego" class="parcelas-select">
            <option value="">Riego: todos</option>
            <option value="Secano">Secano</option>
            <option value="Riego">Riego</option>
            <option value="Mixto">Mixto</option>
          </select>

          <select v-model="filtroGps" class="parcelas-select">
            <option value="">GPS: todos</option>
            <option value="true">Con GPS</option>
            <option value="false">Sin GPS</option>
          </select>

          <div class="search-box parcelas-search">
            <i class="ti ti-search"></i>
            <input v-model="search" placeholder="Buscar parcela..." />
          </div>
        </div>
      </div>

      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Cargando parcelas...</span>
      </div>

      <div v-else class="parcelas-table-wrap">
        <table class="tbl parcelas-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Código</th>
              <th>Productor</th>
              <th>Superficie</th>
              <th>Suelo</th>
              <th>Riego</th>
              <th>Altitud</th>
              <th>GPS</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="p in filteredParcelas" :key="p.id">
              <td class="parcela-id">
                {{ p.id }}
              </td>

              <td>
                <div class="parcela-code-cell">
                  <strong>{{ p.codigo_parcela }}</strong>
                  <small>{{ p.nombre_referencial || 'Sin nombre referencial' }}</small>
                </div>
              </td>

              <td>
                <div class="parcela-productor-cell">
                  <div class="parcela-productor-avatar">
                    {{ inicialProductor(p.productor) }}
                  </div>
                  <span>{{ nombreProductor(p.productor) }}</span>
                </div>
              </td>

              <td>
                <span v-if="p.superficie_ha" class="b bg">
                  {{ p.superficie_ha }} ha
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>{{ p.tipo_suelo || '—' }}</td>

              <td>
                <span class="b bb">
                  {{ p.sistema_riego || '—' }}
                </span>
              </td>

              <td>
                <span v-if="p.altitud_msnm" class="b ba">
                  {{ Number(p.altitud_msnm).toLocaleString('es-BO') }} m
                </span>
                <span v-else class="muted">—</span>
              </td>

              <td>
                <span class="b" :class="tieneGps(p) ? 'bg' : 'badge-gray'">
                  <i :class="tieneGps(p) ? 'ti ti-map-pin' : 'ti ti-map-off'"></i>
                  {{ tieneGps(p) ? 'Sí' : 'No' }}
                </span>
              </td>

              <td>
                <span class="b" :class="badgeEstado(p.estado)">
                  {{ limpiarEstado(p.estado) }}
                </span>
              </td>

              <td>
                <div class="parcelas-actions">
                  <button
                    class="parcela-action view"
                    type="button"
                    title="Ver detalle"
                    :disabled="saving"
                    @click="openView(p)"
                  >
                    <i class="ti ti-eye"></i>
                  </button>

                  <button
                    class="parcela-action edit"
                    type="button"
                    title="Editar"
                    :disabled="saving"
                    @click="openEdit(p)"
                  >
                    <i class="ti ti-pencil"></i>
                  </button>

                  <button
                    class="parcela-action estado"
                    type="button"
                    title="Cambiar estado"
                    :disabled="saving"
                    @click="openEstado(p)"
                  >
                    <i class="ti ti-refresh"></i>
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredParcelas.length === 0">
              <td colspan="10">
                <div class="parcelas-empty">
                  <i class="ti ti-map-2"></i>
                  <span>No se encontraron parcelas</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <ParcelaFormModal
      v-if="showFormModal"
      :mode="modalMode"
      :parcela="selectedParcela"
      :productores="productores"
      :saving="saving"
      :error="formError"
      @close="closeFormModal"
      @save="handleSave"
    />

    <ParcelaViewModal
      v-if="showViewModal"
      :parcela="selectedParcela"
      @close="closeViewModal"
    />

    <ParcelaEstadoModal
      v-if="showEstadoModal"
      :parcela="selectedParcela"
      :saving="saving"
      :error="estadoError"
      @close="closeEstadoModal"
      @confirm="handleCambiarEstado"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import parcelaService from '@/services/modules/parcelaService';
import productorService from '@/services/modules/productorService';
import { obtenerMensajeError } from '@/services/errorService';

import ParcelaFormModal from '@/components/parcelas/ParcelaFormModal.vue';
import ParcelaViewModal from '@/components/parcelas/ParcelaViewModal.vue';
import ParcelaEstadoModal from '@/components/parcelas/ParcelaEstadoModal.vue';

import '@/styles/parcelas.css';
import { normalizeList } from '@/utils/datos';

const parcelas = ref([]);
const productores = ref([]);

const loading = ref(true);
const saving = ref(false);

const search = ref('');
const filtroProductor = ref('');
const filtroEstado = ref('');
const filtroRiego = ref('');
const filtroGps = ref('');

const pageError = ref('');
const formError = ref('');
const estadoError = ref('');

const showFormModal = ref(false);
const showViewModal = ref(false);
const showEstadoModal = ref(false);

const modalMode = ref('create');
const selectedParcela = ref(null);

const parcelasActivas = computed(() => {
  return parcelas.value.filter((p) => p.estado === 'Activa').length;
});

const parcelasDescanso = computed(() => {
  return parcelas.value.filter((p) => p.estado === 'En_descanso').length;
});

const superficieTotal = computed(() => {
  return parcelas.value.reduce((sum, p) => sum + Number(p.superficie_ha || 0), 0);
});

const filteredParcelas = computed(() => {
  const q = search.value.trim().toLowerCase();

  return parcelas.value.filter((p) => {
    const texto = [
      p.codigo_parcela,
      p.nombre_referencial,
      p.tipo_suelo,
      p.pendiente,
      p.sistema_riego,
      p.estado,
      nombreProductor(p.productor),
      p.productor?.carnet_identidad,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    const matchSearch = !q || texto.includes(q);

    const matchProductor =
      !filtroProductor.value || Number(p.id_productor) === Number(filtroProductor.value);

    const matchEstado =
      !filtroEstado.value || p.estado === filtroEstado.value;

    const matchRiego =
      !filtroRiego.value || p.sistema_riego === filtroRiego.value;

    const matchGps =
      filtroGps.value === ''
        ? true
        : tieneGps(p) === (filtroGps.value === 'true');

    return matchSearch && matchProductor && matchEstado && matchRiego && matchGps;
  });
});

onMounted(fetchAll);

async function fetchAll() {
  loading.value = true;
  pageError.value = '';

  try {
    await Promise.all([
      fetchParcelas(),
      fetchProductores(),
    ]);
  } finally {
    loading.value = false;
  }
}

async function fetchParcelas() {
  try {
    const { data } = await parcelaService.listar();
    parcelas.value = normalizeList(data);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las parcelas.');
    parcelas.value = [];
  }
}

async function fetchProductores() {
  try {
    const { data } = await productorService.listar({
      todos: 1,
    });

    productores.value = normalizeList(data)
      .filter((p) => p.activo !== false);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar los productores.');
    productores.value = [];
  }
}

function openCreate() {
  selectedParcela.value = null;
  modalMode.value = 'create';
  showFormModal.value = true;
}

function openEdit(parcela) {
  selectedParcela.value = parcela;
  modalMode.value = 'edit';
  showFormModal.value = true;
}

async function openView(parcela) {
  pageError.value = '';

  try {
    const { data } = await parcelaService.obtener(parcela.id);
    selectedParcela.value = data;
    showViewModal.value = true;
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudo cargar el detalle de la parcela.');
  }
}

function closeViewModal() {
  showViewModal.value = false;
  selectedParcela.value = null;
}

function closeFormModal() {
  formError.value = '';
  showFormModal.value = false;
  selectedParcela.value = null;
}

async function handleSave(payload) {
  saving.value = true;
  formError.value = '';

  try {
    if (modalMode.value === 'create') {
      await parcelaService.crear(payload);
    } else {
      await parcelaService.actualizar(selectedParcela.value.id, payload);
    }

    await fetchParcelas();
    closeFormModal();
  } catch (err) {
    console.error(err);
    formError.value = obtenerMensajeError(err, 'Error al guardar parcela.');
  } finally {
    saving.value = false;
  }
}

function openEstado(parcela) {
  selectedParcela.value = parcela;
  estadoError.value = '';
  showEstadoModal.value = true;
}

function closeEstadoModal() {
  showEstadoModal.value = false;
  selectedParcela.value = null;
  estadoError.value = '';
}

async function handleCambiarEstado(estado) {
  if (!selectedParcela.value?.id) {
    return;
  }

  saving.value = true;
  estadoError.value = '';

  try {
    await parcelaService.cambiarEstado(selectedParcela.value.id, estado);
    await fetchParcelas();
    closeEstadoModal();
  } catch (err) {
    console.error(err);
    estadoError.value = obtenerMensajeError(err, 'Error al cambiar estado.');
  } finally {
    saving.value = false;
  }
}

function tieneGps(parcela) {
  return parcela?.latitud !== null &&
    parcela?.latitud !== undefined &&
    parcela?.latitud !== '' &&
    parcela?.longitud !== null &&
    parcela?.longitud !== undefined &&
    parcela?.longitud !== '';
}

function badgeEstado(estado) {
  return {
    Activa: 'bg',
    En_descanso: 'ba',
    Inactiva: 'bgr',
  }[estado] || 'badge-gray';
}

function limpiarEstado(estado) {
  if (!estado) {
    return '—';
  }

  return String(estado).replaceAll('_', ' ');
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
</script>