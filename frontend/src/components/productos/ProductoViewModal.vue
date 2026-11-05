<template>
  <div class="inventario-modal-backdrop" @click.self="close">
    <div class="inventario-modal productos-view-modal">
      <div class="productos-view-header">
        <div class="productos-view-avatar">
          <img
            v-if="producto?.imagen_url"
            :src="producto.imagen_url"
            alt="Producto"
            @error="hideImage"
          />
          <i v-else class="ti ti-package"></i>
        </div>

        <div>
          <h2>{{ producto?.nombre_producto || 'Producto' }}</h2>
          <p>{{ producto?.codigo_producto || 'Sin código' }}</p>

          <div class="productos-view-badges">
            <span class="inventario-badge" :class="producto?.activo ? 'inventario-bg-green' : 'inventario-bg-red'">
              {{ producto?.activo ? 'Activo' : 'Inactivo' }}
            </span>

            <span class="inventario-badge inventario-bg-blue">
              {{ producto?.categoria || 'Sin categoría' }}
            </span>
          </div>
        </div>

        <button class="productos-view-close" type="button" @click="close">
          ×
        </button>
      </div>

      <div class="productos-view-body">
        <div class="productos-detail-card">
          <label>Unidad de medida</label>
          <p>{{ producto?.unidad_medida || '—' }}</p>
        </div>

        <div class="productos-detail-card">
          <label>Precio unitario</label>
          <p>Bs {{ numero(producto?.precio_unitario) }}</p>
        </div>

        <div class="productos-detail-card">
          <label>Precio costo</label>
          <p>
            {{ producto?.precio_costo !== null && producto?.precio_costo !== undefined
              ? 'Bs ' + numero(producto.precio_costo)
              : '—'
            }}
          </p>
        </div>

        <div class="productos-detail-card">
          <label>Stock mínimo referencial</label>
          <p>{{ numero(producto?.stock_minimo) }}</p>
        </div>

        <div class="productos-detail-card">
          <label>Stock total</label>
          <p :class="stockClass">
            {{ numero(stockProducto(producto)) }}
            {{ producto?.unidad_medida || '' }}
          </p>
        </div>

        <div class="productos-detail-card productos-detail-span">
          <label>Descripción</label>
          <p>{{ producto?.descripcion || 'Sin descripción registrada.' }}</p>
        </div>
      </div>

      <div class="inventario-modal-actions productos-modal-actions">
        <button type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { stockProducto } from '@/utils/datos';

const props = defineProps({
  producto: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const stockClass = computed(() => {
  const stock = stockProducto(props.producto);
  const minimo = Number(props.producto?.stock_minimo || 0);

  if (stock <= 0) return 'productos-text-red';
  if (minimo > 0 && stock <= minimo) return 'productos-text-amber';

  return 'productos-text-green';
});

function close() {
  emit('close');
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function hideImage(event) {
  event.target.style.display = 'none';
}
</script>