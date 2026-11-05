<template>
  <div class="ventas-filtros-card">
    <div class="ventas-filtros-head">
      <div>
        <h2>Historial de ventas</h2>
        <p>
          Busca ventas por número de factura, productor, método de pago o estado.
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

    <div class="ventas-filtros-body">
      <div class="ventas-search">
        <i class="ti ti-search"></i>

        <input
          :value="busqueda"
          placeholder="Buscar factura o productor..."
          @input="$emit('update:busqueda', $event.target.value)"
          @keyup.enter="$emit('buscar')"
        />
      </div>

      <select
        :value="estado"
        @change="$emit('update:estado', $event.target.value)"
      >
        <option value="">Todos los estados</option>
        <option value="Pagada">Pagada</option>
        <option value="Pendiente">Pendiente</option>
        <option value="Parcial">Parcial</option>
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