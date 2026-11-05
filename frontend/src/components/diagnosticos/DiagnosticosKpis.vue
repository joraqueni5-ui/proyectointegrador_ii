<template>
  <div class="diagnosticos-kpis">
    <div class="diagnostico-kpi-main">
      <div class="diagnostico-kpi-label">Diagnóstico fitosanitario</div>

      <div class="diagnostico-kpi-value">
        {{ totalDiagnosticos }}
      </div>

      <div class="diagnostico-kpi-help">
        diagnósticos registrados
      </div>
    </div>

    <div class="diagnostico-kpi-card amber">
      <div class="diagnostico-kpi-icon">
        <i class="ti ti-clock"></i>
      </div>

      <div>
        <span>Pendientes</span>
        <strong>{{ pendientes }}</strong>
        <small>requieren validación</small>
      </div>
    </div>

    <div class="diagnostico-kpi-card green">
      <div class="diagnostico-kpi-icon">
        <i class="ti ti-shield-check"></i>
      </div>

      <div>
        <span>Confirmados</span>
        <strong>{{ confirmados }}</strong>
        <small>validados técnicamente</small>
      </div>
    </div>

    <div class="diagnostico-kpi-card blue">
      <div class="diagnostico-kpi-icon">
        <i class="ti ti-edit"></i>
      </div>

      <div>
        <span>Corregidos</span>
        <strong>{{ corregidos }}</strong>
        <small>ajustados por supervisor</small>
      </div>
    </div>

    <div class="diagnostico-kpi-card red">
      <div class="diagnostico-kpi-icon">
        <i class="ti ti-circle-x"></i>
      </div>

      <div>
        <span>Rechazados</span>
        <strong>{{ rechazados }}</strong>
        <small>foto inválida o error</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalDiagnosticos: {
    type: Number,
    default: 0,
  },
  pendientes: {
    type: Number,
    default: 0,
  },
  confirmados: {
    type: Number,
    default: 0,
  },
  corregidos: {
    type: Number,
    default: 0,
  },
  rechazados: {
    type: Number,
    default: 0,
  },
});
</script>