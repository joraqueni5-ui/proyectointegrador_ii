<template>
  <div class="mapa-base" :class="{ 'sin-red': !disponible }">
    <div ref="contenedor" class="mapa-base__lienzo"></div>

    <div v-if="selectorCapa" class="mapa-base__capas" role="group" aria-label="Capa del mapa">
      <button
        v-for="c in CAPAS"
        :key="c.id"
        type="button"
        :class="{ 'is-activa': capaActual === c.id }"
        :aria-pressed="capaActual === c.id"
        @click="cambiarCapa(c.id)"
      >
        {{ c.etiqueta }}
      </button>
    </div>

    <p v-if="!disponible" class="mapa-base__aviso" role="status">
      <i class="ti ti-wifi-off" aria-hidden="true"></i>
      {{ capaActual === 'satelite' ? 'Capa satelital no disponible sin internet' : 'Mapa de calles no disponible sin internet' }}
    </p>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import L from '@/utils/leafletIconos';
import 'leaflet/dist/leaflet.css';

// Mapa Leaflet con capa satelital (Esri World Imagery) y de calles (OpenStreetMap).
// Si no hay internet, muestra el aviso y cae a un fondo simple: lo que se dibuje encima (círculos,
// marcadores) sigue visible. Quien lo use recibe el mapa en el evento `listo` (o con `defineExpose`).
const CAPAS = [
  {
    id: 'satelite',
    etiqueta: 'Satélite',
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    atribucion: 'Imágenes © Esri, Maxar, Earthstar Geographics y la comunidad de usuarios GIS',
    maxZoom: 19,
  },
  {
    id: 'calles',
    etiqueta: 'Calles',
    url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    atribucion: '© colaboradores de OpenStreetMap',
    maxZoom: 19,
  },
];

const props = defineProps({
  centro: { type: Array, default: () => [-16.5, -68.15] },
  zoom: { type: Number, default: 7 },
  capaInicial: { type: String, default: 'satelite' },
  selectorCapa: { type: Boolean, default: true },
  controlZoom: { type: Boolean, default: true },
});

const emit = defineEmits(['listo']);

const contenedor = ref(null);
const capaActual = ref(props.capaInicial);
const disponible = ref(typeof navigator === 'undefined' ? true : navigator.onLine);

let mapa = null;
let capaTiles = null;
let erroresSeguidos = 0;

function montarCapa() {
  if (!mapa) return;

  if (capaTiles) mapa.removeLayer(capaTiles);

  const def = CAPAS.find((c) => c.id === capaActual.value) || CAPAS[0];

  erroresSeguidos = 0;
  capaTiles = L.tileLayer(def.url, { attribution: def.atribucion, maxZoom: def.maxZoom, crossOrigin: true });
  capaTiles.on('tileload', () => {
    erroresSeguidos = 0;
    disponible.value = true;
  });
  capaTiles.on('tileerror', () => {
    erroresSeguidos += 1;

    if (erroresSeguidos >= 3) disponible.value = false;
  });
  capaTiles.addTo(mapa);

  if (!navigator.onLine) disponible.value = false;
}

function cambiarCapa(id) {
  capaActual.value = id;
  disponible.value = navigator.onLine;
  montarCapa();
}

function alVolverRed() {
  disponible.value = true;
  capaTiles?.redraw();
}

function alPerderRed() {
  disponible.value = false;
}

onMounted(() => {
  mapa = L.map(contenedor.value, { zoomControl: props.controlZoom, attributionControl: true }).setView(props.centro, props.zoom);
  montarCapa();
  window.addEventListener('online', alVolverRed);
  window.addEventListener('offline', alPerderRed);
  emit('listo', mapa, L);
});

onBeforeUnmount(() => {
  window.removeEventListener('online', alVolverRed);
  window.removeEventListener('offline', alPerderRed);
  mapa?.remove();
  mapa = null;
});

defineExpose({
  obtenerMapa: () => mapa,
  invalidar: () => mapa?.invalidateSize(),
});
</script>

<style scoped>
.mapa-base {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 220px;
  overflow: hidden;
  border-radius: inherit;
  background: #dfe9e3;
}

.mapa-base__lienzo {
  position: absolute;
  inset: 0;
  background: #dfe9e3;
  background-image: linear-gradient(rgba(13, 61, 43, 0.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(13, 61, 43, 0.06) 1px, transparent 1px);
  background-size: 40px 40px;
}

.mapa-base__capas {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 500;
  display: flex;
  padding: 3px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
}

.mapa-base__capas button {
  min-height: 36px;
  padding: 0 14px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.mapa-base__capas button.is-activa {
  background: #0d3d2b;
  color: #fff;
}

.mapa-base__capas button:focus-visible {
  outline: 3px solid #3da87a;
}

.mapa-base__aviso {
  position: absolute;
  left: 50%;
  bottom: 14px;
  transform: translateX(-50%);
  z-index: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 8px 14px;
  background: #fffbeb;
  color: #92400e;
  border-radius: 999px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.18);
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
  max-width: calc(100% - 24px);
}
</style>
