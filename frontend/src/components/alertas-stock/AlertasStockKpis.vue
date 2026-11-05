<template>
  <div class="alertas-stock-kpis">
    <div class="alertas-stock-kpi-main">
      <div class="alertas-stock-kpi-label">Control de stock</div>
      <div class="alertas-stock-kpi-value">{{ totalAlertas }}</div>
      <div class="alertas-stock-kpi-help">alertas detectadas</div>
    </div>

    <div class="alertas-stock-kpi-card">
      <div class="alertas-stock-kpi-icon red">
        <i class="ti ti-package-off"></i>
      </div>

      <div>
        <span>Sin stock</span>
        <strong>{{ totalSinStock }}</strong>
        <small>requieren reposición inmediata</small>
      </div>
    </div>

    <div class="alertas-stock-kpi-card">
      <div class="alertas-stock-kpi-icon amber">
        <i class="ti ti-alert-triangle"></i>
      </div>

      <div>
        <span>Bajo mínimo</span>
        <strong>{{ totalBajoMinimo }}</strong>
        <small>por debajo del stock mínimo</small>
      </div>
    </div>

    <div class="alertas-stock-kpi-card">
      <div class="alertas-stock-kpi-icon blue">
        <i class="ti ti-bell-ringing"></i>
      </div>

      <div>
        <span>Preventivas</span>
        <strong>{{ totalPreventivas }}</strong>
        <small>cerca del límite mínimo</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalAlertas: {
    type: Number,
    default: 0,
  },
  totalSinStock: {
    type: Number,
    default: 0,
  },
  totalBajoMinimo: {
    type: Number,
    default: 0,
  },
  totalPreventivas: {
    type: Number,
    default: 0,
  },
});
</script>