<template>
  <div class="inventario-panel inventario-dashboard-side-panel">
    <div class="inventario-panel-head inventario-dashboard-panel-head">
      <div>
        <h2>Alertas recientes</h2>
        <p>Productos sin stock, bajo mínimo o en nivel preventivo.</p>
      </div>

      <RouterLink
        to="/dashboard/inventario/alertas-stock"
        class="inventario-btn-secondary"
      >
        Ver alertas
      </RouterLink>
    </div>

    <div v-if="alertas.length" class="inventario-dashboard-alert-list">
      <div
        v-for="item in alertas.slice(0, 5)"
        :key="item.id"
        class="inventario-dashboard-alert-item"
        :class="item.estado"
      >
        <div class="inventario-dashboard-alert-icon">
          <i :class="iconoEstado(item.estado)"></i>
        </div>

        <div class="inventario-dashboard-alert-content">
          <div class="inventario-dashboard-alert-head">
            <strong>{{ item.producto?.nombre_producto || '—' }}</strong>

            <span
              class="inventario-badge"
              :class="badgeInventario(item.estado)"
            >
              {{ textoEstadoInventario(item.estado) }}
            </span>
          </div>

          <p>
            Stock actual:
            <strong>
              {{ numero(item.stock_actual) }}
              {{ item.producto?.unidad_medida || '' }}
            </strong>
          </p>

          <small>
            Mínimo:
            {{ numero(item.stock_minimo) }}
            {{ item.producto?.unidad_medida || '' }}
            · {{ item.almacen?.nombre_almacen || 'Sin almacén' }}
          </small>
        </div>
      </div>
    </div>

    <div v-else class="inventario-dashboard-empty small">
      <i class="ti ti-circle-check"></i>
      <span>No existen alertas de stock.</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  alertas: {
    type: Array,
    default: () => [],
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

function iconoEstado(estado) {
  return {
    normal: 'ti ti-circle-check',
    alerta: 'ti ti-alert-triangle',
    bajo_minimo: 'ti ti-alert-triangle',
    sin_stock: 'ti ti-package-off',
  }[estado] || 'ti ti-alert-circle';
}
</script>