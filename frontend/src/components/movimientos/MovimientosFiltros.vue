<template>
  <div class="inventario-panel-head movimientos-panel-head">
    <div>
      <h2>Historial de movimientos</h2>
      <p>
        Filtra por producto, almacén o tipo de movimiento.
      </p>
    </div>

    <div class="inventario-filters movimientos-filters">
      <select
        :value="idProducto"
        @change="$emit('update:idProducto', $event.target.value)"
      >
        <option value="">Todos los productos</option>
        <option
          v-for="producto in productos"
          :key="producto.id"
          :value="producto.id"
        >
          {{ producto.nombre_producto }}
        </option>
      </select>

      <select
        :value="idAlmacen"
        @change="$emit('update:idAlmacen', $event.target.value)"
      >
        <option value="">Todos los almacenes</option>
        <option
          v-for="almacen in almacenes"
          :key="almacen.id"
          :value="almacen.id"
        >
          {{ almacen.nombre_almacen }}
        </option>
      </select>

      <select
        :value="tipo"
        @change="$emit('update:tipo', $event.target.value)"
      >
        <option value="">Todos</option>
        <option value="Entrada">Entradas</option>
        <option value="Salida">Salidas</option>
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
  productos: {
    type: Array,
    default: () => [],
  },
  almacenes: {
    type: Array,
    default: () => [],
  },
  idProducto: {
    type: [String, Number],
    default: '',
  },
  idAlmacen: {
    type: [String, Number],
    default: '',
  },
  tipo: {
    type: String,
    default: '',
  },
});

defineEmits([
  'update:idProducto',
  'update:idAlmacen',
  'update:tipo',
  'buscar',
  'limpiar',
]);
</script>