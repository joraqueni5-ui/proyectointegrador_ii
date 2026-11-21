<template>
  <div class="picker">
    <div class="picker__acciones">
      <BaseButton variante="secundario" icono="ti-current-location" :cargando="ubicando" texto-cargando="Buscando señal GPS…" @click="usarMiUbicacion">
        Usar mi ubicación
      </BaseButton>
      <span class="picker__pista">o toca el mapa para marcar el punto</span>
    </div>

    <div class="picker__mapa">
      <MapaBase ref="base" :centro="centroInicial" :zoom="zoomInicial" capa-inicial="satelite" @listo="alListo" />
    </div>

    <p v-if="avisoGps" class="picker__gps picker__gps--error" role="alert">{{ avisoGps }}</p>
    <p v-else-if="lat !== '' && lng !== ''" class="picker__estado" :class="{ 'es-error': !dentroDeBolivia }">
      <i :class="['ti', dentroDeBolivia ? 'ti-circle-check' : 'ti-alert-triangle']" aria-hidden="true"></i>
      {{ dentroDeBolivia ? 'Ubicación dentro de Bolivia' : 'La ubicación está fuera de Bolivia: revisa latitud y longitud.' }}
    </p>
    <p v-else class="picker__estado picker__estado--vacio">Todavía no hay un punto marcado.</p>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import MapaBase from '@/components/mapa/MapaBase.vue';

// Elige un punto con un toque en el mapa, arrastrando el marcador o con el GPS del dispositivo.
// v-model:lat y v-model:lng (texto o número). Emite `altitud` si el GPS la entrega (casi nunca).
const props = defineProps({
  lat: { type: [String, Number], default: '' },
  lng: { type: [String, Number], default: '' },
  centro: { type: Array, default: null }, // [lat, lng] de referencia si todavía no hay punto
});

const emit = defineEmits(['update:lat', 'update:lng', 'altitud']);

const base = ref(null);
const ubicando = ref(false);
const avisoGps = ref('');

let mapa = null;
let L = null;
let marcador = null;

const LIMITES = { latMin: -23, latMax: -9, lngMin: -70, lngMax: -57 };

const numero = (v) => (v === '' || v === null || v === undefined ? null : Number(v));

const dentroDeBolivia = computed(() => {
  const la = numero(props.lat);
  const lo = numero(props.lng);

  return la !== null && lo !== null && la >= LIMITES.latMin && la <= LIMITES.latMax && lo >= LIMITES.lngMin && lo <= LIMITES.lngMax;
});

const tienePunto = () => Number.isFinite(numero(props.lat)) && Number.isFinite(numero(props.lng));
const centroInicial = computed(() => (tienePunto() ? [numero(props.lat), numero(props.lng)] : props.centro || [-16.5, -68.15]));
const zoomInicial = computed(() => (tienePunto() ? 15 : props.centro ? 11 : 7));

function redondear(n) {
  return Number(n.toFixed(7));
}

function fijar(la, lo, mover = true) {
  emit('update:lat', redondear(la));
  emit('update:lng', redondear(lo));
  avisoGps.value = '';

  if (mover) dibujar(la, lo, true);
}

function dibujar(la, lo, centrar = false) {
  if (!mapa || !L) return;

  if (!marcador) {
    marcador = L.marker([la, lo], { draggable: true }).addTo(mapa);
    marcador.on('dragend', () => {
      const p = marcador.getLatLng();

      fijar(p.lat, p.lng, false);
    });
  } else {
    marcador.setLatLng([la, lo]);
  }

  if (centrar) mapa.setView([la, lo], Math.max(mapa.getZoom(), 15));
}

function alListo(m, leaflet) {
  mapa = m;
  L = leaflet;
  mapa.on('click', (e) => fijar(e.latlng.lat, e.latlng.lng));

  if (tienePunto()) dibujar(numero(props.lat), numero(props.lng));
  setTimeout(() => mapa?.invalidateSize(), 250);
}

// Si se escriben las coordenadas a mano, el marcador las sigue.
watch(
  () => [props.lat, props.lng],
  () => {
    if (tienePunto() && mapa && Number.isFinite(numero(props.lat)) && Number.isFinite(numero(props.lng))) {
      const actual = marcador?.getLatLng();

      if (!actual || Math.abs(actual.lat - numero(props.lat)) > 1e-7 || Math.abs(actual.lng - numero(props.lng)) > 1e-7) {
        dibujar(numero(props.lat), numero(props.lng), true);
      }
    }
  }
);

function usarMiUbicacion() {
  avisoGps.value = '';

  if (!navigator.geolocation) {
    avisoGps.value = 'Este dispositivo no permite obtener la ubicación. Marca el punto en el mapa.';
    return;
  }

  ubicando.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      ubicando.value = false;
      fijar(pos.coords.latitude, pos.coords.longitude);

      if (Number.isFinite(pos.coords.altitude)) emit('altitud', Math.round(pos.coords.altitude));
    },
    (err) => {
      ubicando.value = false;
      avisoGps.value =
        err.code === 1
          ? 'No diste permiso para usar la ubicación. Actívalo en el navegador o marca el punto en el mapa.'
          : 'No se pudo obtener la ubicación. Sal a cielo abierto o marca el punto en el mapa.';
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
  );
}

onBeforeUnmount(() => {
  marcador = null;
  mapa = null;
});
</script>

<style scoped>
.picker {
  display: grid;
  gap: 10px;
}

.picker__acciones {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.picker__pista {
  font-size: 12px;
  color: #4a6070;
}

.picker__mapa {
  height: 300px;
  border-radius: 16px;
  overflow: hidden;
  border: 2px solid #dce6ed;
}

.picker__estado,
.picker__gps {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: #166534;
}

.picker__estado.es-error,
.picker__gps--error {
  color: #b91c1c;
}

.picker__estado--vacio {
  color: #4a6070;
  font-weight: 600;
}
</style>
