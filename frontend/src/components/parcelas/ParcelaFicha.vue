<template>
  <DetailDrawer
    :model-value="modelValue"
    :titulo="parcela?.nombre_visible || ''"
    :codigo="parcela?.codigo_parcela || ''"
    :subtitulo="subtitulo"
    :ancho="1000"
    @update:model-value="(v) => $emit('update:modelValue', v)"
  >
    <template #estado>
      <PriorityBadge v-if="parcela?.estado_sanitario === 'alerta' && parcela.alerta_puntaje !== null && parcela.alerta_puntaje !== undefined" :puntaje="parcela.alerta_puntaje" />
      <StatusBadge v-if="parcela" :estado="parcela.estado" />
      <StatusBadge v-if="parcela?.estado_sanitario === 'alerta'" tono="naranja" texto="Con alerta" />
      <StatusBadge v-if="parcela?.sin_nombre" tono="ambar" texto="sin nombre" />
    </template>

    <div v-if="parcela" class="pf">
      <div class="pf__principal">
        <ul class="pf__datos" aria-label="Datos del terreno">
          <li><i class="ti ti-ruler-2" aria-hidden="true"></i>{{ hectareas(parcela.superficie_ha) }}</li>
          <li><i class="ti ti-droplet" aria-hidden="true"></i>{{ parcela.sistema_riego }}</li>
          <li v-if="parcela.pendiente"><i class="ti ti-chart-line" aria-hidden="true"></i>Pendiente {{ parcela.pendiente.toLowerCase() }}</li>
          <li v-if="parcela.altitud_msnm"><i class="ti ti-mountain" aria-hidden="true"></i>{{ altitud(parcela.altitud_msnm) }}</li>
          <li v-if="parcela.tipo_suelo"><i class="ti ti-grain" aria-hidden="true"></i>Suelo {{ parcela.tipo_suelo.toLowerCase() }}</li>
        </ul>

        <section class="pf__bloque">
          <h3>Campaña actual · {{ campanaActual }}</h3>
          <template v-if="cultivo">
            <p class="pf__cultivo">
              <strong>{{ cultivo.variedad_papa }}</strong>
              · sembrada el {{ fecha(cultivo.fecha_siembra) }}
              <span v-if="diaCultivo" class="pf__dia">día {{ diaCultivo }}</span>
            </p>
            <p v-if="etapaActual" class="pf__etapa">Etapa actual: <strong>{{ etapaActual.nombre }}</strong> · {{ etapas.findIndex((e) => e.id === etapaActual.id) + 1 }} de {{ etapas.length }}</p>
            <ol v-if="etapas.length" class="pf__etapas" aria-label="Etapas fenológicas">
              <li v-for="e in etapas" :key="e.id" :title="e.nombre" :class="{ 'is-actual': e.id === cultivo.id_etapa_fenologica, 'is-pasada': etapaActual && e.orden < etapaActual.orden }" :aria-current="e.id === cultivo.id_etapa_fenologica ? 'step' : undefined">
                <span></span><b class="pf__sr">{{ e.nombre }}</b>
              </li>
            </ol>
          </template>
          <p v-else class="texto-secundario">Esta parcela no tiene un cultivo activo en este momento.</p>
        </section>

        <section class="pf__bloque">
          <h3>Historial de sanidad</h3>
          <p v-if="cargandoEventos" class="texto-secundario">Cargando historial…</p>
          <p v-else-if="!eventos.length" class="texto-secundario">Todavía no hay siembras, visitas, diagnósticos ni alertas registrados.</p>
          <ol v-else class="pf__tiempo">
            <li v-for="e in eventos" :key="e.id">
              <span class="pf__punto" :style="{ background: estilos[e.tipo].color }"><i :class="['ti', estilos[e.tipo].icono]" aria-hidden="true"></i></span>
              <div class="pf__evento">
                <div class="pf__evento-cabeza">
                  <strong>{{ e.titulo }}</strong>
                  <time :datetime="e.fecha" :title="fecha(e.fecha)">{{ fechaRelativa(e.fecha) }}</time>
                </div>
                <p v-if="e.detalle">{{ e.detalle }}</p>
                <div class="pf__evento-pie">
                  <StatusBadge v-if="e.estado" :estado="e.estado" />
                  <img v-if="e.foto" :src="urlArchivo(e.foto)" alt="Foto del diagnóstico" loading="lazy" />
                </div>
              </div>
            </li>
          </ol>
        </section>
      </div>

      <div class="pf__lateral">
        <section class="pf__mapa" aria-label="Ubicación">
          <MapaBase v-if="tieneUbicacion" :key="parcela.id" :centro="[Number(parcela.latitud), Number(parcela.longitud)]" :zoom="15" :selector-capa="false" @listo="dibujar" />
          <p v-else class="pf__sin-ubicacion"><i class="ti ti-map-pin-off" aria-hidden="true"></i> Sin ubicación · completar</p>
        </section>

        <section class="pf__bloque">
          <h3>Producción</h3>
          <ul v-if="produccion.length" class="pf__produccion">
            <li v-for="p in produccion" :key="p.campana">
              <span>Campaña {{ p.campana }}</span>
              <strong>{{ toneladas(p.toneladas) }}<small v-if="p.rendimiento_t_ha !== null"> · {{ numero(p.rendimiento_t_ha, { decimales: 2 }) }} t/ha</small></strong>
            </li>
          </ul>
          <p v-else class="texto-secundario">Aún no hay cosechas registradas.</p>
        </section>

        <section class="pf__bloque">
          <h3>Último diagnóstico</h3>
          <template v-if="parcela.ultimo_diagnostico_nombre">
            <p class="pf__ultimo"><strong>{{ parcela.ultimo_diagnostico_nombre }}</strong><span v-if="parcela.ultimo_diagnostico_confianza"> · {{ porcentaje(parcela.ultimo_diagnostico_confianza, 1) }}</span></p>
            <StatusBadge :estado="parcela.ultimo_diagnostico_estado || 'Pendiente'" />
          </template>
          <p v-else class="texto-secundario">Todavía sin diagnósticos.</p>
        </section>
      </div>
    </div>

    <template #acciones>
      <BaseButton variante="secundario" icono="ti-refresh" @click="$emit('cambiar-estado', parcela)">Cambiar estado</BaseButton>
      <BaseButton variante="secundario" icono="ti-pencil" @click="$emit('editar', parcela)">Editar</BaseButton>
      <BaseButton icono="ti-clipboard-plus" @click="$emit('programar-visita', parcela)">Nueva inspección</BaseButton>
    </template>
  </DetailDrawer>
