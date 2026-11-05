<template>
  <div class="cotizaciones-filtros-card">
    <div class="cotizaciones-filtros-head">
      <div>
        <h2>Listado de cotizaciones</h2>
        <p>
          Busca por número de cotización, productor o estado comercial.
        </p>
      </div>

      <button
        class="btn btn-s"
        type="button"
        :disabled="loading"
        @click="$emit('actualizar')"
      >
        <i class="ti ti-refresh"></i>
        {{ loading ? 'Actualizando...' : 'Actualizar' }}
      </button>
    </div>

    <div class="cotizaciones-filtros-body">
      <div class="cotizaciones-search">
        <i class="ti ti-search"></i>

        <input
          :value="busqueda"
          placeholder="Buscar cotización o productor..."
          @input="$emit('update:busqueda', $event.target.value)"
          @keyup.enter="$emit('buscar')"
        />
      </div>

      <select
        :value="estado"
        @change="$emit('update:estado', $event.target.value)"
      >
        <option value="">Todos los estados</option>
        <option value="Pendiente">Pendiente</option>
        <option value="Aprobada">Aprobada</option>
        <option value="Rechazada">Rechazada</option>
        <option value="Convertida">Convertida</option>
        <option value="Anulada">Anulada</option>
      </select>

      <button
        class="btn btn-p"
        type="button"
        @click="$emit('buscar')"
      >
        <i class="ti ti-filter"></i>
        Buscar
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
  busqueda: {
    type: String,
    default: '',
  },
  estado: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'update:busqueda',
  'update:estado',
  'buscar',
  'limpiar',
  'actualizar',
]);
</script>