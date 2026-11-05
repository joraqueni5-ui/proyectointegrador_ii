<template>
  <div class="inventario-table-wrap productos-table-wrap">
    <table class="inventario-table productos-table">
      <thead>
        <tr>
          <th>Producto</th>
          <th>Categoría</th>
          <th>Unidad</th>
          <th>Precio venta</th>
          <th>Costo</th>
          <th>Stock total</th>
          <th>Stock mín.</th>
          <th>Estado</th>
          <th v-if="puedeGestionar" class="inventario-right">
            Acciones
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="producto in productos" :key="producto.id">
          <td>
            <div class="producto-cell">
              <div class="producto-avatar">
                <img
                  v-if="producto.imagen_url"
                  :src="producto.imagen_url"
                  alt="Producto"
                  @error="hideImage"
                />
                <i v-else class="ti ti-package"></i>
              </div>

              <div>
                <strong>{{ producto.nombre_producto }}</strong>
                <small>{{ producto.codigo_producto }}</small>

                <p v-if="producto.descripcion">
                  {{ resumen(producto.descripcion) }}
                </p>
              </div>
            </div>
          </td>

          <td>
            <span class="inventario-badge" :class="categoriaClass(producto.categoria)">
              {{ producto.categoria }}
            </span>
          </td>

          <td>
            <strong>{{ producto.unidad_medida }}</strong>
          </td>

          <td>
            <strong>Bs {{ numero(producto.precio_unitario) }}</strong>
          </td>

          <td>
            <span v-if="producto.precio_costo !== null && producto.precio_costo !== undefined">
              Bs {{ numero(producto.precio_costo) }}
            </span>
            <span v-else class="productos-muted">—</span>
          </td>

          <td>
            <div class="producto-stock-cell">
              <strong :class="stockClass(producto)">
                {{ numero(stockProducto(producto)) }}
              </strong>
              <small>{{ producto.unidad_medida }}</small>
            </div>
          </td>

          <td>
            {{ numero(producto.stock_minimo) }}
          </td>

          <td>
            <span
              class="inventario-badge"
              :class="producto.activo ? 'inventario-bg-green' : 'inventario-bg-red'"
            >
              {{ producto.activo ? 'Activo' : 'Inactivo' }}
            </span>
          </td>

          <td v-if="puedeGestionar">
            <div class="inventario-actions productos-actions">
              <button
                class="producto-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('view', producto)"
              >
                <i class="ti ti-eye"></i>
              </button>

              <button
                class="producto-action edit"
                type="button"
                title="Editar producto"
                @click="$emit('edit', producto)"
              >
                <i class="ti ti-pencil"></i>
              </button>

              <button
                class="producto-action state"
                :class="producto.activo ? 'delete' : 'activate'"
                type="button"
                :title="producto.activo ? 'Desactivar producto' : 'Activar producto'"
                @click="$emit('toggle-state', producto)"
              >
                <i :class="producto.activo ? 'ti ti-ban' : 'ti ti-check'"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="productos.length === 0">
          <td :colspan="puedeGestionar ? 9 : 8" class="inventario-empty productos-empty">
            <i class="ti ti-package-off"></i>
            <span>No existen productos registrados.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { stockProducto } from '@/utils/datos';

defineProps({
  productos: {
    type: Array,
    default: () => [],
  },
  puedeGestionar: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['view', 'edit', 'toggle-state']);

function stockClass(producto) {
  const stock = stockProducto(producto);
  const minimo = Number(producto.stock_minimo || 0);

  if (stock <= 0) return 'productos-text-red';
  if (minimo > 0 && stock <= minimo) return 'productos-text-amber';

  return 'productos-text-green';
}

function categoriaClass(categoria) {
  return {
    Semilla: 'inventario-bg-green',
    Bioinsumo: 'inventario-bg-blue',
    Agroquimico: 'inventario-bg-red',
    Fertilizante: 'inventario-bg-yellow',
    Equipo: 'inventario-bg-gray',
    Otro: 'inventario-bg-blue',
  }[categoria] || 'inventario-bg-blue';
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function resumen(texto) {
  if (!texto) return '';

  const limpio = String(texto).trim();

  if (limpio.length <= 70) return limpio;

  return `${limpio.slice(0, 70)}...`;
}

function hideImage(event) {
  event.target.style.display = 'none';
}
</script>