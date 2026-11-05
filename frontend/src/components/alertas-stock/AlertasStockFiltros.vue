<template>
  <div class="inventario-panel-head alertas-stock-panel-head">
    <div>
      <h2>Listado de alertas</h2>
      <p>
        Revisa productos sin stock, bajo mínimo o con alerta preventiva.
      </p>
    </div>

    <div class="inventario-filters alertas-stock-filters">
      <div class="alertas-stock-search">
        <i class="ti ti-search"></i>

        <input
          :value="search"
          placeholder="Buscar producto, código o almacén..."
          @input="$emit('update:search', $event.target.value)"
          @keyup.enter="$emit('buscar')"
        />
      </div>

      <select
        :value="estado"
        @change="$emit('update:estado', $event.target.value)"
      >
        <option value="">Todos los estados</option>
        <option value="sin_stock">Sin stock</option>
        <option value="bajo_minimo">Bajo mínimo</option>
        <option value="alerta">Alerta preventiva</option>
      </select>

      <button
        class="inventario-btn-secondary"
        type="button"
        @click="$emit('buscar')"
      >
        <i class="ti ti-filter"></i>
        Buscar
      </button>

      <button
        class="inventario-btn-secondary"
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
  search: {
    type: String,
    default: '',
  },
  estado: {
    type: String,
    default: '',
  },
});

defineEmits([
  'update:search',
  'update:estado',
  'buscar',
  'limpiar',
]);
</script>