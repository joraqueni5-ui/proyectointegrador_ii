<template>
  <DetailDrawer
    :model-value="modelValue"
    :titulo="nombre"
    :codigo="ci"
    :subtitulo="subtitulo"
    @update:model-value="(v) => $emit('update:modelValue', v)"
  >
    <template #estado>
      <StatusBadge v-if="productor" :estado="productor.activo ? 'Activo' : 'Inactivo'" />
    </template>

    <template v-if="productor">
      <DetailBlock
        titulo="Datos personales"
        :datos="[
          { etiqueta: 'Nombre completo', valor: productor.nombre_completo, completo: true },
          { etiqueta: 'Carnet de identidad', valor: ci },
          { etiqueta: 'Teléfono', valor: productor.telefono },
          { etiqueta: 'Sexo', valor: textoSexo },
          { etiqueta: 'Fecha de nacimiento', valor: productor.fecha_nacimiento ? fecha(productor.fecha_nacimiento) : '' },
          { etiqueta: 'Dirección', valor: productor.direccion, completo: true },
        ]"
      />

      <DetailBlock
        titulo="Datos agrícolas"
        :datos="[
          { etiqueta: 'Superficie declarada', valor: productor.superficie_total_ha !== null && productor.superficie_total_ha !== '' ? hectareas(productor.superficie_total_ha) : 'Sin declarar' },
          { etiqueta: 'En parcelas', valor: hectareas(usada) },
          { etiqueta: 'Disponible', valor: disponible },
          { etiqueta: 'Parcelas', valor: numero(parcelas.length, { decimales: 0 }) },
        ]"
      />

      <section class="pd-seccion">
        <h3>Parcelas</h3>
        <ul v-if="parcelas.length" class="pd-lista">
          <li v-for="p in parcelas" :key="p.id">
            <RouterLink :to="{ path: '/dashboard/parcelas', query: { buscar: p.codigo_parcela } }" class="pd-fila" @click="$emit('update:modelValue', false)">
              <span class="pd-fila__texto">
                <strong>{{ p.nombre_visible || p.codigo_parcela }}</strong>
                <small><span class="codigo">{{ p.codigo_parcela }}</span> · {{ hectareas(p.superficie_ha) }}</small>
              </span>
              <span class="pd-fila__estado">
                <StatusBadge v-if="p.alertas_activas > 0" tono="naranja" :texto="`${p.alertas_activas} ${p.alertas_activas === 1 ? 'alerta' : 'alertas'}`" />
                <StatusBadge :estado="p.estado" />
              </span>
            </RouterLink>
          </li>
        </ul>
        <p v-else class="texto-secundario">Todavía no tiene parcelas registradas.</p>
      </section>

      <section v-if="productor.facturas" class="pd-seccion">
        <h3>Últimas compras</h3>
        <ul v-if="productor.facturas.length" class="pd-lista">
          <li v-for="f in productor.facturas" :key="f.id" class="pd-fila pd-fila--plana">
            <span class="pd-fila__texto">
              <strong>{{ f.nro_factura }}</strong>
              <small>{{ fecha(f.fecha_emision) }}</small>
            </span>
            <span class="pd-fila__estado">
              <strong>{{ bolivianos(f.monto_total) }}</strong>
              <StatusBadge :estado="f.estado" />
            </span>
          </li>
        </ul>
        <p v-else class="texto-secundario">Todavía no tiene compras registradas.</p>
      </section>

      <DetailBlock v-if="productor.observaciones" titulo="Observaciones" :datos="[{ etiqueta: 'Notas', valor: productor.observaciones, completo: true }]" />
    </template>

    <template #acciones>
      <BaseButton variante="secundario" @click="$emit('update:modelValue', false)">Cerrar</BaseButton>
      <BaseButton variante="secundario" :icono="productor?.activo ? 'ti-circle-off' : 'ti-circle-check'" @click="$emit('cambiar-estado', productor)">
        {{ productor?.activo ? 'Desactivar' : 'Activar' }}
      </BaseButton>
      <BaseButton icono="ti-pencil" @click="$emit('editar', productor)">Editar</BaseButton>
    </template>
  </DetailDrawer>
</template>

<script setup>
import { computed } from 'vue';
import DetailDrawer from '@/components/ui/DetailDrawer.vue';
import DetailBlock from '@/components/ui/DetailBlock.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { bolivianos, fecha, hectareas, numero } from '@/utils/formato';

defineEmits(['update:modelValue', 'editar', 'cambiar-estado']);

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  productor: { type: Object, default: null },
});

const nombre = computed(() => props.productor?.nombre_completo || '');
const ci = computed(() => {
  const p = props.productor;

  if (!p) return '';

  const numeroCi = `${p.carnet_identidad}${p.complemento_ci ? `-${p.complemento_ci}` : ''}`;

  return `CI ${numeroCi} ${p.ci_extension || ''}`.trim();
});
const subtitulo = computed(() => [props.productor?.comunidad?.nombre_comunidad, props.productor?.comunidad?.municipio].filter(Boolean).join(' · '));
const parcelas = computed(() => props.productor?.parcelas || []);
const usada = computed(() => parcelas.value.reduce((s, p) => s + Number(p.superficie_ha || 0), 0));
const disponible = computed(() => {
  const d = props.productor?.superficie_total_ha;

  return d === null || d === undefined || d === '' ? '—' : hectareas(Math.max(0, Number(d) - usada.value));
});
const textoSexo = computed(() => ({ M: 'Masculino', F: 'Femenino', Otro: 'Otro' })[props.productor?.sexo] || '');
</script>

<style scoped>
.pd-seccion h3 {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.pd-lista {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.pd-fila {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  background: #f8fafc;
  border-radius: 12px;
  color: inherit;
  text-decoration: none;
}

a.pd-fila:hover {
  background: #edfaf4;
}

.pd-fila__texto {
  display: grid;
  min-width: 0;
}

.pd-fila__texto strong {
  font-size: 14px;
}

.pd-fila__texto small {
  font-size: 12px;
  color: #4a6070;
}

.pd-fila__estado {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
}
</style>
