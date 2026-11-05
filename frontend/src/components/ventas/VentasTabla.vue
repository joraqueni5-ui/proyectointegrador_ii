<template>
  <div class="ventas-table-wrap">
    <table class="ventas-table">
      <thead>
        <tr>
          <th>Factura</th>
          <th>Productor</th>
          <th>Fecha</th>
          <th>Método</th>
          <th>Total</th>
          <th>Estado</th>
          <th class="ventas-right">Acciones</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="venta in ventas" :key="venta.id">
          <td>
            <div class="venta-code-cell">
              <strong>#{{ venta.nro_factura }}</strong>
              <small>{{ venta.tipo || 'Venta' }}</small>
            </div>
          </td>

          <td>
            <div class="venta-productor-cell">
              <strong>{{ venta.productor?.nombre_completo || '—' }}</strong>
              <small>{{ venta.productor?.telefono || 'Sin teléfono' }}</small>
            </div>
          </td>

          <td>
            <div class="venta-date-cell">
              <strong>{{ formatDate(venta.fecha_emision) }}</strong>
              <small v-if="venta.fecha_vencimiento">
                Vence: {{ formatDate(venta.fecha_vencimiento) }}
              </small>
              <small v-else>Sin vencimiento</small>
            </div>
          </td>

          <td>
            <span class="venta-metodo">
              <i :class="iconoPago(venta.metodo_pago)"></i>
              {{ venta.metodo_pago || '—' }}
            </span>
          </td>

          <td>
            <div class="venta-total-cell">
              <strong>Bs {{ numero(venta.monto_total) }}</strong>
              <small>Desc: Bs {{ numero(venta.descuento) }}</small>
            </div>
          </td>

          <td>
            <span class="b" :class="badgeEstado(venta.estado)">
              {{ venta.estado || 'Sin estado' }}
            </span>
          </td>

          <td>
            <div class="ventas-actions">
              <button
                class="venta-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('ver', venta)"
              >
                <i class="ti ti-eye"></i>
              </button>

              <button
                class="venta-action print"
                type="button"
                title="Imprimir comprobante"
                @click="$emit('imprimir', venta)"
              >
                <i class="ti ti-printer"></i>
              </button>

              <button
                v-if="venta.estado !== 'Anulada'"
                class="venta-action cancel"
                type="button"
                title="Anular venta"
                :disabled="saving"
                @click="$emit('anular', venta)"
              >
                <i class="ti ti-ban"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="ventas.length === 0">
          <td colspan="7" class="ventas-empty">
            <i class="ti ti-receipt-off"></i>
            <span>No existen ventas registradas.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  ventas: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['ver', 'imprimir', 'anular']);

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
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

function badgeEstado(estado) {
  return {
    Pagada: 'bg',
    Pendiente: 'ba',
    Parcial: 'bb',
    Anulada: 'br',
  }[estado] || 'bgr';
}

function iconoPago(metodo) {
  return {
    Efectivo: 'ti ti-cash',
    Transferencia: 'ti ti-building-bank',
    QR: 'ti ti-qrcode',
    Credito: 'ti ti-credit-card',
    Otro: 'ti ti-dots',
  }[metodo] || 'ti ti-wallet';
}
</script>