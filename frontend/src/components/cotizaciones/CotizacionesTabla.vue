<template>
  <div class="cotizaciones-table-wrap">
    <table class="cotizaciones-table">
      <thead>
        <tr>
          <th>Cotización</th>
          <th>Productor</th>
          <th>Fecha</th>
          <th>Método pago</th>
          <th>Total</th>
          <th>Estado</th>
          <th class="cotizaciones-right">Acciones</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="cotizacion in cotizaciones" :key="cotizacion.id">
          <td>
            <div class="cotizacion-code-cell">
              <strong>{{ cotizacion.nro_cotizacion || `COT-${cotizacion.id}` }}</strong>
              <small>{{ cotizacion.detalles?.length || 0 }} producto(s)</small>
            </div>
          </td>

          <td>
            <div class="cotizacion-productor-cell">
              <strong>{{ cotizacion.productor?.nombre_completo || '—' }}</strong>
              <small>{{ cotizacion.productor?.telefono || 'Sin teléfono' }}</small>
            </div>
          </td>

          <td>
            <div class="cotizacion-date-cell">
              <strong>{{ formatDate(cotizacion.fecha_emision) }}</strong>
              <small v-if="cotizacion.fecha_vencimiento">
                Vence: {{ formatDate(cotizacion.fecha_vencimiento) }}
              </small>
              <small v-else>Sin vencimiento</small>
            </div>
          </td>

          <td>
            <span class="cotizacion-metodo">
              <i :class="iconoPago(cotizacion.metodo_pago)"></i>
              {{ cotizacion.metodo_pago || '—' }}
            </span>
          </td>

          <td>
            <div class="cotizacion-total-cell">
              <strong>Bs {{ numero(cotizacion.monto_total) }}</strong>
              <small>Desc: Bs {{ numero(cotizacion.descuento) }}</small>
            </div>
          </td>

          <td>
            <span class="b" :class="badgeEstado(cotizacion.estado)">
              {{ cotizacion.estado || 'Sin estado' }}
            </span>
          </td>

          <td>
            <div class="cotizaciones-actions">
              <button
                class="cotizacion-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('ver', cotizacion)"
              >
                <i class="ti ti-eye"></i>
              </button>

              <button
                v-if="cotizacion.estado === 'Pendiente'"
                class="cotizacion-action approve"
                type="button"
                title="Aprobar"
                :disabled="saving"
                @click="$emit('aprobar', cotizacion)"
              >
                <i class="ti ti-thumb-up"></i>
              </button>

              <button
                v-if="cotizacion.estado === 'Pendiente'"
                class="cotizacion-action reject"
                type="button"
                title="Rechazar"
                :disabled="saving"
                @click="$emit('rechazar', cotizacion)"
              >
                <i class="ti ti-circle-x"></i>
              </button>

              <button
                v-if="cotizacion.estado === 'Aprobada'"
                class="cotizacion-action convert"
                type="button"
                title="Convertir a venta"
                :disabled="saving"
                @click="$emit('convertir', cotizacion)"
              >
                <i class="ti ti-receipt"></i>
              </button>

              <button
                v-if="['Pendiente', 'Aprobada', 'Rechazada'].includes(cotizacion.estado)"
                class="cotizacion-action cancel"
                type="button"
                title="Anular"
                :disabled="saving"
                @click="$emit('anular', cotizacion)"
              >
                <i class="ti ti-ban"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="cotizaciones.length === 0">
          <td colspan="7" class="cotizaciones-empty">
            <i class="ti ti-file-invoice"></i>
            <span>No existen cotizaciones registradas.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  cotizaciones: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'ver',
  'aprobar',
  'rechazar',
  'convertir',
  'anular',
]);

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
    Pendiente: 'ba',
    Aprobada: 'bb',
    Rechazada: 'br',
    Convertida: 'bg',
    Anulada: 'bgr',
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