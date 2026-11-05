<template>
  <div class="inventario-table-wrap alertas-stock-table-wrap">
    <table class="inventario-table alertas-stock-table">
      <thead>
        <tr>
          <th>Producto</th>
          <th>Categoría</th>
          <th>Almacén</th>
          <th>Stock actual</th>
          <th>Stock mínimo</th>
          <th>Estado</th>
          <th class="inventario-right">Acción</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in alertas" :key="item.id">
          <td>
            <div class="alerta-producto-cell">
              <div class="alerta-producto-icon" :class="item.estado">
                <i :class="iconoEstado(item.estado)"></i>
              </div>

              <div>
                <strong>{{ item.producto?.nombre_producto || '—' }}</strong>
                <small>{{ item.producto?.codigo_producto || 'Sin código' }}</small>
              </div>
            </div>
          </td>

          <td>
            <span class="inventario-badge inventario-bg-blue">
              {{ item.producto?.categoria || '—' }}
            </span>
          </td>

          <td>
            <span class="alerta-almacen">
              {{ item.almacen?.nombre_almacen || 'Sin almacén asignado' }}
            </span>
          </td>

          <td>
            <div class="alerta-stock-cell">
              <strong :class="item.stock_actual <= 0 ? 'alertas-stock-text-red' : 'alertas-stock-text-amber'">
                {{ numero(item.stock_actual) }}
              </strong>
              <small>{{ item.producto?.unidad_medida || '' }}</small>
            </div>
          </td>

          <td>
            <div class="alerta-stock-cell">
              <strong>{{ numero(item.stock_minimo) }}</strong>
              <small>{{ item.producto?.unidad_medida || '' }}</small>
            </div>
          </td>

          <td>
            <span class="inventario-badge" :class="badgeInventario(item.estado)">
              {{ textoEstadoInventario(item.estado) }}
            </span>
          </td>

          <td>
            <div class="inventario-actions alertas-stock-actions">
              <button
                class="alerta-action reponer"
                type="button"
                title="Registrar entrada o ajuste"
                @click="$emit('reponer', item)"
              >
                <i class="ti ti-plus"></i>
                Reponer
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="alertas.length === 0">
          <td colspan="7" class="inventario-empty alertas-stock-empty">
            <i class="ti ti-circle-check"></i>
            <span>No existen alertas de stock.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  alertas: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['reponer']);

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
    alerta: 'Alerta preventiva',
    bajo_minimo: 'Bajo mínimo',
    sin_stock: 'Sin stock',
  }[estado] || 'Sin estado';
}

function iconoEstado(estado) {
  return {
    alerta: 'ti ti-bell-ringing',
    bajo_minimo: 'ti ti-alert-triangle',
    sin_stock: 'ti ti-package-off',
  }[estado] || 'ti ti-alert-circle';
}
</script>