<template>
  <div class="inventario-dashboard-kpis">
    <div class="inventario-dashboard-kpi-main">
      <div class="inventario-dashboard-kpi-label">Panel de inventario</div>
      <div class="inventario-dashboard-kpi-value">
        {{ numero(stockSemillas) }}
      </div>
      <div class="inventario-dashboard-kpi-help">
        kg de semillas disponibles
      </div>
    </div>

    <div class="inventario-dashboard-kpi-card">
      <div class="inventario-dashboard-kpi-icon blue">
        <i class="ti ti-leaf"></i>
      </div>
      <div>
        <span>Bioinsumos</span>
        <strong>{{ totalBioinsumos }}</strong>
        <small>registros disponibles</small>
      </div>
    </div>

    <div class="inventario-dashboard-kpi-card">
      <div class="inventario-dashboard-kpi-icon amber">
        <i class="ti ti-alert-triangle"></i>
      </div>
      <div>
        <span>Alertas stock</span>
        <strong>{{ alertasStock }}</strong>
        <small>bajo mínimo o sin stock</small>
      </div>
    </div>

    <div class="inventario-dashboard-kpi-card">
      <div class="inventario-dashboard-kpi-icon green">
        <i class="ti ti-arrows-transfer-down"></i>
      </div>
      <div>
        <span>Movimientos mes</span>
        <strong>{{ movimientosMes }}</strong>
        <small>entradas y salidas</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  stockSemillas: {
    type: [Number, String],
    default: 0,
  },
  totalBioinsumos: {
    type: [Number, String],
    default: 0,
  },
  alertasStock: {
    type: [Number, String],
    default: 0,
  },
  movimientosMes: {
    type: [Number, String],
    default: 0,
  },
});

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>