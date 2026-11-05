<template>
  <div class="inventario-panel-head productos-panel-head">
    <div>
      <h2>Listado de productos</h2>
      <p>
        Filtra por categoría, nombre o código. El stock se calcula desde inventario.
      </p>
    </div>

    <div class="inventario-filters productos-filters">
      <select :value="categoria" @change="$emit('update:categoria', $event.target.value)">
        <option value="">Todas las categorías</option>
        <option value="Semilla">Semilla</option>
        <option value="Bioinsumo">Bioinsumo</option>
        <option value="Agroquimico">Agroquímico</option>
        <option value="Fertilizante">Fertilizante</option>
        <option value="Equipo">Equipo</option>
        <option value="Otro">Otro</option>
      </select>

      <div class="productos-search">
        <i class="ti ti-search"></i>
        <input
          :value="search"
          placeholder="Buscar por código, nombre o categoría..."
          @input="$emit('update:search', $event.target.value)"
          @keyup.enter="$emit('buscar')"
        />
      </div>

      <button class="inventario-btn-secondary" type="button" @click="$emit('buscar')">
        <i class="ti ti-filter"></i>
        Buscar
      </button>

      <button class="inventario-btn-secondary" type="button" @click="$emit('limpiar')">
        <i class="ti ti-eraser"></i>
        Limpiar
      </button>

      <button
        class="inventario-btn-secondary"
        type="button"
        :disabled="exporting"
        @click="$emit('excel')"
      >
        <i class="ti ti-file-spreadsheet"></i>
        Excel
      </button>

      <button
        class="inventario-btn-secondary"
        type="button"
        :disabled="exporting"
        @click="$emit('pdf')"
      >
        <i class="ti ti-file-type-pdf"></i>
        PDF
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
  categoria: {
    type: String,
    default: '',
  },
  exporting: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'update:search',
  'update:categoria',
  'buscar',
  'limpiar',
  'excel',
  'pdf',
]);
</script>