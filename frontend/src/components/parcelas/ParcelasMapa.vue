<template>
  <div class="pm">
    <MapaBase ref="base" @listo="alListo" />

    <ul class="pm__leyenda" aria-label="Leyenda del mapa">
      <li v-for="e in leyenda" :key="e.clave">
        <i :style="{ background: e.color }"></i>{{ e.etiqueta }} <b>{{ e.total }}</b>
      </li>
      <li v-if="sinUbicacion" class="pm__sin"><i></i>Sin ubicación <b>{{ sinUbicacion }}</b></li>
    </ul>

    <aside v-if="seleccionada" class="pm__tarjeta" aria-live="polite">
      <header>
        <div>
          <strong>{{ seleccionada.nombre_visible }}</strong>
          <span class="pm__prod">{{ productor }}</span>
        </div>
        <PriorityBadge v-if="seleccionada.estado_sanitario === 'alerta' && seleccionada.alerta_puntaje !== null" :puntaje="seleccionada.alerta_puntaje" />
        <StatusBadge v-else :tono="sanidad.tono" :texto="sanidad.clave === 'sana' ? 'Sana' : sanidad.clave === 'sin_visita' ? 'Sin visita +30 días' : sanidad.clave === 'descanso' ? 'En descanso' : 'Con alerta'" />
      </header>

      <dl class="pm__datos">
        <div><dt>Superficie</dt><dd>{{ hectareas(seleccionada.superficie_ha) }}</dd></div>
        <div><dt>Cultivo</dt><dd>{{ cultivo.dia }}<small v-if="cultivo.etapa">{{ cultivo.etapa }}</small></dd></div>
        <div><dt>Altitud</dt><dd>{{ seleccionada.altitud_msnm ? altitud(seleccionada.altitud_msnm) : '—' }}</dd></div>
      </dl>

      <footer>
        <BaseButton @click="$emit('ver-ficha', seleccionada)">Ver ficha</BaseButton>
        <BaseButton variante="secundario" @click="$emit('programar-visita', seleccionada)">Programar visita</BaseButton>
      </footer>
    </aside>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import MapaBase from '@/components/mapa/MapaBase.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import PriorityBadge from '@/components/ui/PriorityBadge.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { altitud, hectareas } from '@/utils/formato';
import { ESTADOS_SANIDAD, diaDeCultivo, radioParcela, sanidadDe } from '@/utils/sanidad';

