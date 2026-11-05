<template>
  <div class="modal-overlay venta-modal-overlay" @click.self="close">
    <div class="modal-card venta-detalle-modal">
      <div class="venta-detalle-header" :class="estadoClass(venta?.estado)">
        <div class="venta-detalle-icon">
          <i class="ti ti-receipt"></i>
        </div>

        <div>
          <span>Detalle de venta</span>
          <h3>Factura #{{ venta?.nro_factura || venta?.id || '—' }}</h3>
          <p>
            Información de la venta, productos vendidos y movimiento de inventario asociado.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="venta-detalle-body" v-if="venta">
        <div class="venta-detalle-grid">
          <div class="venta-info-card">
            <label>Productor</label>
            <p>{{ venta.productor?.nombre_completo || '—' }}</p>
            <small>{{ venta.productor?.telefono || 'Sin teléfono' }}</small>
          </div>

          <div class="venta-info-card">
            <label>Fecha emisión</label>
            <p>{{ formatDate(venta.fecha_emision) }}</p>
            <small>
              Vencimiento: {{ formatDate(venta.fecha_vencimiento) }}
            </small>
          </div>

          <div class="venta-info-card">
            <label>Método de pago</label>
            <p>{{ venta.metodo_pago || '—' }}</p>
            <small>Modalidad de pago</small>
          </div>

          <div class="venta-info-card">
            <label>Estado</label>
            <p>{{ venta.estado || '—' }}</p>
            <small>Situación actual</small>
          </div>

          <div class="venta-info-card">
            <label>Subtotal</label>
            <p>Bs {{ numero(venta.subtotal) }}</p>
            <small>Antes de descuento</small>
          </div>

          <div class="venta-info-card">
            <label>Descuento</label>
            <p>Bs {{ numero(venta.descuento) }}</p>
            <small>Descuento general</small>
          </div>

          <div class="venta-info-card total">
            <label>Total</label>
            <p>Bs {{ numero(venta.monto_total) }}</p>
            <small>Monto final de la venta</small>
          </div>

          <div class="venta-info-card">
            <label>Registrado por</label>
            <p>{{ nombreUsuario(venta.usuario) }}</p>
            <small>Usuario responsable</small>
          </div>
        </div>

        <div
          v-if="venta.observaciones"
          class="venta-detail-section"
        >
          <h4>
            <i class="ti ti-notes"></i>
            Observaciones
          </h4>

          <p>{{ venta.observaciones }}</p>
        </div>

        <div class="venta-detail-section productos">
          <h4>
            <i class="ti ti-package"></i>
            Productos vendidos
          </h4>

          <div class="venta-productos-table-wrap">
            <table class="venta-productos-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Precio</th>
                  <th>Subtotal</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="detalle in venta.detalles || []"
                  :key="detalle.id"
                >
                  <td>
                    <strong>{{ detalle.producto?.nombre_producto || '—' }}</strong>
                    <small>{{ detalle.producto?.codigo_producto || 'Sin código' }}</small>
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

                <tr v-if="!venta.detalles?.length">
                  <td colspan="4" class="ventas-empty small">
                    No existen productos en esta venta.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div
          v-if="venta.movimientos?.length"
          class="venta-detail-section movimientos"
        >
          <h4>
            <i class="ti ti-arrows-transfer-down"></i>
            Movimientos de inventario
          </h4>

          <div class="venta-movimientos-list">
            <div
              v-for="movimiento in venta.movimientos"
              :key="movimiento.id"
              class="venta-movimiento-item"
            >
              <div>
                <strong>{{ movimiento.tipo }}</strong>
                <small>
                  {{ movimiento.producto?.nombre_producto || 'Producto' }}
                  · {{ movimiento.almacen?.nombre_almacen || 'Almacén' }}
                </small>
              </div>

              <span>
                {{ numero(movimiento.cantidad) }}
              </span>
            </div>
          </div>
        </div>

        <div class="venta-detail-note">
          <i class="ti ti-info-circle"></i>
          Esta venta ya afectó inventario mediante movimientos de salida. Si se anula, el sistema debe devolver stock con movimiento inverso.
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>

        <button
          class="btn btn-p"
          type="button"
          @click="$emit('imprimir', venta)"
        >
          <i class="ti ti-printer"></i>
          Imprimir comprobante
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  venta: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'imprimir']);

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
    Pagada: 'pagada',
    Pendiente: 'pendiente',
    Parcial: 'parcial',
    Anulada: 'anulada',
  }[estado] || 'pagada';
}
</script>