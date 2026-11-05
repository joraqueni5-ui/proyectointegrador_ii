<template>
  <div class="inventario-panel-head almacenes-panel-head">
    <div>
      <h2>Listado de almacenes</h2>
      <p>
        Filtra por nombre, ubicación, departamento o estado del almacén.
      </p>
    </div>

    <div class="inventario-filters almacenes-filters">
      <select :value="estado" @change="$emit('update:estado', $event.target.value)">
        <option value="">Todos los estados</option>
        <option value="Activo">Activos</option>
        <option value="Inactivo">Inactivos</option>
      </select>

      <select :value="departamento" @change="$emit('update:departamento', $event.target.value)">
        <option value="">Todos los departamentos</option>
        <option value="La Paz">La Paz</option>
        <option value="Cochabamba">Cochabamba</option>
        <option value="Santa Cruz">Santa Cruz</option>
        <option value="Oruro">Oruro</option>
        <option value="Potosí">Potosí</option>
        <option value="Chuquisaca">Chuquisaca</option>
        <option value="Tarija">Tarija</option>
        <option value="Beni">Beni</option>
        <option value="Pando">Pando</option>
      </select>

      <div class="almacenes-search">
        <i class="ti ti-search"></i>
        <input
          :value="search"
          placeholder="Buscar almacén, ubicación o responsable..."
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
  departamento: {
    type: String,
    default: '',
  },
});

defineEmits([
  'update:search',
  'update:estado',
  'update:departamento',
  'buscar',
  'limpiar',
]);
</script>