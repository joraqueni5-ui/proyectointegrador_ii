<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal movimientos-view-modal">
      <div
        class="movimientos-view-header"
        :class="movimiento?.tipo === 'Entrada' ? 'entrada' : 'salida'"
      >
        <div class="movimientos-view-icon">
          <i :class="movimiento?.tipo === 'Entrada' ? 'ti ti-arrow-down-to-arc' : 'ti ti-arrow-up-from-arc'"></i>
        </div>

        <div>
          <h2>Movimiento #{{ movimiento?.id || '—' }}</h2>
          <p>
            {{ movimiento?.producto?.nombre_producto || 'Producto no disponible' }}
          </p>

          <div class="movimientos-view-badges">
            <span
              class="inventario-badge"
              :class="movimiento?.tipo === 'Entrada' ? 'inventario-bg-blue' : 'inventario-bg-red'"
            >
              {{ movimiento?.tipo || '—' }}
            </span>

            <span class="inventario-badge inventario-bg-gray">
              {{ textoMotivo(movimiento?.motivo) }}
            </span>
          </div>
        </div>

        <button class="movimientos-view-close" type="button" @click="close">
          ×
        </button>
      </div>

      <div class="movimientos-view-body">
        <div class="movimientos-detail-card">
          <label>Producto</label>
          <p>{{ movimiento?.producto?.nombre_producto || '—' }}</p>
          <small>{{ movimiento?.producto?.codigo_producto || 'Sin código' }}</small>
        </div>

        <div class="movimientos-detail-card">
          <label>Almacén</label>
          <p>{{ movimiento?.almacen?.nombre_almacen || '—' }}</p>
        </div>

        <div class="movimientos-detail-card">
          <label>Cantidad</label>
          <p>
            {{ numero(movimiento?.cantidad) }}
            {{ movimiento?.producto?.unidad_medida || '' }}
          </p>
        </div>

        <div class="movimientos-detail-card">
          <label>Fecha</label>
          <p>{{ formatDate(movimiento?.fecha_movimiento) }}</p>
        </div>

        <div class="movimientos-detail-card">
          <label>Stock anterior</label>
          <p>{{ numero(movimiento?.stock_anterior) }}</p>
        </div>

        <div class="movimientos-detail-card">
          <label>Stock posterior</label>
          <p
            :class="movimiento?.tipo === 'Entrada'
              ? 'movimientos-text-blue'
              : 'movimientos-text-red'"
          >
            {{ numero(movimiento?.stock_posterior) }}
          </p>
        </div>

        <div class="movimientos-detail-card">
          <label>Usuario</label>
          <p>{{ nombreUsuario(movimiento?.usuario) }}</p>
        </div>

        <div class="movimientos-detail-card">
          <label>Motivo</label>
          <p>{{ textoMotivo(movimiento?.motivo) }}</p>
        </div>

        <div class="movimientos-detail-card movimientos-detail-span">
          <label>Observaciones</label>
          <p>{{ movimiento?.observaciones || 'Sin observaciones registradas.' }}</p>
        </div>
      </div>

      <div class="inventario-modal-actions movimientos-modal-actions">
        <button type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  movimiento: {
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
  }[motivo] || motivo || '—';
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