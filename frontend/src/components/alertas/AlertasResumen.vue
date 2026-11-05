<template>
  <div class="alertas-resumen-card">
    <div class="alertas-resumen-head">
      <h3>
        <i class="ti ti-chart-pie"></i>
        Resumen por estado
      </h3>
      <p>Estado operativo de las alertas diagnósticas.</p>
    </div>

    <div class="alertas-resumen-grid">
      <div class="alertas-resumen-item activa">
        <span>Activas</span>
        <strong>{{ activas }}</strong>
      </div>

      <div class="alertas-resumen-item vencida">
        <span>Vencidas</span>
        <strong>{{ vencidas }}</strong>
      </div>

      <div class="alertas-resumen-item ignorada">
        <span>Ignoradas</span>
        <strong>{{ ignoradas }}</strong>
      </div>

      <div class="alertas-resumen-item atendida">
        <span>Atendidas</span>
        <strong>{{ atendidas }}</strong>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activas: {
    type: Number,
    default: 0,
  },
  vencidas: {
    type: Number,
    default: 0,
  },
  ignoradas: {
    type: Number,
    default: 0,
  },
  atendidas: {
    type: Number,
    default: 0,
  },
});
</script>