</template>

<script setup>
import { computed } from 'vue';
import DetailDrawer from '@/components/ui/DetailDrawer.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PriorityBadge from '@/components/ui/PriorityBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import MapaBase from '@/components/mapa/MapaBase.vue';
import { urlArchivo } from '@/services/api';
import { altitud, fecha, fechaRelativa, hectareas, numero, porcentaje, toneladas } from '@/utils/formato';
import { etiquetaCampana } from '@/utils/campana';
import { diaDeCultivo, sanidadDe } from '@/utils/sanidad';

defineEmits(['update:modelValue', 'editar', 'cambiar-estado', 'programar-visita']);

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  parcela: { type: Object, default: null },
  eventos: { type: Array, default: () => [] },
  cargandoEventos: { type: Boolean, default: false },
  etapas: { type: Array, default: () => [] },
});

const estilos = {
  siembra: { color: '#2e7d5a', icono: 'ti-seeding' },
  inspeccion: { color: '#2563eb', icono: 'ti-clipboard-check' },
  diagnostico: { color: '#1a5c3e', icono: 'ti-microscope' },
  alerta: { color: '#f07a2a', icono: 'ti-bell-ringing' },
  cosecha: { color: '#d97706', icono: 'ti-basket' },
};

const campanaActual = etiquetaCampana();
const productor = computed(() => props.parcela?.productor);
const subtitulo = computed(() => {
  const p = productor.value;

  if (!p) return '';

  const nombre = p.nombre_completo || [p.nombres, p.apellido_paterno].filter(Boolean).join(' ');

  return [nombre, p.comunidad?.nombre_comunidad, p.telefono].filter(Boolean).join(' · ');
});

