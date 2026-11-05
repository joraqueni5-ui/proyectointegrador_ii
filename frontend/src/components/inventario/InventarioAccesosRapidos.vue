<template>
  <div class="inventario-panel inventario-dashboard-quick-panel">
    <div class="inventario-panel-head inventario-dashboard-panel-head">
      <div>
        <h2>Accesos rápidos</h2>
        <p>
          Entra directamente a los módulos principales de inventario.
        </p>
      </div>
    </div>

    <div class="inventario-dashboard-quick-grid">
      <RouterLink
        to="/dashboard/inventario/productos"
        class="inventario-dashboard-quick-card"
      >
        <div class="inventario-dashboard-quick-icon green">
          <i class="ti ti-package"></i>
        </div>

        <div>
          <strong>Productos</strong>
          <span>Gestionar catálogo de semillas, bioinsumos e insumos.</span>
        </div>
      </RouterLink>

      <RouterLink
        to="/dashboard/inventario/almacenes"
        class="inventario-dashboard-quick-card"
      >
        <div class="inventario-dashboard-quick-icon blue">
          <i class="ti ti-building-warehouse"></i>
        </div>

        <div>
          <strong>Almacenes</strong>
          <span>Administrar espacios físicos de resguardo.</span>
        </div>
      </RouterLink>

      <RouterLink
        to="/dashboard/inventario/movimientos"
        class="inventario-dashboard-quick-card"
      >
        <div class="inventario-dashboard-quick-icon amber">
          <i class="ti ti-arrows-transfer-down"></i>
        </div>

        <div>
          <strong>Movimientos</strong>
          <span>Registrar entradas y salidas que modifican el stock.</span>
        </div>
      </RouterLink>

      <RouterLink
        to="/dashboard/inventario/alertas-stock"
        class="inventario-dashboard-quick-card"
      >
        <div class="inventario-dashboard-quick-icon red">
          <i class="ti ti-alert-triangle"></i>
        </div>

        <div>
          <strong>Alertas stock</strong>
          <span>Revisar productos sin stock o bajo mínimo.</span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>