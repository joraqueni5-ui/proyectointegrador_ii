<template>
  <div class="diagnosticos-panel-head">
    <div>
      <h2>Historial de diagnósticos</h2>
      <p>
        Filtra los análisis por inspección, estado de validación o búsqueda general.
      </p>
    </div>

    <div class="diagnosticos-filters">
      <div class="diagnosticos-search">
        <i class="ti ti-search"></i>

        <input
          :value="search"
          placeholder="Buscar enfermedad, parcela o productor..."
          @input="$emit('update:search', $event.target.value)"
          @keyup.enter="$emit('buscar')"
        />
      </div>

      <select
        :value="idInspeccion"
        @change="$emit('update:idInspeccion', $event.target.value)"
      >
        <option value="">Todas las inspecciones</option>

        <option
          v-for="inspeccion in inspecciones"
          :key="inspeccion.id"
          :value="inspeccion.id"
        >
          #{{ inspeccion.id }}
          —
          {{ inspeccion.parcela?.codigo_parcela || 'Sin parcela' }}
          <template v-if="inspeccion.fecha_visita">
            ({{ formatDate(inspeccion.fecha_visita) }})
          </template>
        </option>
      </select>

      <select
        :value="estadoValidacion"
        @change="$emit('update:estadoValidacion', $event.target.value)"
      >
        <option value="">Todos los estados</option>
        <option value="Pendiente">Pendiente</option>
        <option value="Confirmado">Confirmado</option>
        <option value="Corregido">Corregido</option>
        <option value="Rechazado">Rechazado</option>
      </select>

      <button
        class="btn btn-s"
        type="button"
        @click="$emit('buscar')"
      >
        <i class="ti ti-filter"></i>
        Filtrar
      </button>

      <button
        class="btn btn-s"
        type="button"
        @click="$emit('limpiar')"
      >
        <i class="ti ti-eraser"></i>
        Limpiar
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  inspecciones: {
    type: Array,
    default: () => [],
  },
  search: {
    type: String,
    default: '',
  },
  idInspeccion: {
    type: [String, Number],
    default: '',
  },
  estadoValidacion: {
    type: String,
    default: '',
  },
});

defineEmits([
  'update:search',
  'update:idInspeccion',
  'update:estadoValidacion',
  'buscar',
  'limpiar',
]);

function formatDate(fecha) {
  if (!fecha) {
    return '—';
  }

  return new Date(fecha).toLocaleDateString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}
</script>