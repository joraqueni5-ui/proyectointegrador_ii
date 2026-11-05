<template>
  <div class="inventario-table-wrap almacenes-table-wrap">
    <table class="inventario-table almacenes-table">
      <thead>
        <tr>
          <th>Almacén</th>
          <th>Departamento</th>
          <th>Ubicación</th>
          <th>Responsable</th>
          <th>Capacidad</th>
          <th>Stock total</th>
          <th>Inventarios</th>
          <th>Estado</th>
          <th v-if="puedeGestionar" class="inventario-right">
            Acciones
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="almacen in almacenes" :key="almacen.id">
          <td>
            <div class="almacen-cell">
              <div class="almacen-avatar" :class="almacen.estado === 'Activo' ? 'active' : 'inactive'">
                <i class="ti ti-building-warehouse"></i>
              </div>

              <div>
                <strong>{{ almacen.nombre_almacen }}</strong>
                <small>ID: {{ almacen.id }}</small>
              </div>
            </div>
          </td>

          <td>
            <span class="inventario-badge inventario-bg-blue">
              {{ almacen.departamento || '—' }}
            </span>
          </td>

          <td>
            <span class="almacen-location">
              {{ almacen.ubicacion || 'Sin ubicación' }}
            </span>
          </td>

          <td>
            {{ nombreResponsable(almacen.responsable) }}
          </td>

          <td>
            <strong>{{ numero(almacen.capacidad_maxima_m3) }}</strong>
            <small class="almacen-muted">m³</small>
          </td>

          <td>
            <strong :class="stockTotal(almacen) > 0 ? 'almacenes-text-green' : 'almacenes-text-muted'">
              {{ numero(stockTotal(almacen)) }}
            </strong>
          </td>

          <td>
            <span class="inventario-badge inventario-bg-gray">
              {{ almacen.inventarios_count ?? cantidadInventarios(almacen) }}
            </span>
          </td>

          <td>
            <span
              class="inventario-badge"
              :class="almacen.estado === 'Activo' ? 'inventario-bg-green' : 'inventario-bg-red'"
            >
              {{ almacen.estado || '—' }}
            </span>
          </td>

          <td v-if="puedeGestionar">
            <div class="inventario-actions almacenes-actions">
              <button
                class="almacen-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('view', almacen)"
              >
                <i class="ti ti-eye"></i>
              </button>

              <button
                class="almacen-action edit"
                type="button"
                title="Editar almacén"
                @click="$emit('edit', almacen)"
              >
                <i class="ti ti-pencil"></i>
              </button>

              <button
                class="almacen-action state"
                :class="almacen.estado === 'Activo' ? 'delete' : 'activate'"
                type="button"
                :title="almacen.estado === 'Activo' ? 'Desactivar almacén' : 'Activar almacén'"
                @click="$emit('toggle-state', almacen)"
              >
                <i :class="almacen.estado === 'Activo' ? 'ti ti-ban' : 'ti ti-check'"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="almacenes.length === 0">
          <td :colspan="puedeGestionar ? 9 : 8" class="inventario-empty almacenes-empty">
            <i class="ti ti-building-warehouse"></i>
            <span>No existen almacenes registrados.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  almacenes: {
    type: Array,
    default: () => [],
  },
  puedeGestionar: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['view', 'edit', 'toggle-state']);

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

function cantidadInventarios(almacen) {
  if (Array.isArray(almacen?.inventarios)) {
    return almacen.inventarios.length;
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