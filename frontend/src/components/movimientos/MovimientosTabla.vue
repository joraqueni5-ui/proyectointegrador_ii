<template>
  <div class="inventario-table-wrap movimientos-table-wrap">
    <table class="inventario-table movimientos-table">
      <thead>
        <tr>
          <th>Movimiento</th>
          <th>Producto</th>
          <th>Almacén</th>
          <th>Tipo</th>
          <th>Cantidad</th>
          <th>Stock</th>
          <th>Motivo</th>
          <th>Fecha</th>
          <th>Usuario</th>
          <th class="inventario-right">Acciones</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="movimiento in movimientos" :key="movimiento.id">
          <td>
            <div class="movimiento-id-cell">
              <div
                class="movimiento-avatar"
                :class="movimiento.tipo === 'Entrada' ? 'entrada' : 'salida'"
              >
                <i :class="movimiento.tipo === 'Entrada' ? 'ti ti-arrow-down-to-arc' : 'ti ti-arrow-up-from-arc'"></i>
              </div>

              <div>
                <strong>#{{ movimiento.id }}</strong>
                <small>{{ formatDate(movimiento.fecha_movimiento) }}</small>
              </div>
            </div>
          </td>

          <td>
            <div class="movimiento-producto-cell">
              <strong>{{ movimiento.producto?.nombre_producto || '—' }}</strong>
              <small>{{ movimiento.producto?.codigo_producto || 'Sin código' }}</small>
            </div>
          </td>

          <td>
            <span class="inventario-badge inventario-bg-gray">
              {{ movimiento.almacen?.nombre_almacen || '—' }}
            </span>
          </td>

          <td>
            <span
              class="inventario-badge"
              :class="movimiento.tipo === 'Entrada' ? 'inventario-bg-blue' : 'inventario-bg-red'"
            >
              {{ movimiento.tipo }}
            </span>
          </td>

          <td>
            <div class="movimiento-cantidad-cell">
              <strong>{{ numero(movimiento.cantidad) }}</strong>
              <small>{{ movimiento.producto?.unidad_medida || '' }}</small>
            </div>
          </td>

          <td>
            <div class="movimiento-stock-cell">
              <small>Antes: {{ numero(movimiento.stock_anterior) }}</small>
              <strong>Después: {{ numero(movimiento.stock_posterior) }}</strong>
            </div>
          </td>

          <td>
            {{ textoMotivo(movimiento.motivo) }}
          </td>

          <td>
            {{ formatDate(movimiento.fecha_movimiento) }}
          </td>

          <td>
            {{ nombreUsuario(movimiento.usuario) }}
          </td>

          <td>
            <div class="inventario-actions movimientos-actions">
              <button
                class="movimiento-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('view', movimiento)"
              >
                <i class="ti ti-eye"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="movimientos.length === 0">
          <td colspan="10" class="inventario-empty movimientos-empty">
            <i class="ti ti-arrows-transfer-down"></i>
            <span>No existen movimientos registrados.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  movimientos: {
    type: Array,
    default: () => [],
  },
});

defineEmits(['view']);

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