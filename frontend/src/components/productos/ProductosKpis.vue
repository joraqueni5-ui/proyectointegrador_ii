<template>
  <div class="productos-kpis">
    <div class="productos-kpi-main">
      <div class="productos-kpi-label">Catálogo comercial</div>
      <div class="productos-kpi-value">{{ totalProductos }}</div>
      <div class="productos-kpi-help">productos registrados</div>
    </div>

    <div class="productos-kpi-card">
      <div class="productos-kpi-icon green">
        <i class="ti ti-circle-check"></i>
      </div>
      <div>
        <span>Activos</span>
        <strong>{{ totalActivos }}</strong>
        <small>disponibles para uso</small>
      </div>
    </div>

    <div class="productos-kpi-card">
      <div class="productos-kpi-icon amber">
        <i class="ti ti-alert-triangle"></i>
      </div>
      <div>
        <span>Bajo stock</span>
        <strong>{{ totalBajoStock }}</strong>
        <small>requieren revisión</small>
      </div>
    </div>

    <div class="productos-kpi-card">
      <div class="productos-kpi-icon red">
        <i class="ti ti-ban"></i>
      </div>
      <div>
        <span>Inactivos</span>
        <strong>{{ totalInactivos }}</strong>
        <small>fuera de uso</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalProductos: {
    type: Number,
    default: 0,
  },
  totalActivos: {
    type: Number,
    default: 0,
  },
  totalBajoStock: {
    type: Number,
    default: 0,
  },
  totalInactivos: {
    type: Number,
    default: 0,
  },
});
</script>