<template>
  <section class="inventario-page inventario-dashboard-page fade-in">
    <!-- HEADER -->
    <div class="inventario-header inventario-dashboard-header">
      <div>
        <div class="inventario-dashboard-breadcrumb">
          <i class="ti ti-home"></i>
          <span>›</span>
          <span>Comercial</span>
          <span>›</span>
          <strong>Inventario</strong>
        </div>

        <h1>Panel de inventario</h1>
        <p>
          Resumen general de stock, almacenes, movimientos y alertas del sistema.
        </p>
      </div>

      <div class="inventario-header-actions inventario-dashboard-header-actions">
        <button
          class="inventario-btn-secondary"
          type="button"
          :disabled="loading"
          @click="cargarResumen"
        >
          <i class="ti ti-refresh"></i>
          {{ loading ? 'Actualizando...' : 'Actualizar' }}
        </button>
      </div>
    </div>

    <!-- ERROR -->
    <div v-if="pageError" class="inventario-error inventario-dashboard-error">
      {{ pageError }}
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="inventario-loading inventario-dashboard-loading">
      <div class="inventario-dashboard-spinner"></div>
      <span>Cargando resumen de inventario...</span>
    </div>

    <template v-else>
      <!-- KPIS -->
      <InventarioKpis
        :stock-semillas="kpis.stock_semillas_kg"
        :total-bioinsumos="kpis.total_bioinsumos"
        :alertas-stock="puedeVerAlertasStock ? kpis.alertas_stock : 0"
        :movimientos-mes="puedeVerMovimientos ? kpis.movimientos_mes : 0"
      />

      <!-- ACCESOS RÁPIDOS -->
      <InventarioAccesosRapidos />

      <!-- STOCK POR CATEGORÍA -->
      <div class="inventario-dashboard-stock-layout">
        <InventarioStockCategoria
          titulo="Semillas"
          descripcion="Stock disponible de semillas registrado en almacenes."
          :items="semillas"
          empty-text="No existen semillas con stock registrado."
        />

        <InventarioStockCategoria
          titulo="Bioinsumos"
          descripcion="Stock disponible de bioinsumos registrados en almacenes."
          :items="bioinsumos"
          empty-text="No existen bioinsumos con stock registrado."
        />
      </div>

      <!-- PANEL FINAL -->
      <div
        v-if="puedeVerMovimientos || puedeVerAlertasStock"
        class="inventario-dashboard-main-grid"
      >
        <InventarioMovimientosRecientes
          v-if="puedeVerMovimientos"
          :movimientos="movimientos"
        />

        <InventarioAlertasResumen
          v-if="puedeVerAlertasStock"
          :alertas="alertas"
        />
      </div>
    </template>
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

import '@/styles/inventario.css';

import inventarioService from '@/services/modules/inventarioService';
import movimientoService from '@/services/modules/movimientoService';
import { obtenerMensajeError } from '@/services/errorService';
import { tienePermiso as usuarioTienePermiso, usuarioGuardado } from '@/utils/permisos';

import InventarioKpis from '@/components/inventario/InventarioKpis.vue';
import InventarioAccesosRapidos from '@/components/inventario/InventarioAccesosRapidos.vue';
import InventarioStockCategoria from '@/components/inventario/InventarioStockCategoria.vue';
import InventarioMovimientosRecientes from '@/components/inventario/InventarioMovimientosRecientes.vue';
import InventarioAlertasResumen from '@/components/inventario/InventarioAlertasResumen.vue';

const loading = ref(true);
const pageError = ref('');

const kpis = ref({
  stock_semillas_kg: 0,
  total_bioinsumos: 0,
  alertas_stock: 0,
  movimientos_mes: 0,
});

const semillas = ref([]);
const bioinsumos = ref([]);
const movimientos = ref([]);
const alertas = ref([]);

const puedeVerMovimientos = computed(() => tienePermiso('gestionar_movimientos'));
const puedeVerAlertasStock = computed(() => tienePermiso('ver_alertas_stock'));

onMounted(cargarResumen);

async function cargarResumen() {
  loading.value = true;
  pageError.value = '';

  try {
    const tareas = [
      fetchDashboard(),
    ];

    if (puedeVerMovimientos.value) {
      tareas.push(fetchMovimientos());
    } else {
      movimientos.value = [];
    }

    if (puedeVerAlertasStock.value) {
      tareas.push(fetchAlertas());
    } else {
      alertas.value = [];
    }

    await Promise.all(tareas);
  } catch (err) {
    console.error(err);
    pageError.value = obtenerMensajeError(
      err,
      'No se pudo cargar el resumen de inventario.'
    );
  } finally {
    loading.value = false;
  }
}

async function fetchDashboard() {
  const { data } = await inventarioService.dashboard();

  kpis.value = {
    stock_semillas_kg: data?.kpis?.stock_semillas_kg ?? 0,
    total_bioinsumos: data?.kpis?.total_bioinsumos ?? 0,
    alertas_stock: data?.kpis?.alertas_stock ?? 0,
    movimientos_mes: data?.kpis?.movimientos_mes ?? 0,
  };

  semillas.value = data?.semillas || [];
  bioinsumos.value = data?.bioinsumos || [];
}

async function fetchMovimientos() {
  const { data } = await movimientoService.listar({
    per_page: 5,
  });

  movimientos.value = Array.isArray(data)
    ? data
    : data?.data ?? [];
}

async function fetchAlertas() {
  const { data } = await inventarioService.alertas();

  alertas.value = Array.isArray(data)
    ? data
    : [];
}

function tienePermiso(permiso) {
  return usuarioTienePermiso(usuarioGuardado(), permiso);
}
</script>