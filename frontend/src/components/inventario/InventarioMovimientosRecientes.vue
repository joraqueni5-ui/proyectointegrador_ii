<template>
  <div class="inventario-panel inventario-dashboard-side-panel">
    <div class="inventario-panel-head inventario-dashboard-panel-head">
      <div>
        <h2>Últimos movimientos</h2>
        <p>Entradas y salidas registradas recientemente.</p>
      </div>

      <RouterLink
        to="/dashboard/inventario/movimientos"
        class="inventario-btn-secondary"
      >
        Ver todos
      </RouterLink>
    </div>

    <div v-if="movimientos.length" class="inventario-dashboard-mov-list">
      <div
        v-for="movimiento in movimientos"
        :key="movimiento.id"
        class="inventario-dashboard-mov-item"
      >
        <div
          class="inventario-dashboard-mov-icon"
          :class="movimiento.tipo === 'Entrada' ? 'entrada' : 'salida'"
        >
          <i :class="movimiento.tipo === 'Entrada' ? 'ti ti-arrow-down-to-arc' : 'ti ti-arrow-up-from-arc'"></i>
        </div>

        <div class="inventario-dashboard-mov-content">
          <div class="inventario-dashboard-mov-head">
            <strong>{{ movimiento.producto?.nombre_producto || '—' }}</strong>

            <span
              class="inventario-badge"
              :class="movimiento.tipo === 'Entrada' ? 'inventario-bg-blue' : 'inventario-bg-red'"
            >
              {{ movimiento.tipo }}
            </span>
          </div>

          <p>
            {{ numero(movimiento.cantidad) }}
            {{ movimiento.producto?.unidad_medida || '' }}
            · {{ textoMotivo(movimiento.motivo) }}
          </p>

          <small>
            {{ formatDate(movimiento.fecha_movimiento) }}
            · {{ movimiento.almacen?.nombre_almacen || 'Sin almacén' }}
            · {{ nombreUsuario(movimiento.usuario) }}
          </small>
        </div>

        <div class="inventario-dashboard-mov-stock">
          <span>Antes: {{ numero(movimiento.stock_anterior) }}</span>
          <strong>Después: {{ numero(movimiento.stock_posterior) }}</strong>
        </div>
      </div>
    </div>

    <div v-else class="inventario-dashboard-empty small">
      <i class="ti ti-arrows-transfer-down"></i>
      <span>No existen movimientos registrados.</span>
    </div>
  </div>
</template>

<script setup>
defineProps({
  movimientos: {
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

function textoMotivo(motivo) {
  return {
    Compra: 'Compra',
    Venta: 'Venta',
    Devolucion: 'Devolución',
    Ajuste_entrada: 'Ajuste entrada',
    Ajuste_salida: 'Ajuste salida',
    Traslado_entrada: 'Traslado entrada',
    Traslado_salida: 'Traslado salida',
    Otro: 'Otro',
  }[motivo] || motivo;
}

function nombreUsuario(usuario) {
  if (!usuario) {
    return 'Sistema';
  }

  return [
    usuario.name,
    usuario.paterno,
    usuario.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
}

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