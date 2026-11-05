<template>
  <article class="cultivo-card" :class="`cultivo-card--${estadoClase}`">
    <div class="cultivo-card-top">
      <div class="cultivo-icon">
        <i class="ti ti-seeding"></i>
      </div>

      <div class="cultivo-main">
        <h3>{{ cultivo.variedad_papa || 'Sin variedad registrada' }}</h3>
        <p>
          Parcela:
          <strong>{{ cultivo.parcela?.codigo_parcela || '—' }}</strong>
        </p>
      </div>
    </div>

    <div class="cultivo-chips">
      <span class="cultivo-chip">
        <i class="ti ti-user"></i>
        {{ nombreProductor }}
      </span>

      <span class="cultivo-chip">
        <i class="ti ti-calendar"></i>
        {{ formatDate(cultivo.fecha_siembra) }}
      </span>

      <span class="cultivo-chip" v-if="nombreEtapa">
        <i class="ti ti-plant-2"></i>
        {{ nombreEtapa }}
      </span>
    </div>

    <div class="cultivo-info-grid">
      <div class="cultivo-info">
        <small>Semilla</small>
        <strong>{{ formatKg(cultivo.cantidad_semilla_kg) }}</strong>
      </div>

      <div class="cultivo-info">
        <small>Procedencia</small>
        <strong>{{ cultivo.procedencia_semilla || 'No registrada' }}</strong>
      </div>
    </div>

    <div class="cultivo-footer">
      <span class="cultivo-status" :class="`cultivo-status--${estadoClase}`">
        {{ limpiarEstado(cultivo.estado) }}
      </span>

      <div class="cultivo-actions">
        <button type="button" class="cultivo-action view" title="Ver detalle" @click="$emit('view', cultivo)">
          <i class="ti ti-eye"></i>
        </button>

        <button type="button" class="cultivo-action edit" title="Editar" @click="$emit('edit', cultivo)">
          <i class="ti ti-pencil"></i>
        </button>

        <button type="button" class="cultivo-action status" title="Cambiar estado" @click="$emit('status', cultivo)">
          <i class="ti ti-refresh"></i>
        </button>
      </div>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  cultivo: {
    type: Object,
    required: true,
  },
});

defineEmits(['view', 'edit', 'status']);

const nombreProductor = computed(() => {
  const productor = props.cultivo?.parcela?.productor;

  if (!productor) {
    return 'Sin productor';
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
    .join(' ') || 'Sin productor';
});

const nombreEtapa = computed(() => {
  return props.cultivo?.etapa_fenologica?.nombre ||
    props.cultivo?.etapaFenologica?.nombre ||
    '';
});

const estadoClase = computed(() => {
  const estado = props.cultivo?.estado || '';

  if (estado === 'Activo') return 'activo';
  if (estado === 'Cosechado') return 'cosechado';
  if (estado === 'Perdido') return 'perdido';
  if (estado === 'Abandonado') return 'abandonado';

  return 'default';
});

function limpiarEstado(estado) {
  if (!estado) {
    return 'Sin estado';
  }

  return String(estado).replaceAll('_', ' ');
}

function formatDate(dateStr) {
  if (!dateStr) {
    return 'Sin fecha';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return dateStr;
  }

  return date.toLocaleDateString('es-BO', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatKg(value) {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  return `${Number(value).toFixed(2)} kg`;
}
</script>