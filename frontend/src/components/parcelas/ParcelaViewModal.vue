<template>
  <div class="modal-overlay parcela-modal-overlay" @click.self="close">
    <div class="modal-card parcela-view-modal">
      <div class="parcela-view-header">
        <div class="parcela-view-icon">
          <i class="ti ti-map-2"></i>
        </div>

        <div class="parcela-view-main">
          <h3>{{ parcela?.codigo_parcela || 'Parcela' }}</h3>
          <p>{{ parcela?.nombre_referencial || 'Detalle de parcela agrícola' }}</p>

          <div class="parcela-view-badges">
            <span class="b" :class="badgeEstado(parcela?.estado)">
              {{ limpiarEstado(parcela?.estado) }}
            </span>

            <span class="b bb">
              {{ parcela?.sistema_riego || 'Secano' }}
            </span>

            <span class="b" :class="tieneGps ? 'bg' : 'badge-gray'">
              {{ tieneGps ? 'Con GPS' : 'Sin GPS' }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="parcela-view-body">
        <div class="parcela-view-section-title">Datos principales</div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-user"></i>
          </div>
          <div>
            <label>Productor</label>
            <p>{{ nombreProductor(parcela?.productor) }}</p>
          </div>
        </div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-ruler-measure"></i>
          </div>
          <div>
            <label>Superficie</label>
            <p>{{ parcela?.superficie_ha ? parcela.superficie_ha + ' ha' : '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-mountain"></i>
          </div>
          <div>
            <label>Altitud</label>
            <p>{{ parcela?.altitud_msnm ? Number(parcela.altitud_msnm).toLocaleString('es-BO') + ' m.s.n.m.' : '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-droplet"></i>
          </div>
          <div>
            <label>Sistema de riego</label>
            <p>{{ parcela?.sistema_riego || '—' }}</p>
          </div>
        </div>

        <div class="parcela-view-section-title">Características del terreno</div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-layers-subtract"></i>
          </div>
          <div>
            <label>Tipo de suelo</label>
            <p>{{ parcela?.tipo_suelo || '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-line"></i>
          </div>
          <div>
            <label>Pendiente</label>
            <p>{{ parcela?.pendiente || '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card span-2">
          <div class="parcela-detail-icon">
            <i class="ti ti-notes"></i>
          </div>
          <div>
            <label>Observaciones</label>
            <p>{{ parcela?.observaciones || '—' }}</p>
          </div>
        </div>

        <div class="parcela-view-section-title">Ubicación GPS</div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-current-location"></i>
          </div>
          <div>
            <label>Latitud</label>
            <p>{{ parcela?.latitud ?? '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card">
          <div class="parcela-detail-icon">
            <i class="ti ti-location"></i>
          </div>
          <div>
            <label>Longitud</label>
            <p>{{ parcela?.longitud ?? '—' }}</p>
          </div>
        </div>

        <div class="parcela-detail-card span-2">
          <div class="parcela-detail-icon">
            <i class="ti ti-map-pin"></i>
          </div>
          <div>
            <label>Coordenadas en texto</label>
            <p>{{ parcela?.coordenadas_texto || '—' }}</p>
          </div>
        </div>

        <div class="parcela-map-preview span-2">
          <div ref="mapContainer" class="parcela-detail-map"></div>
        </div>

        <div class="parcela-view-section-title">Cultivos registrados</div>

        <div v-if="!cultivos.length" class="parcela-empty-block span-2">
          <i class="ti ti-plant-off"></i>
          <span>Sin cultivos registrados</span>
        </div>

        <div v-else class="parcela-mini-table span-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Variedad</th>
                <th>Fecha siembra</th>
                <th>Etapa</th>
                <th>Estado</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="c in cultivos" :key="c.id">
                <td>
                  <strong>{{ c.variedad_papa || '—' }}</strong>
                </td>
                <td>{{ formatDate(c.fecha_siembra) }}</td>
                <td>{{ c.etapa_fenologica?.nombre || '—' }}</td>
                <td>
                  <span class="b" :class="c.estado === 'Activo' ? 'bg' : 'bgr'">
                    {{ c.estado || '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="parcela-view-section-title">Últimas inspecciones</div>

        <div v-if="!inspecciones.length" class="parcela-empty-block span-2">
          <i class="ti ti-clipboard-x"></i>
          <span>Sin inspecciones registradas</span>
        </div>

        <div v-else class="parcela-mini-table span-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Técnico</th>
                <th>Estado</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="i in inspecciones" :key="i.id">
                <td>{{ formatDate(i.fecha_visita) }}</td>
                <td>{{ i.usuario ? `${i.usuario.name || ''} ${i.usuario.paterno || ''}` : '—' }}</td>
                <td>
                  <span class="b bgr">
                    {{ limpiarEstado(i.estado) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

const props = defineProps({
  parcela: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const mapContainer = ref(null);
let mapInstance = null;

const cultivos = computed(() => props.parcela?.cultivo_variedades || []);
const inspecciones = computed(() => props.parcela?.inspecciones || []);

const tieneGps = computed(() => {
  return props.parcela?.latitud !== null &&
    props.parcela?.latitud !== undefined &&
    props.parcela?.longitud !== null &&
    props.parcela?.longitud !== undefined;
});

onMounted(async () => {
  await nextTick();
  initMap();
});

onBeforeUnmount(() => {
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});

function initMap() {
  if (!mapContainer.value || !tieneGps.value) {
    return;
  }

  const lat = Number(props.parcela.latitud);
  const lng = Number(props.parcela.longitud);

  if (Number.isNaN(lat) || Number.isNaN(lng)) {
    return;
  }

  mapInstance = L.map(mapContainer.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  }).setView([lat, lng], 16);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
  }).addTo(mapInstance);

  L.marker([lat, lng])
    .addTo(mapInstance)
    .bindPopup(props.parcela.codigo_parcela || 'Parcela');

  setTimeout(() => {
    mapInstance?.invalidateSize();
  }, 200);
}

function close() {
  emit('close');
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

function formatDate(dateStr) {
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
</script>