<template>
  <div class="movimientos-kpis">
    <div class="movimientos-kpi-main">
      <div class="movimientos-kpi-label">Control de movimientos</div>
      <div class="movimientos-kpi-value">{{ totalMovimientos }}</div>
      <div class="movimientos-kpi-help">movimientos registrados</div>
    </div>

    <div class="movimientos-kpi-card">
      <div class="movimientos-kpi-icon blue">
        <i class="ti ti-arrow-down-to-arc"></i>
      </div>
      <div>
        <span>Entradas</span>
        <strong>{{ totalEntradas }}</strong>
        <small>incrementan stock</small>
      </div>
    </div>

    <div class="movimientos-kpi-card">
      <div class="movimientos-kpi-icon red">
        <i class="ti ti-arrow-up-from-arc"></i>
      </div>
      <div>
        <span>Salidas</span>
        <strong>{{ totalSalidas }}</strong>
        <small>reducen stock</small>
      </div>
    </div>

    <div class="movimientos-kpi-card">
      <div class="movimientos-kpi-icon amber">
        <i class="ti ti-calendar-stats"></i>
      </div>
      <div>
        <span>Mes actual</span>
        <strong>{{ movimientosMes }}</strong>
        <small>actividad reciente</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalMovimientos: {
    type: Number,
    default: 0,
  },
  totalEntradas: {
    type: Number,
    default: 0,
  },
  totalSalidas: {
    type: Number,
    default: 0,
  },
  movimientosMes: {
    type: Number,
    default: 0,
  },
});
</script>