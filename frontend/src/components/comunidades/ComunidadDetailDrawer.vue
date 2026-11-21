<template>
  <DetailDrawer
    :model-value="modelValue"
    :titulo="comunidad?.nombre_comunidad || ''"
    :subtitulo="subtitulo"
    @update:model-value="(v) => $emit('update:modelValue', v)"
  >
    <template #estado>
      <StatusBadge v-if="comunidad" :estado="comunidad.activo ? 'Activa' : 'Inactiva'" />
    </template>

    <template v-if="comunidad">
      <DetailBlock
        titulo="Ubicación"
        :datos="[
          { etiqueta: 'Departamento', valor: comunidad.departamento },
          { etiqueta: 'Provincia', valor: comunidad.provincia },
          { etiqueta: 'Municipio', valor: comunidad.municipio },
          { etiqueta: 'Altitud', valor: comunidad.altitud_msnm ? altitud(comunidad.altitud_msnm) : '' },
        ]"
      />

      <section v-if="tieneUbicacion" class="cd-mapa" aria-label="Mapa de la comunidad">
        <MapaBase :centro="[Number(comunidad.latitud), Number(comunidad.longitud)]" :zoom="13" :selector-capa="false" @listo="dibujar" />
      </section>
      <p v-else class="cd-sin-ubicacion">
        <i class="ti ti-map-pin-off" aria-hidden="true"></i> Sin ubicación · completar
      </p>

      <DetailBlock
        titulo="Resumen"
        :datos="[
          { etiqueta: 'Productores', valor: numero(comunidad.productores_count ?? comunidad.productores?.length ?? 0, { decimales: 0 }) },
          { etiqueta: 'Parcelas', valor: numero(comunidad.parcelas_count ?? 0, { decimales: 0 }) },
          { etiqueta: 'Superficie', valor: hectareas(comunidad.superficie_total_ha ?? 0) },
          { etiqueta: 'Parcelas con alerta', valor: numero(comunidad.parcelas_con_alerta ?? 0, { decimales: 0 }) },
        ]"
      />

      <section class="cd-productores">
        <h3>Productores</h3>
        <ul v-if="productores.length">
          <li v-for="p in productores" :key="p.id">{{ nombreProductor(p) }}</li>
        </ul>
        <p v-else class="texto-secundario">Todavía no hay productores en esta comunidad.</p>
      </section>
    </template>

    <template #acciones>
      <BaseButton variante="secundario" @click="$emit('update:modelValue', false)">Cerrar</BaseButton>
      <BaseButton variante="secundario" :icono="comunidad?.activo ? 'ti-circle-off' : 'ti-circle-check'" @click="$emit('cambiar-estado', comunidad)">
        {{ comunidad?.activo ? 'Desactivar' : 'Activar' }}
      </BaseButton>
      <BaseButton icono="ti-pencil" @click="$emit('editar', comunidad)">Editar</BaseButton>
    </template>
  </DetailDrawer>
</template>

<script setup>
import { computed } from 'vue';
import DetailDrawer from '@/components/ui/DetailDrawer.vue';
import DetailBlock from '@/components/ui/DetailBlock.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import MapaBase from '@/components/mapa/MapaBase.vue';
import { altitud, hectareas, numero } from '@/utils/formato';

defineEmits(['update:modelValue', 'editar', 'cambiar-estado']);

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  comunidad: { type: Object, default: null },
});

const subtitulo = computed(() => [props.comunidad?.municipio, props.comunidad?.provincia].filter(Boolean).join(' · '));
const productores = computed(() => props.comunidad?.productores || []);
const tieneUbicacion = computed(
  () => props.comunidad && props.comunidad.latitud !== null && props.comunidad.latitud !== '' && props.comunidad.longitud !== null && props.comunidad.longitud !== ''
);

function nombreProductor(p) {
  return p.nombre_completo || [p.nombres, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(' ');
}

function dibujar(mapa, L) {
  L.circleMarker([Number(props.comunidad.latitud), Number(props.comunidad.longitud)], {
    radius: 10,
    color: '#fff',
    weight: 3,
    fillColor: '#1a5c3e',
    fillOpacity: 1,
  }).addTo(mapa);
}
</script>

<style scoped>
.cd-mapa {
  height: 220px;
  border-radius: 16px;
  overflow: hidden;
  border: 2px solid #dce6ed;
}

.cd-sin-ubicacion {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 12px 14px;
  background: #fffbeb;
  color: #92400e;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
}

.cd-productores h3 {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.cd-productores ul {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.cd-productores li {
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
}
</style>
