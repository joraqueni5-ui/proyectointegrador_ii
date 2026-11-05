<template>
  <div class="inventario-dashboard-stock-section">
    <div class="inventario-dashboard-section-title">
      <div>
        <h2>{{ titulo }}</h2>
        <p>{{ descripcion }}</p>
      </div>

      <span>{{ items.length }} registros</span>
    </div>

    <div v-if="items.length" class="inventario-dashboard-stock-grid">
      <div
        v-for="item in items"
        :key="item.id"
        class="inventario-dashboard-stock-card"
      >
        <div class="inventario-dashboard-stock-head">
          <div>
            <strong>{{ item.producto?.nombre_producto || '—' }}</strong>
            <small>{{ item.producto?.codigo_producto || 'Sin código' }}</small>
          </div>

          <span
            class="inventario-badge"
            :class="badgeInventario(item.estado)"
          >
            {{ textoEstadoInventario(item.estado) }}
          </span>
        </div>

        <div class="inventario-dashboard-stock-value">
          {{ numero(item.stock_actual) }}
          <small>{{ item.producto?.unidad_medida || '' }}</small>
        </div>

        <div class="inventario-dashboard-progress">
          <div
            :class="item.estado"
            :style="{ width: `${Math.min(item.porcentaje || 0, 100)}%` }"
          ></div>
        </div>

        <div class="inventario-dashboard-stock-meta">
          <span>
            Mín:
            <strong>{{ numero(item.stock_minimo) }}</strong>
            {{ item.producto?.unidad_medida || '' }}
          </span>

          <span>
            {{ item.almacen?.nombre_almacen || 'Sin almacén' }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="inventario-dashboard-empty">
      <i class="ti ti-package-off"></i>
      <span>{{ emptyText }}</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  titulo: {
    type: String,
    default: 'Stock',
  },
  descripcion: {
    type: String,
    default: 'Resumen de stock por categoría.',
  },
  items: {
    type: Array,
    default: () => [],
  },
  emptyText: {
    type: String,
    default: 'No existen registros de stock.',
  },
});

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function badgeInventario(estado) {
  return {
    normal: 'inventario-bg-green',
    alerta: 'inventario-bg-yellow',
    bajo_minimo: 'inventario-bg-yellow',
    sin_stock: 'inventario-bg-red',
  }[estado] || 'inventario-bg-gray';
}

function textoEstadoInventario(estado) {
  return {
    normal: 'Normal',
    alerta: 'Alerta',
    bajo_minimo: 'Bajo mínimo',
    sin_stock: 'Sin stock',
  }[estado] || 'Sin estado';
}
</script>