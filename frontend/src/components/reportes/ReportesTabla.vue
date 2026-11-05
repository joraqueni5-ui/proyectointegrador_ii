<template>
  <div class="reportes-table-card">
    <div class="reportes-table-head">
      <div>
        <h2>Detalle del reporte</h2>
        <p>
          Vista paginada para facilitar la revisión en pantalla.
          El PDF y Excel se exportan completos.
        </p>
      </div>

      <div class="reportes-table-tools">
        <div class="reportes-search">
          <i class="ti ti-search"></i>

          <input
            v-model.trim="busquedaLocal"
            placeholder="Buscar en resultado..."
            @input="resetPage"
          />
        </div>

        <select v-model.number="perPage" @change="resetPage">
          <option :value="10">10 por página</option>
          <option :value="25">25 por página</option>
          <option :value="50">50 por página</option>
        </select>
      </div>
    </div>

    <div v-if="filasFiltradas.length === 0" class="reportes-empty-table">
      <i class="ti ti-database-off"></i>
      <strong>No existen datos</strong>
      <span>No hay registros para los filtros o búsqueda aplicada.</span>
    </div>

    <template v-else>
      <!-- TABLA DESKTOP -->
      <div class="reportes-table-wrap">
        <table class="reportes-table">
          <thead>
            <tr>
              <th
                v-for="columna in columnas"
                :key="columna"
              >
                {{ columna }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(fila, index) in filasPaginadas"
              :key="index"
            >
              <td
                v-for="(valor, key) in fila"
                :key="key"
              >
                {{ valor ?? '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CARDS MOBILE -->
      <div class="reportes-mobile-list">
        <article
          v-for="(fila, index) in filasPaginadas"
          :key="`mobile-${index}`"
          class="reporte-mobile-card"
        >
          <div class="reporte-mobile-head">
            <strong>Registro {{ desde + index }}</strong>
            <span>#{{ desde + index }}</span>
          </div>

          <div class="reporte-mobile-fields">
            <div
              v-for="(valor, key) in fila"
              :key="key"
            >
              <label>{{ formatearEtiqueta(key) }}</label>
              <p>{{ valor ?? '—' }}</p>
            </div>
          </div>
        </article>
      </div>

      <!-- PAGINACIÓN -->
      <div class="reportes-pagination">
        <button
          class="btn btn-s"
          type="button"
          :disabled="currentPage <= 1"
          @click="currentPage--"
        >
          <i class="ti ti-chevron-left"></i>
          Anterior
        </button>

        <div class="reportes-pagination-info">
          <strong>
            Página {{ currentPage }} de {{ totalPages }}
          </strong>
          <span>
            Mostrando {{ desde }} - {{ hasta }} de {{ filasFiltradas.length }} registros
          </span>
        </div>

        <button
          class="btn btn-s"
          type="button"
          :disabled="currentPage >= totalPages"
          @click="currentPage++"
        >
          Siguiente
          <i class="ti ti-chevron-right"></i>
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  reporte: {
    type: Object,
    default: null,
  },
});

const busquedaLocal = ref('');
const currentPage = ref(1);
const perPage = ref(10);

const columnas = computed(() => props.reporte?.columnas || []);
const filas = computed(() => props.reporte?.filas || []);

const filasFiltradas = computed(() => {
  const q = busquedaLocal.value.toLowerCase();

  if (!q) {
    return filas.value;
  }

  return filas.value.filter((fila) => {
    return Object.values(fila)
      .join(' ')
      .toLowerCase()
      .includes(q);
  });
});

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filasFiltradas.value.length / perPage.value));
});

const filasPaginadas = computed(() => {
  const start = (currentPage.value - 1) * perPage.value;
  const end = start + perPage.value;

  return filasFiltradas.value.slice(start, end);
});

const desde = computed(() => {
  if (filasFiltradas.value.length === 0) {
    return 0;
  }

  return (currentPage.value - 1) * perPage.value + 1;
});

const hasta = computed(() => {
  return Math.min(
    currentPage.value * perPage.value,
    filasFiltradas.value.length
  );
});

watch(
  () => props.reporte,
  () => {
    busquedaLocal.value = '';
    currentPage.value = 1;
  }
);

watch(
  () => filasFiltradas.value.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value;
    }
  }
);

function resetPage() {
  currentPage.value = 1;
}

function formatearEtiqueta(key) {
  return String(key || '')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letra) => letra.toUpperCase());
}
</script>