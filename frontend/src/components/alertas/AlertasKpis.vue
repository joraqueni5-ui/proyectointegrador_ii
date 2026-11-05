<template>
  <div class="alertas-kpis">
    <div class="alerta-kpi-main">
      <div class="alerta-kpi-label">Alertas diagnósticas</div>

      <div class="alerta-kpi-value">
        {{ totalAlertas }}
      </div>

      <div class="alerta-kpi-help">alertas generadas por diagnósticos</div>
    </div>

    <div class="alerta-kpi-card red">
      <div class="alerta-kpi-icon">
        <i class="ti ti-alert-octagon"></i>
      </div>

      <div>
        <span> Críticas </span>

        <strong>
          {{ criticas }}
        </strong>

        <small> requieren acción urgente </small>
      </div>
    </div>

    <div class="alerta-kpi-card amber">
      <div class="alerta-kpi-icon">
        <i class="ti ti-alert-triangle"></i>
      </div>

      <div>
        <span> Altas </span>

        <strong>
          {{ altas }}
        </strong>

        <small> monitoreo activo </small>
      </div>
    </div>

    <div class="alerta-kpi-card blue">
      <div class="alerta-kpi-icon">
        <i class="ti ti-bell-ringing"></i>
      </div>

      <div>
        <span> No leídas </span>

        <strong>
          {{ noLeidas }}
        </strong>

        <small> pendientes de revisión </small>
      </div>
    </div>

    <div class="alerta-kpi-card green">
      <div class="alerta-kpi-icon">
        <i class="ti ti-circle-check"></i>
      </div>

      <div>
        <span> Atendidas </span>

        <strong>
          {{ atendidas }}
        </strong>

        <small> acciones registradas </small>
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
  criticas: {
    type: Number,
    default: 0,
  },
  altas: {
    type: Number,
    default: 0,
  },
  noLeidas: {
    type: Number,
    default: 0,
  },
  atendidas: {
    type: Number,
    default: 0,
  },
});
</script>