const cultivo = computed(() => props.parcela?.cultivo_activo || (props.parcela?.cultivo_variedades || []).find((c) => c.estado === 'Activo') || null);
const diaCultivo = computed(() => diaDeCultivo(cultivo.value));
const etapaActual = computed(() => props.etapas.find((e) => e.id === cultivo.value?.id_etapa_fenologica) || null);
const produccion = computed(() => props.parcela?.produccion_por_campana || []);
const tieneUbicacion = computed(() => props.parcela && props.parcela.latitud !== null && props.parcela.latitud !== '' && props.parcela.longitud !== null && props.parcela.longitud !== '');

function dibujar(mapa, L) {
  L.circleMarker([Number(props.parcela.latitud), Number(props.parcela.longitud)], {
    radius: 12, color: '#fff', weight: 3, fillColor: sanidadDe(props.parcela).color, fillOpacity: 0.95,
  }).addTo(mapa);
}
</script>

<style scoped>
.pf {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
  gap: 20px;
}

.pf__principal,
.pf__lateral {
  display: grid;
  align-content: start;
  gap: 18px;
  min-width: 0;
}

.pf__datos {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pf__datos li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #f4f7f9;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
}

.pf__bloque {
  padding: 16px;
  background: #f8fafc;
  border-radius: 16px;
}

.pf__bloque h3 {
  margin: 0 0 10px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.pf__cultivo {
  margin: 0 0 12px;
  font-size: 14px;
}

.pf__dia {
  margin-left: 6px;
  padding: 2px 10px;
  border-radius: 999px;
  background: #edfaf4;
  color: #166534;
  font-size: 12px;
  font-weight: 800;
}

.pf__etapas {
  display: flex;
  gap: 4px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pf__etapa {
  margin: 0 0 8px;
  font-size: 13px;
  color: #4a6070;
}

.pf__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.pf__etapas li {
  flex: 1;
  min-width: 0;
}

.pf__etapas li span {
  height: 8px;
  border-radius: 999px;
  background: #dce6ed;
}

.pf__etapas li.is-pasada span {
  background: #a8e6c6;
}

.pf__etapas li.is-actual span {
  background: #1a5c3e;
}


.pf__tiempo {
  display: grid;
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pf__tiempo li {
  position: relative;
  display: flex;
  gap: 12px;
  padding-bottom: 16px;
}

.pf__tiempo li:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 15px;
  top: 32px;
  bottom: 0;
  width: 2px;
  background: #dce6ed;
}

.pf__punto {
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: #fff;
  font-size: 16px;
}

.pf__evento {
  min-width: 0;
  flex: 1;
}

.pf__evento-cabeza {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 14px;
}

.pf__evento-cabeza time {
  flex: none;
  font-size: 12px;
  color: #4a6070;
}

.pf__evento p {
  margin: 2px 0 6px;
  font-size: 13px;
  color: #4a6070;
}

.pf__evento-pie {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.pf__evento-pie img {
  width: 48px;
  height: 48px;
  object-fit: cover;
  border-radius: 10px;
}

.pf__mapa {
  height: 220px;
  border-radius: 16px;
  overflow: hidden;
  border: 2px solid #dce6ed;
}

.pf__sin-ubicacion {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 100%;
  margin: 0;
  background: #fffbeb;
  color: #92400e;
  font-size: 13px;
  font-weight: 700;
}

.pf__produccion {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pf__produccion li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
}

.pf__produccion small {
  font-weight: 600;
  color: #4a6070;
}

.pf__ultimo {
  margin: 0 0 8px;
  font-size: 14px;
}

@media (max-width: 900px) {
  .pf {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