// Mapa de sanidad: un círculo por parcela (radio según superficie, color según estado sanitario).
// Seleccionar en el mapa o en la lista es lo mismo: `seleccionadaId` manda y el mapa se centra.
const props = defineProps({
  parcelas: { type: Array, default: () => [] },
  seleccionadaId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['seleccionar', 'ver-ficha', 'programar-visita']);

const base = ref(null);
let mapa = null;
let L = null;
let capa = null;
const circulos = new Map();

const conUbicacion = (p) => Number.isFinite(Number(p.latitud)) && Number.isFinite(Number(p.longitud)) && p.latitud !== null && p.longitud !== null;

const leyenda = computed(() =>
  Object.values(ESTADOS_SANIDAD).map((e) => ({ ...e, total: props.parcelas.filter((p) => p.estado_sanitario === e.clave).length }))
);
const sinUbicacion = computed(() => props.parcelas.filter((p) => !conUbicacion(p)).length);

const seleccionada = computed(() => props.parcelas.find((p) => p.id === props.seleccionadaId) || null);
const sanidad = computed(() => sanidadDe(seleccionada.value));
const productor = computed(() => {
  const p = seleccionada.value?.productor;

  if (!p) return '';

  const nombre = [String(p.nombres || '').split(' ')[0], p.apellido_paterno].filter(Boolean).join(' ') || p.nombre_completo;

  return [nombre, p.telefono].filter(Boolean).join(' · ');
});
const cultivo = computed(() => {
  const c = seleccionada.value?.cultivo_activo;
  const dia = diaDeCultivo(c);

  return { dia: dia ? `Día ${dia}` : '—', etapa: dia ? c?.etapa_fenologica?.nombre || '' : '' };
});

function alListo(m, leaflet) {
  mapa = m;
  L = leaflet;
  capa = L.layerGroup().addTo(mapa);
  dibujar(true);
}

function estilo(p, elegida) {
  return {
    radius: radioParcela(p.superficie_ha),
    color: elegida ? '#0d3d2b' : '#ffffff',
    weight: elegida ? 4 : 2,
    fillColor: sanidadDe(p).color,
    fillOpacity: 0.92,
  };
}

function dibujar(ajustar = false) {
  if (!mapa || !L) return;

  capa.clearLayers();
  circulos.clear();

  const puntos = [];

  for (const p of props.parcelas) {
    if (!conUbicacion(p)) continue;

    const punto = [Number(p.latitud), Number(p.longitud)];
    const c = L.circleMarker(punto, estilo(p, p.id === props.seleccionadaId)).addTo(capa);

    c.on('click', () => emit('seleccionar', p));
    c.bindTooltip(`${p.nombre_visible} · ${hectareas(p.superficie_ha)}`, { direction: 'top', offset: [0, -4] });
    circulos.set(p.id, c);
    puntos.push(punto);
  }

  if (ajustar && puntos.length) mapa.fitBounds(L.latLngBounds(puntos), { padding: [48, 48], maxZoom: 13 });
}

watch(() => props.parcelas, () => dibujar(false), { deep: false });

watch(
  () => props.seleccionadaId,
  (nuevo, viejo) => {
    if (viejo !== null && circulos.has(viejo)) {
      const p = props.parcelas.find((x) => x.id === viejo);

      if (p) circulos.get(viejo).setStyle(estilo(p, false));
    }

    if (nuevo !== null && circulos.has(nuevo)) {
      const p = props.parcelas.find((x) => x.id === nuevo);
      const c = circulos.get(nuevo);

      c.setStyle(estilo(p, true));
      c.bringToFront();
      mapa.flyTo(c.getLatLng(), Math.max(mapa.getZoom(), 15), { duration: 0.6 });
    }
  }
);

defineExpose({ invalidar: () => base.value?.invalidar(), ajustar: () => dibujar(true) });

onBeforeUnmount(() => {
  circulos.clear();
  mapa = null;
});
</script>

<style scoped>
.pm {
  position: relative;
  width: 100%;
  height: 100%;
}

.pm__leyenda {
  position: absolute;
  left: 12px;
  bottom: 28px;
  z-index: 500;
  display: grid;
  gap: 4px;
  margin: 0;
  padding: 10px 12px;
  list-style: none;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
  font-size: 12px;
  font-weight: 600;
  color: #111827;
}

.pm__leyenda li {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pm__leyenda i {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #cbd5e1;
}

.pm__leyenda b {
  margin-left: auto;
  padding-left: 10px;
}

.pm__sin i {
  background: transparent;
  box-shadow: 0 0 0 1px #8ba3b5;
}

.pm__tarjeta {
  position: absolute;
  right: 14px;
  bottom: 28px;
  z-index: 500;
  width: min(380px, calc(100% - 28px));
  display: grid;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 14px 40px rgba(15, 23, 42, 0.28);
}

.pm__tarjeta header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.pm__tarjeta header strong {
  display: block;
  font-size: 16px;
  font-weight: 800;
}

.pm__prod {
  font-size: 12px;
  color: #4a6070;
}

.pm__datos {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
}

.pm__datos > div {
  padding: 8px 10px;
  background: #f8fafc;
  border-radius: 10px;
  min-width: 0;
}

.pm__datos dt {
  font-size: 11px;
  color: #4a6070;
}

.pm__datos dd {
  margin: 2px 0 0;
  font-size: 13px;
  font-weight: 800;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pm__datos dd small {
  display: block;
  margin-top: 1px;
  font-size: 11px;
  font-weight: 600;
  color: #4a6070;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pm__tarjeta footer {
  display: flex;
  gap: 8px;
}

.pm__tarjeta footer > * {
  flex: 1;
  padding: 0 10px;
}

@media (max-width: 767px) {
  .pm__leyenda {
    bottom: auto;
    top: 68px;
    left: 12px;
  }
}
</style>
