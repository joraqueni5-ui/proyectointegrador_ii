<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal almacenes-view-modal">
      <div class="almacenes-view-header">
        <div class="almacenes-view-icon" :class="almacen?.estado === 'Activo' ? 'active' : 'inactive'">
          <i class="ti ti-building-warehouse"></i>
        </div>

        <div>
          <h2>{{ almacen?.nombre_almacen || 'Almacén' }}</h2>
          <p>
            {{ almacen?.ubicacion || 'Sin ubicación registrada' }}
          </p>

          <div class="almacenes-view-badges">
            <span
              class="inventario-badge"
              :class="almacen?.estado === 'Activo' ? 'inventario-bg-green' : 'inventario-bg-red'"
            >
              {{ almacen?.estado || '—' }}
            </span>

            <span class="inventario-badge inventario-bg-blue">
              {{ almacen?.departamento || 'Sin departamento' }}
            </span>
          </div>
        </div>

        <button class="almacenes-view-close" type="button" @click="close">
          ×
        </button>
      </div>

      <div class="almacenes-view-body">
        <div class="almacenes-detail-card">
          <label>Responsable</label>
          <p>{{ nombreResponsable(almacen?.responsable) }}</p>
        </div>

        <div class="almacenes-detail-card">
          <label>Teléfono de contacto</label>
          <p>{{ almacen?.telefono_contacto || '—' }}</p>
        </div>

        <div class="almacenes-detail-card">
          <label>Capacidad máxima</label>
          <p>{{ numero(almacen?.capacidad_maxima_m3) }} m³</p>
        </div>

        <div class="almacenes-detail-card">
          <label>Stock total</label>
          <p :class="stockTotal(almacen) > 0 ? 'almacenes-text-green' : 'almacenes-text-muted'">
            {{ numero(stockTotal(almacen)) }}
          </p>
        </div>

        <div class="almacenes-detail-card almacenes-detail-span">
          <label>Observaciones</label>
          <p>{{ almacen?.observaciones || 'Sin observaciones registradas.' }}</p>
        </div>

        <div class="almacenes-detail-span">
          <div class="almacenes-inventario-title">
            <i class="ti ti-box-seam"></i>
            Inventario asociado
          </div>

          <div v-if="inventarios.length === 0" class="almacenes-empty-block">
            <i class="ti ti-package-off"></i>
            <span>No hay productos registrados en este almacén.</span>
          </div>

          <div v-else class="almacenes-inventario-list">
            <div
              v-for="item in inventarios"
              :key="item.id"
              class="almacenes-inventario-item"
            >
              <div>
                <strong>{{ item.producto?.nombre_producto || 'Producto sin nombre' }}</strong>
                <small>
                  {{ item.producto?.codigo_producto || 'Sin código' }}
                  · {{ item.producto?.unidad_medida || 'Sin unidad' }}
                </small>
              </div>

              <span>
                {{ numero(item.stock_actual) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="inventario-modal-actions almacenes-modal-actions">
        <button type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  almacen: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const inventarios = computed(() => {
  return Array.isArray(props.almacen?.inventarios)
    ? props.almacen.inventarios
    : [];
});

function close() {
  emit('close');
}

function nombreResponsable(responsable) {
  if (!responsable) {
    return '—';
  }

  if (typeof responsable === 'string') {
    return responsable;
  }

  return [
    responsable.name,
    responsable.paterno,
    responsable.materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function stockTotal(almacen) {
  if (!almacen) {
    return 0;
  }

  if (almacen.stock_total !== null && almacen.stock_total !== undefined) {
    return Number(almacen.stock_total) || 0;
  }

  if (Array.isArray(almacen.inventarios)) {
    return almacen.inventarios.reduce(
      (sum, item) => sum + (Number(item.stock_actual) || 0),
      0
    );
  }

  return 0;
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>