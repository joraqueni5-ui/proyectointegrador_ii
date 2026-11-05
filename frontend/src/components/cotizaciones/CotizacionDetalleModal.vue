<template>
  <div class="modal-overlay cotizacion-modal-overlay" @click.self="close">
    <div class="modal-card cotizacion-detalle-modal">
      <div class="cotizacion-detalle-header" :class="estadoClass(cotizacion?.estado)">
        <div class="cotizacion-detalle-icon">
          <i class="ti ti-file-invoice"></i>
        </div>

        <div>
          <span>Detalle de cotización</span>
          <h3>{{ cotizacion?.nro_cotizacion || `Cotización #${cotizacion?.id || '—'}` }}</h3>
          <p>
            Información comercial, productos cotizados y estado actual.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="cotizacion-detalle-body" v-if="cotizacion">
        <div class="cotizacion-detalle-grid">
          <div class="cotizacion-info-card">
            <label>Productor</label>
            <p>{{ cotizacion.productor?.nombre_completo || '—' }}</p>
            <small>{{ cotizacion.productor?.telefono || 'Sin teléfono' }}</small>
          </div>

          <div class="cotizacion-info-card">
            <label>Fecha emisión</label>
            <p>{{ formatDate(cotizacion.fecha_emision) }}</p>
            <small>
              Vencimiento: {{ formatDate(cotizacion.fecha_vencimiento) }}
            </small>
          </div>

          <div class="cotizacion-info-card">
            <label>Método de pago</label>
            <p>{{ cotizacion.metodo_pago || '—' }}</p>
            <small>Modalidad comercial</small>
          </div>

          <div class="cotizacion-info-card">
            <label>Estado</label>
            <p>{{ cotizacion.estado || '—' }}</p>
            <small>Situación actual</small>
          </div>

          <div class="cotizacion-info-card">
            <label>Subtotal</label>
            <p>Bs {{ numero(cotizacion.subtotal) }}</p>
            <small>Antes de descuento</small>
          </div>

          <div class="cotizacion-info-card">
            <label>Descuento</label>
            <p>Bs {{ numero(cotizacion.descuento) }}</p>
            <small>Descuento general</small>
          </div>

          <div class="cotizacion-info-card total">
            <label>Total</label>
            <p>Bs {{ numero(cotizacion.monto_total) }}</p>
            <small>Monto final cotizado</small>
          </div>

          <div class="cotizacion-info-card">
            <label>Registrado por</label>
            <p>{{ nombreUsuario(cotizacion.usuario) }}</p>
            <small>Usuario responsable</small>
          </div>
        </div>

        <div
          v-if="cotizacion.observaciones"
          class="cotizacion-detail-section"
        >
          <h4>
            <i class="ti ti-notes"></i>
            Observaciones
          </h4>

          <p>{{ cotizacion.observaciones }}</p>
        </div>

        <div
          v-if="cotizacion.motivo_rechazo"
          class="cotizacion-detail-section rechazo"
        >
          <h4>
            <i class="ti ti-alert-triangle"></i>
            Motivo de rechazo
          </h4>

          <p>{{ cotizacion.motivo_rechazo }}</p>
        </div>

        <div
          v-if="cotizacion.factura"
          class="cotizacion-detail-section venta"
        >
          <h4>
            <i class="ti ti-receipt"></i>
            Venta generada
          </h4>

          <p>
            Esta cotización fue convertida en venta/factura:
            <strong>#{{ cotizacion.factura.nro_factura }}</strong>
          </p>
        </div>

        <div class="cotizacion-detail-section productos">
          <h4>
            <i class="ti ti-package"></i>
            Productos cotizados
          </h4>

          <div class="cotizacion-productos-table-wrap">
            <table class="cotizacion-productos-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Almacén</th>
                  <th>Cantidad</th>
                  <th>Precio</th>
                  <th>Subtotal</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="detalle in cotizacion.detalles || []"
                  :key="detalle.id"
                >
                  <td>
                    <strong>{{ detalle.producto?.nombre_producto || '—' }}</strong>
                    <small>{{ detalle.producto?.codigo_producto || 'Sin código' }}</small>
                  </td>

                  <td>
                    {{ detalle.almacen?.nombre_almacen || '—' }}
                  </td>

                  <td>
                    {{ numero(detalle.cantidad) }}
                    {{ detalle.producto?.unidad_medida || '' }}
                  </td>

                  <td>
                    Bs {{ numero(detalle.precio_unitario) }}
                  </td>

                  <td>
                    <strong>Bs {{ numero(detalle.subtotal) }}</strong>
                  </td>
                </tr>

                <tr v-if="!cotizacion.detalles?.length">
                  <td colspan="5" class="cotizaciones-empty small">
                    No existen productos en esta cotización.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  cotizacion: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

function close() {
  emit('close');
}

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

function nombreUsuario(usuario) {
  if (!usuario) {
    return '—';
  }

  return [
    usuario.name,
    usuario.paterno,
    usuario.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
}

function estadoClass(estado) {
  return {
    Pendiente: 'pendiente',
    Aprobada: 'aprobada',
    Rechazada: 'rechazada',
    Convertida: 'convertida',
    Anulada: 'anulada',
  }[estado] || 'pendiente';
}
</script>