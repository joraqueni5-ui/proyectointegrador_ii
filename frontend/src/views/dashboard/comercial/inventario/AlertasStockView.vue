<template>
  <section class="inventario-page alertas-stock-page fade-in">
    <!-- HEADER -->
    <div class="inventario-header alertas-stock-header">
      <div>
        <div class="alertas-stock-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <span>Comercial</span>
          <span>›</span>
          <strong>Alertas de stock</strong>
        </div>

        <h1>Alertas de stock</h1>
        <p>
          Productos sin stock, bajo mínimo o próximos al límite permitido.
        </p>
      </div>

      <div class="inventario-header-actions alertas-stock-header-actions">
        <button
          class="inventario-btn-secondary"
          type="button"
          :disabled="loading"
          @click="fetchAlertas"
        >
          <i class="ti ti-refresh"></i>
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>

        <RouterLink
          to="/dashboard/inventario/movimientos"
          class="inventario-btn-primary"
        >
          <i class="ti ti-plus"></i>
          Registrar entrada
        </RouterLink>
      </div>
    </div>

    <!-- ERROR -->
    <div v-if="pageError" class="inventario-error alertas-stock-page-error">
      {{ pageError }}
    </div>

    <!-- KPIS -->
    <AlertasStockKpis
      v-if="!loading"
      :total-alertas="alertas.length"
      :total-sin-stock="totalSinStock"
      :total-bajo-minimo="totalBajoMinimo"
      :total-preventivas="totalPreventivas"
    />

    <!-- PANEL -->
    <div class="inventario-panel alertas-stock-panel">
      <AlertasStockFiltros
        v-model:search="search"
        v-model:estado="filtroEstado"
        @buscar="aplicarFiltros"
        @limpiar="limpiarFiltros"
      />

      <div v-if="loading" class="inventario-loading alertas-stock-loading">
        <div class="alertas-stock-spinner"></div>
        <span>Cargando alertas de stock...</span>
      </div>

      <AlertasStockTabla
        v-else
        :alertas="alertasFiltradas"
        @reponer="irAMovimientos"
      />
    </div>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';

import '@/styles/inventario.css';

import inventarioService from '@/services/modules/inventarioService';
import { obtenerMensajeError } from '@/services/errorService';

import AlertasStockKpis from '@/components/alertas-stock/AlertasStockKpis.vue';
import AlertasStockFiltros from '@/components/alertas-stock/AlertasStockFiltros.vue';
import AlertasStockTabla from '@/components/alertas-stock/AlertasStockTabla.vue';

const router = useRouter();

const alertas = ref([]);
const loading = ref(true);
const pageError = ref('');

const search = ref('');
const filtroEstado = ref('');

onMounted(fetchAlertas);

const totalSinStock = computed(() => {
  return alertas.value.filter((item) => item.estado === 'sin_stock').length;
});

const totalBajoMinimo = computed(() => {
  return alertas.value.filter((item) => item.estado === 'bajo_minimo').length;
});

const totalPreventivas = computed(() => {
  return alertas.value.filter((item) => item.estado === 'alerta').length;
});

const alertasFiltradas = computed(() => {
  const q = search.value.trim().toLowerCase();

  return alertas.value.filter((item) => {
    const nombreProducto = item.producto?.nombre_producto?.toLowerCase() || '';
    const codigoProducto = item.producto?.codigo_producto?.toLowerCase() || '';
    const categoria = item.producto?.categoria?.toLowerCase() || '';
    const almacen = item.almacen?.nombre_almacen?.toLowerCase() || '';

    const matchSearch =
      !q ||
      nombreProducto.includes(q) ||
      codigoProducto.includes(q) ||
      categoria.includes(q) ||
      almacen.includes(q);

    const matchEstado =
      !filtroEstado.value || item.estado === filtroEstado.value;

    return matchSearch && matchEstado;
  });
});

async function fetchAlertas() {
  loading.value = true;
  pageError.value = '';

  try {
    const { data } = await inventarioService.alertas();
    alertas.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(err, 'No se pudieron cargar las alertas de stock.');
    alertas.value = [];
  } finally {
    loading.value = false;
  }
}

function aplicarFiltros() {
  // Los filtros son locales mediante computed.
}

function limpiarFiltros() {
  search.value = '';
  filtroEstado.value = '';
}

function irAMovimientos() {
  router.push('/dashboard/inventario/movimientos');
}
</script>