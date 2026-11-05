<template>
  <section class="rf-page fade-in">
    <!-- CONTEXTBAR -->
    <div class="cbar rf-cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Diagnóstico IA</span>
        <span>›</span>
        <span class="cur">Reportes Fitosanitarios</span>
      </div>

      <div class="cbar-right">
        <button class="btn btn-s" type="button" @click="limpiarFiltros">
          <i class="ti ti-eraser"></i>
          Limpiar
        </button>

        <button
          class="btn btn-p"
          type="button"
          :disabled="loading"
          @click="generarReporte"
        >
          <i class="ti ti-report-search"></i>
          {{ loading ? 'Generando...' : 'Generar reporte' }}
        </button>
      </div>
    </div>

    <!-- HERO -->
    <div class="rf-hero">
      <div class="rf-hero-left">
        <div class="rf-badge">
          <i class="ti ti-report-analytics"></i>
          Reportes dinámicos
        </div>

        <h1>Reportes fitosanitarios</h1>

        <p>
          Genera reportes combinados de inspecciones, diagnósticos, alertas,
          parcelas, técnicos y enfermedades en una sola pantalla.
        </p>

        <div class="rf-steps">
          <span><b>1</b> Elige reporte</span>
          <span><b>2</b> Aplica filtros</span>
          <span><b>3</b> Exporta PDF o Excel</span>
        </div>
      </div>

      <div class="rf-hero-card">
        <i class="ti ti-plant-2"></i>
        <strong>{{ totalFilas }}</strong>
        <span>registros encontrados</span>
      </div>
    </div>

    <!-- MENSAJES -->
    <div v-if="error" class="rf-error">
      <i class="ti ti-alert-circle"></i>
      {{ error }}
    </div>

    <div v-if="successMessage" class="rf-success">
      <i class="ti ti-circle-check"></i>
      {{ successMessage }}
    </div>

    <!-- TIPOS DE REPORTE -->
    <div class="rf-card rf-type-card">
      <div class="rf-card-head">
        <div>
          <h3>¿Qué reporte deseas generar?</h3>
          <p>Selecciona una opción para adaptar los filtros y columnas.</p>
        </div>

        <span class="rf-chip">
          <i class="ti ti-sparkles"></i>
          Selección rápida
        </span>
      </div>

      <div class="rf-report-types">
        <button
          v-for="(item, index) in reportTypes"
          :key="item.value"
          type="button"
          class="rf-report-card"
          :class="{ active: filtros.tipo_reporte === item.value }"
          :style="{ '--i': index }"
          @click="selectReport(item.value)"
        >
          <div class="rf-report-icon">
            <i :class="item.icon"></i>
          </div>

          <div>
            <strong>{{ item.label }}</strong>
            <span>{{ item.description }}</span>
          </div>

          <i class="ti ti-chevron-right rf-report-arrow"></i>
        </button>
      </div>
    </div>

    <!-- FILTROS PRINCIPALES -->
    <div class="rf-card">
      <div class="rf-card-head">
        <div>
          <h3>Filtros principales</h3>
          <p>
            Estos filtros son los más usados para generar el reporte seleccionado:
            <strong>{{ selectedReport.label }}</strong>.
          </p>
        </div>

        <span class="rf-chip success">
          <i class="ti ti-filter"></i>
          {{ filtrosActivos }} filtros activos
        </span>
      </div>

      <div class="rf-grid">
        <div class="rf-field">
          <label>Fecha inicio</label>
          <input v-model="filtros.fecha_inicio" type="date" />
        </div>

        <div class="rf-field">
          <label>Fecha fin</label>
          <input v-model="filtros.fecha_fin" type="date" />
        </div>

        <div class="rf-field">
          <label>Parcela</label>
          <select v-model="filtros.id_parcela">
            <option value="">Todas las parcelas</option>
            <option v-for="p in parcelas" :key="p.id" :value="p.id">
              {{ p.codigo_parcela }} {{ p.nombre_referencial ? `— ${p.nombre_referencial}` : '' }}
            </option>
          </select>
        </div>

        <div class="rf-field">
          <label>Productor</label>
          <select v-model="filtros.id_productor">
            <option value="">Todos los productores</option>
            <option v-for="p in productores" :key="p.id" :value="p.id">
              {{ nombreProductor(p) }}
            </option>
          </select>
        </div>

        <div class="rf-field">
          <label>Técnico asignado</label>
          <select v-model="filtros.id_usuario">
            <option value="">Todos los técnicos</option>
            <option v-for="t in tecnicos" :key="t.id" :value="t.id">
              {{ nombreUsuario(t) }}
            </option>
          </select>
        </div>

        <div class="rf-field">
          <label>Enfermedad</label>
          <select v-model="filtros.id_enfermedad">
            <option value="">Todas las enfermedades</option>
            <option v-for="e in enfermedades" :key="e.id" :value="e.id">
              {{ e.nombre_comun || e.nombre || 'Sin nombre' }}
            </option>
          </select>
        </div>

        <div class="rf-field rf-field-wide">
          <label>Búsqueda rápida</label>
          <input
            v-model="filtros.busqueda"
            type="text"
            placeholder="Buscar por parcela, productor, enfermedad u observación..."
          />
        </div>
      </div>

      <!-- AVANZADOS -->
      <button
        class="rf-advanced-toggle"
        type="button"
        @click="mostrarAvanzados = !mostrarAvanzados"
      >
        <span>
          <i class="ti ti-adjustments"></i>
          Filtros avanzados
        </span>

        <i :class="mostrarAvanzados ? 'ti ti-chevron-up' : 'ti ti-chevron-down'"></i>
      </button>

      <Transition name="rf-slide">
        <div v-if="mostrarAvanzados" class="rf-grid rf-advanced">
          <div class="rf-field">
            <label>Tipo de inspección</label>
            <select v-model="filtros.tipo_inspeccion">
              <option value="">Todos</option>
              <option value="Cultivo">Cultivo</option>
              <option value="Pre_siembra">Pre siembra</option>
            </select>
          </div>

          <div class="rf-field">
            <label>Estado inspección</label>
            <select v-model="filtros.estado_inspeccion">
              <option value="">Todos</option>
              <option value="Pendiente">Pendiente</option>
              <option value="En_proceso">En proceso</option>
              <option value="Completada">Completada</option>
              <option value="Validada">Validada</option>
              <option value="No_realizada">No realizada</option>
            </select>
          </div>

          <div class="rf-field">
            <label>Severidad</label>
            <select v-model="filtros.nivel_severidad">
              <option value="">Todas</option>
              <option value="Sin_afeccion">Sin afección</option>
              <option value="Leve">Leve</option>
              <option value="Moderada">Moderada</option>
              <option value="Severa">Severa</option>
              <option value="Muy_severa">Muy severa</option>
            </select>
          </div>

          <div class="rf-field">
            <label>Estado diagnóstico</label>
            <select v-model="filtros.estado_validacion">
              <option value="">Todos</option>
              <option value="Pendiente">Pendiente</option>
              <option value="Confirmado">Confirmado</option>
              <option value="Corregido">Corregido</option>
              <option value="Rechazado">Rechazado</option>
            </select>
          </div>

          <div class="rf-field">
            <label>Nivel de riesgo</label>
            <select v-model="filtros.nivel_riesgo">
              <option value="">Todos</option>
              <option value="Bajo">Bajo</option>
              <option value="Medio">Medio</option>
              <option value="Alto">Alto</option>
              <option value="Critico">Crítico</option>
            </select>
          </div>

          <div class="rf-field">
            <label>Estado alerta</label>
            <select v-model="filtros.estado_alerta">
              <option value="">Todos</option>
              <option value="Activa">Activa</option>
              <option value="Atendida">Atendida</option>
              <option value="Vencida">Vencida</option>
              <option value="Ignorada">Ignorada</option>
            </select>
          </div>
        </div>
      </Transition>

      <div class="rf-actions">
        <button
          class="btn btn-s"
          type="button"
          :disabled="loading"
          @click="generarReporte"
        >
          <i class="ti ti-eye"></i>
          Vista previa
        </button>

        <button
          class="btn btn-s"
          type="button"
          :disabled="exporting || !reporte"
          @click="exportarExcel"
        >
          <i class="ti ti-file-spreadsheet"></i>
          Excel
        </button>

        <button
          class="btn btn-s"
          type="button"
          :disabled="exporting || !reporte"
          @click="exportarPdf"
        >
          <i class="ti ti-file-type-pdf"></i>
          PDF
        </button>
      </div>
    </div>

    <!-- LOADING -->
    <div v-if="loading" class="rf-loading">
      <div class="spinner"></div>
      <span>Generando reporte fitosanitario...</span>
    </div>

    <!-- RESULTADO -->
    <template v-else-if="reporte">
      <div class="rf-card rf-result-card">
        <div class="rf-card-head">
          <div>
            <h3>{{ reporte.titulo || 'Reporte fitosanitario' }}</h3>
            <p>{{ totalFilas }} registros encontrados según los filtros aplicados.</p>
          </div>

          <span class="rf-chip success">
            <i class="ti ti-check"></i>
            Generado
          </span>
        </div>

        <div class="rf-resumen">
          <div
            v-for="(item, index) in resumenVisible"
            :key="item.key"
            class="rf-resumen-card"
            :class="{ textual: item.tipo === 'texto' }"
            :style="{ '--i': index }"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
      </div>

      <div class="rf-card">
        <div class="rf-table-head">
          <h3>Detalle del reporte</h3>
          <span>{{ totalFilas }} filas · mostrando {{ filasVistaPrevia.length }}</span>
        </div>

        <div v-if="!totalFilas" class="rf-empty">
          <i class="ti ti-database-off"></i>
          <span>No existen datos para los filtros seleccionados.</span>
        </div>

        <div v-else class="rf-table-wrap">
          <table class="tbl rf-table">
            <thead>
              <tr>
                <th v-for="col in reporte.columnas" :key="col">
                  {{ col }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="(fila, index) in filasVistaPrevia" :key="index">
                <td v-for="(valor, key) in fila" :key="key">
                  {{ valor || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="filasOcultas > 0" class="rf-preview-note">
  <i class="ti ti-info-circle"></i>
  Se muestran solo las primeras {{ MAX_FILAS_PREVIEW }} filas para mantener la vista limpia.
  Exporta a Excel para ver los {{ totalFilas }} registros completos.
</div>
      </div>
    </template>

    <!-- EMPTY -->
    <div v-else class="rf-card rf-placeholder">
      <div class="rf-placeholder-icon">
        <i class="ti ti-report-analytics"></i>
      </div>

      <h3>Genera tu primer reporte</h3>
      <p>
        Elige el tipo de reporte, aplica los filtros necesarios y presiona
        “Generar reporte”.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import enfermedadService from '@/services/modules/enfermedadService';
import parcelaService from '@/services/modules/parcelaService';
import productorService from '@/services/modules/productorService';
import reporteFitosanitarioService from '@/services/modules/reporteFitosanitarioService';
import usuarioService from '@/services/modules/usuarioService';
import { obtenerMensajeError } from '@/services/errorService';

import '@/styles/reportes-fitosanitarios.css';
import { normalizeList } from '@/utils/datos';
import { descargarBlob } from '@/services/api';

const loading = ref(false);
const exporting = ref(false);
const mostrarAvanzados = ref(false);

const error = ref('');
const successMessage = ref('');

const reporte = ref(null);

const parcelas = ref([]);
const productores = ref([]);
const tecnicos = ref([]);
const enfermedades = ref([]);

const filtros = ref(defaultFiltros());

const reportTypes = [
  {
    value: 'general',
    label: 'General combinado',
    description: 'Inspecciones, diagnósticos y alertas en una sola tabla.',
    icon: 'ti ti-layout-grid',
  },
  {
    value: 'inspecciones',
    label: 'Inspecciones',
    description: 'Reporte simple y detallado de visitas técnicas.',
    icon: 'ti ti-clipboard-check',
  },
  {
    value: 'diagnosticos',
    label: 'Diagnósticos',
    description: 'Reporte simple con enfermedad, confianza y validación.',
    icon: 'ti ti-microscope',
  },
  {
    value: 'por_parcela',
    label: 'Por parcela',
    description: 'Resumen con enfermedades, alertas y acciones tomadas.',
    icon: 'ti ti-map-2',
  },
  {
    value: 'por_tecnico',
    label: 'Por técnico',
    description: 'Trabajo realizado, parcelas atendidas y diagnósticos.',
    icon: 'ti ti-user-check',
  },
  {
    value: 'por_enfermedad',
    label: 'Por enfermedad',
    description: 'Enfermedades detectadas y parcelas afectadas.',
    icon: 'ti ti-virus-search',
  },
  {
    value: 'alertas',
    label: 'Alertas',
    description: 'Alertas activas, atendidas y acciones tomadas.',
    icon: 'ti ti-bell-ringing',
  },
  {
    value: 'no_realizadas',
    label: 'No realizadas',
    description: 'Inspecciones no realizadas y reprogramaciones.',
    icon: 'ti ti-calendar-x',
  },
  {
    value: 'pre_siembra',
    label: 'Pre siembra',
    description: 'Inspecciones antes del registro del cultivo.',
    icon: 'ti ti-seeding',
  },
];

const totalFilas = computed(() => reporte.value?.filas?.length || 0);

const MAX_FILAS_PREVIEW = 50;

const filasVistaPrevia = computed(() => {
  return (reporte.value?.filas || []).slice(0, MAX_FILAS_PREVIEW);
});

const filasOcultas = computed(() => {
  return Math.max(totalFilas.value - filasVistaPrevia.value.length, 0);
});

const resumenVisible = computed(() => {
  const resumen = reporte.value?.resumen || {};

  return Object.entries(resumen).map(([key, value]) => ({
    key,
    label: limpiarLabel(key),
    value,
    tipo: Number.isFinite(Number(value)) ? 'numero' : 'texto',
  }));
});

const selectedReport = computed(() => {
  return reportTypes.find((item) => item.value === filtros.value.tipo_reporte) || reportTypes[0];
});

const filtrosActivos = computed(() => {
  return Object.entries(filtros.value)
    .filter(([key, value]) => key !== 'tipo_reporte' && value !== '' && value !== null && value !== undefined)
    .length;
});

onMounted(async () => {
  await cargarCatalogos();
});

function defaultFiltros() {
  return {
    tipo_reporte: 'general',
    fecha_inicio: '',
    fecha_fin: '',
    id_parcela: '',
    id_productor: '',
    id_usuario: '',
    id_enfermedad: '',
    tipo_inspeccion: '',
    estado_inspeccion: '',
    nivel_severidad: '',
    estado_validacion: '',
    nivel_riesgo: '',
    estado_alerta: '',
    busqueda: '',
  };
}

function selectReport(tipo) {
  filtros.value.tipo_reporte = tipo;
  reporte.value = null;
  error.value = '';
  successMessage.value = '';
}

async function cargarCatalogos() {
  try {
    await Promise.all([
      cargarParcelas(),
      cargarProductores(),
      cargarTecnicos(),
      cargarEnfermedades(),
    ]);
  } catch (err) {
    console.error(err);
  }
}

async function cargarParcelas() {
  try {
    const { data } = await parcelaService.listar();
    parcelas.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

async function cargarProductores() {
  try {
    const { data } = await productorService.listar({ todos: true });

    productores.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

async function cargarTecnicos() {
  try {
    const { data } = await usuarioService.listarTecnicosInspeccion();
    tecnicos.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

async function cargarEnfermedades() {
  try {
    const { data } = await enfermedadService.listar({ activo: true });

    enfermedades.value = normalizeList(data);
  } catch (err) {
    console.error(err);
  }
}

function filtrosLimpios() {
  const params = {};

  Object.entries(filtros.value).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params[key] = value;
    }
  });

  return params;
}

async function generarReporte() {
  loading.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteFitosanitarioService.preview(filtrosLimpios());

    reporte.value = data;

    mostrarExito('Reporte generado correctamente.');
  } catch (err) {
    console.error(err);
    reporte.value = null;

    error.value = obtenerMensajeError(
      err,
      'No se pudo generar el reporte fitosanitario.'
    );
  } finally {
    loading.value = false;
  }
}

async function exportarExcel() {
  exporting.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteFitosanitarioService.excel(filtrosLimpios());

    descargarBlob(
      data,
      `reporte_fitosanitario_${filtros.value.tipo_reporte}.xlsx`,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    mostrarExito('Reporte Excel exportado correctamente.');
  } catch (err) {
    console.error(err);

    error.value = obtenerMensajeError(
      err,
      'No se pudo exportar el Excel.'
    );
  } finally {
    exporting.value = false;
  }
}

async function exportarPdf() {
  exporting.value = true;
  error.value = '';
  successMessage.value = '';

  try {
    const { data } = await reporteFitosanitarioService.pdf(filtrosLimpios());

    descargarBlob(
      data,
      `reporte_fitosanitario_${filtros.value.tipo_reporte}.pdf`,
      'application/pdf'
    );

    mostrarExito('Reporte PDF exportado correctamente.');
  } catch (err) {
    console.error(err);

    error.value = obtenerMensajeError(
      err,
      'No se pudo exportar el PDF.'
    );
  } finally {
    exporting.value = false;
  }
}

function limpiarFiltros() {
  filtros.value = defaultFiltros();
  reporte.value = null;
  error.value = '';
  successMessage.value = '';
  mostrarAvanzados.value = false;
}

function mostrarExito(mensaje) {
  successMessage.value = mensaje;

  setTimeout(() => {
    successMessage.value = '';
  }, 3500);
}

function limpiarLabel(key) {
  return String(key)
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (l) => l.toUpperCase());
}

function nombreUsuario(user) {
  if (!user) return '—';

  return [
    user.name,
    user.paterno,
    user.materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function nombreProductor(productor) {
  if (!productor) return '—';

  return productor.nombre_completo || [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}
</script>