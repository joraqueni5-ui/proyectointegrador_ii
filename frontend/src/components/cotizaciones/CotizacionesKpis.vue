<template>
  <div class="cotizaciones-kpis">
    <div class="cotizacion-kpi-main">
      <div class="cotizacion-kpi-label">Gestión comercial</div>
      <div class="cotizacion-kpi-value">{{ total }}</div>
      <div class="cotizacion-kpi-help">cotizaciones registradas</div>
    </div>

    <div class="cotizacion-kpi-card amber">
      <div class="cotizacion-kpi-icon">
        <i class="ti ti-clock"></i>
      </div>

      <div>
        <span>Pendientes</span>
        <strong>{{ pendientes }}</strong>
        <small>requieren aprobación</small>
      </div>
    </div>

    <div class="cotizacion-kpi-card blue">
      <div class="cotizacion-kpi-icon">
        <i class="ti ti-thumb-up"></i>
      </div>

      <div>
        <span>Aprobadas</span>
        <strong>{{ aprobadas }}</strong>
        <small>listas para venta</small>
      </div>
    </div>

    <div class="cotizacion-kpi-card green">
      <div class="cotizacion-kpi-icon">
        <i class="ti ti-receipt"></i>
      </div>

      <div>
        <span>Convertidas</span>
        <strong>{{ convertidas }}</strong>
        <small>pasaron a venta</small>
      </div>
    </div>

    <div class="cotizacion-kpi-card red">
      <div class="cotizacion-kpi-icon">
        <i class="ti ti-circle-x"></i>
      </div>

      <div>
        <span>Rechazadas</span>
        <strong>{{ rechazadas }}</strong>
        <small>no aprobadas</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  total: {
    type: Number,
    default: 0,
  },
  pendientes: {
    type: Number,
    default: 0,
  },
  aprobadas: {
    type: Number,
    default: 0,
  },
  convertidas: {
    type: Number,
    default: 0,
  },
  rechazadas: {
    type: Number,
    default: 0,
  },
});
</script>