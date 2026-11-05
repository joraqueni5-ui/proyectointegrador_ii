<template>
  <div class="ventas-kpis">
    <div class="venta-kpi-main">
      <div class="venta-kpi-label">Ventas comerciales</div>

      <div class="venta-kpi-value">
        Bs {{ numero(kpis.ventas_mes) }}
      </div>

      <div class="venta-kpi-help">
        total facturado este mes
      </div>
    </div>

    <div class="venta-kpi-card green">
      <div class="venta-kpi-icon">
        <i class="ti ti-receipt"></i>
      </div>

      <div>
        <span>Facturas emitidas</span>
        <strong>{{ kpis.facturas_emitidas || 0 }}</strong>
        <small>ventas registradas</small>
      </div>
    </div>

    <div class="venta-kpi-card amber">
      <div class="venta-kpi-icon">
        <i class="ti ti-clock-dollar"></i>
      </div>

      <div>
        <span>Saldos pendientes</span>
        <strong>Bs {{ numero(kpis.saldos_pendientes) }}</strong>
        <small>ventas a crédito</small>
      </div>
    </div>

    <div class="venta-kpi-card red">
      <div class="venta-kpi-icon">
        <i class="ti ti-alert-triangle"></i>
      </div>

      <div>
        <span>Facturas vencidas</span>
        <strong>{{ kpis.facturas_vencidas || 0 }}</strong>
        <small>créditos atrasados</small>
      </div>
    </div>

    <div class="venta-kpi-card blue">
      <div class="venta-kpi-icon">
        <i class="ti ti-users"></i>
      </div>

      <div>
        <span>Productores pendientes</span>
        <strong>{{ kpis.productores_pend || 0 }}</strong>
        <small>con deuda o saldo</small>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  kpis: {
    type: Object,
    default: () => ({
      ventas_mes: 0,
      facturas_emitidas: 0,
      saldos_pendientes: 0,
      productores_pend: 0,
      facturas_vencidas: 0,
    }),
  },
});

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>