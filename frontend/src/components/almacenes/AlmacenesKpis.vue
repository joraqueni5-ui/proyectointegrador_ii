<template>
  <div class="almacenes-kpis">
    <div class="almacenes-kpi-main">
      <div class="almacenes-kpi-label">Gestión de almacenes</div>
      <div class="almacenes-kpi-value">{{ totalAlmacenes }}</div>
      <div class="almacenes-kpi-help">almacenes registrados</div>
    </div>

    <div class="almacenes-kpi-card">
      <div class="almacenes-kpi-icon green">
        <i class="ti ti-building-warehouse"></i>
      </div>
      <div>
        <span>Activos</span>
        <strong>{{ totalActivos }}</strong>
        <small>habilitados para inventario</small>
      </div>
    </div>

    <div class="almacenes-kpi-card">
      <div class="almacenes-kpi-icon amber">
        <i class="ti ti-box"></i>
      </div>
      <div>
        <span>Con stock</span>
        <strong>{{ totalConStock }}</strong>
        <small>tienen inventario registrado</small>
      </div>
    </div>

    <div class="almacenes-kpi-card">
      <div class="almacenes-kpi-icon red">
        <i class="ti ti-ban"></i>
      </div>
      <div>
        <span>Inactivos</span>
        <strong>{{ totalInactivos }}</strong>
        <small>fuera de operación</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalAlmacenes: {
    type: Number,
    default: 0,
  },
  totalActivos: {
    type: Number,
    default: 0,
  },
  totalInactivos: {
    type: Number,
    default: 0,
  },
  totalConStock: {
    type: Number,
    default: 0,
  },
});
</script>