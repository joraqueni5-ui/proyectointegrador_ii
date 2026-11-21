<template>
  <section class="dm-page fade-in">
    <div class="cbar">
      <div class="bread">
        <i class="ti ti-home" style="font-size:14px"></i>
        <span>›</span>
        <span>Diagnóstico IA</span>
        <span>›</span>
        <span class="cur">Desempeño del modelo</span>
      </div>

      <div class="cbar-right">
        <button
          class="btn btn-p"
          type="button"
          :disabled="exportando || cargando || !datos"
          @click="exportarPdf"
        >
          <i class="ti ti-file-type-pdf"></i>
          {{ exportando ? 'Generando PDF...' : 'Exportar PDF' }}
        </button>
      </div>
    </div>

    <div class="pg-header dm-header">
      <div>
        <div class="pg-title">Desempeño del modelo de IA</div>
        <div class="pg-sub">
          Qué tanto coincide la IA con el criterio del agrónomo, calculado con los diagnósticos ya validados.
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <form class="card dm-filtros" novalidate @submit.prevent="aplicar">
      <div class="dm-campo">
        <label for="dm-desde">Desde</label>
        <input id="dm-desde" v-model="filtros.fecha_desde" type="date" :class="{ invalido: errores.fecha_desde }" />
        <small v-if="errores.fecha_desde" class="dm-error-campo">{{ errores.fecha_desde }}</small>
      </div>

      <div class="dm-campo">
        <label for="dm-hasta">Hasta</label>
        <input id="dm-hasta" v-model="filtros.fecha_hasta" type="date" :class="{ invalido: errores.fecha_hasta }" />
        <small v-if="errores.fecha_hasta" class="dm-error-campo">{{ errores.fecha_hasta }}</small>
      </div>

      <div class="dm-campo dm-campo-ancho">
        <label for="dm-version">Versión del modelo</label>
        <select id="dm-version" v-model="filtros.modelo_version" :class="{ invalido: errores.modelo_version }">
          <option value="">Todas las versiones</option>
          <option v-for="version in versiones" :key="version" :value="version">{{ version }}</option>
        </select>
        <small v-if="errores.modelo_version" class="dm-error-campo">{{ errores.modelo_version }}</small>
      </div>

      <div class="dm-acciones">
        <button class="btn btn-s" type="button" :disabled="cargando" @click="limpiar">
          <i class="ti ti-eraser"></i>
          Limpiar
        </button>
        <button class="btn btn-p" type="submit" :disabled="cargando">
          <i class="ti ti-filter"></i>
          {{ cargando ? 'Calculando...' : 'Aplicar' }}
        </button>
      </div>
    </form>

    <div v-if="errorGeneral" class="dm-error" role="alert">{{ errorGeneral }}</div>

    <div v-if="cargando && !datos" class="loading-state">
      <div class="spinner"></div>
      <span>Calculando el desempeño del modelo...</span>
    </div>

    <template v-else-if="datos">
      <div v-for="aviso in datos.advertencias" :key="aviso" class="dm-aviso">
        <i class="ti ti-alert-triangle"></i>
        <span>{{ aviso }}</span>
      </div>

      <!-- Indicadores -->
      <div class="dm-kpis">
        <div class="dm-kpi principal">
          <label>Aciertos (accuracy)</label>
          <strong>{{ fraccion(datos.metricas_globales.accuracy) }}</strong>
          <small>de {{ datos.metricas_globales.muestras }} diagnósticos evaluados</small>
        </div>
        <div class="dm-kpi">
          <label>F1 macro</label>
          <strong>{{ fraccion(datos.metricas_globales.f1_macro) }}</strong>
          <small>equilibrio entre precisión y recall</small>
        </div>
        <div class="dm-kpi ok">
          <label>Confirmados</label>
          <strong>{{ porcentaje(datos.resumen.pct_confirmados) }}</strong>
          <small>{{ datos.resumen.confirmados }} la IA acertó</small>
        </div>
        <div class="dm-kpi aviso">
          <label>Corregidos</label>
          <strong>{{ porcentaje(datos.resumen.pct_corregidos) }}</strong>
          <small>{{ datos.resumen.corregidos }} la IA se equivocó</small>
        </div>
        <div class="dm-kpi malo">
          <label>Rechazados</label>
          <strong>{{ porcentaje(datos.resumen.pct_rechazados) }}</strong>
          <small>{{ datos.resumen.rechazados }} no válidos</small>
        </div>
        <div class="dm-kpi">
          <label>Pendientes</label>
          <strong>{{ datos.resumen.pendientes }}</strong>
          <small>{{ porcentaje(datos.resumen.pct_pendientes) }} sin validar</small>
        </div>
      </div>

      <div class="dm-fila">
        <!-- Matriz de confusión -->
        <section class="card dm-panel">
          <h3><i class="ti ti-grid-dots"></i> Matriz de confusión</h3>
          <p class="dm-ayuda">
            Cada fila es la enfermedad <b>final</b> (la que validó el agrónomo) y cada columna lo que <b>predijo la IA</b>.
            La diagonal verde son los aciertos; el rojo, los errores.
          </p>

          <div v-if="matriz.codigos.length" class="dm-tabla-scroll">
            <table class="dm-matriz">
              <thead>
                <tr>
                  <th class="esquina">Real ↓ / IA →</th>
                  <th v-for="codigo in matriz.codigos" :key="codigo">{{ nombre(codigo) }}</th>
                  <th class="total">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(fila, i) in matriz.valores" :key="matriz.codigos[i]">
                  <th>{{ nombre(matriz.codigos[i]) }}</th>
                  <td
                    v-for="(valor, j) in fila"
                    :key="j"
                    :class="{ diagonal: i === j, error: i !== j && valor > 0 }"
                    :style="estiloCelda(valor, i, j)"
                  >
                    <strong>{{ valor }}</strong>
                    <small v-if="totalFila(i) > 0 && valor > 0">{{ Math.round(valor / totalFila(i) * 100) }} %</small>
                  </td>
                  <td class="total">{{ totalFila(i) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="dm-vacio">
            <i class="ti ti-chart-dots-3"></i>
            <span>Todavía no hay diagnósticos validados para armar la matriz.</span>
          </div>
        </section>

        <!-- Métricas por clase -->
        <section class="card dm-panel">
          <h3><i class="ti ti-list-check"></i> Precisión, recall y F1 por clase</h3>
          <div v-if="datos.por_clase.length" class="dm-tabla-scroll">
            <table class="dm-tabla">
              <thead>
                <tr>
                  <th>Clase</th>
                  <th class="num">Casos</th>
                  <th class="num">Precisión</th>
                  <th class="num">Recall</th>
                  <th class="num">F1</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="clase in datos.por_clase" :key="clase.codigo">
                  <td>{{ clase.nombre }}</td>
                  <td class="num">{{ clase.soporte }}</td>
                  <td class="num">{{ fraccion(clase.precision) }}</td>
                  <td class="num">{{ fraccion(clase.recall) }}</td>
                  <td class="num"><b>{{ fraccion(clase.f1) }}</b></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="dm-vacio">
            <i class="ti ti-chart-dots-3"></i>
            <span>Sin diagnósticos validados.</span>
          </div>

          <p class="dm-ayuda">
            <b>Precisión:</b> de lo que la IA marcó como esa enfermedad, cuánto era cierto.
            <b>Recall:</b> de los casos reales, cuántos detectó.
          </p>
        </section>
      </div>

      <div class="dm-fila">
        <!-- Confianza -->
        <section class="card dm-panel">
          <h3><i class="ti ti-gauge"></i> Confianza de la IA: aciertos frente a errores</h3>
          <div class="dm-barras">
            <div class="dm-barra">
              <div class="dm-barra-cab">
                <span>Cuando acierta ({{ datos.confianza.n_aciertos }} casos)</span>
                <strong>{{ porcentaje(datos.confianza.promedio_aciertos) }}</strong>
              </div>
              <div class="dm-pista"><div class="dm-relleno ok" :style="{ width: ancho(datos.confianza.promedio_aciertos) }"></div></div>
            </div>
            <div class="dm-barra">
              <div class="dm-barra-cab">
                <span>Cuando se equivoca ({{ datos.confianza.n_errores }} casos)</span>
                <strong>{{ porcentaje(datos.confianza.promedio_errores) }}</strong>
              </div>
              <div class="dm-pista"><div class="dm-relleno malo" :style="{ width: ancho(datos.confianza.promedio_errores) }"></div></div>
            </div>
            <div class="dm-barra">
              <div class="dm-barra-cab">
                <span>En diagnósticos rechazados ({{ datos.confianza.n_rechazados }} casos)</span>
                <strong>{{ porcentaje(datos.confianza.promedio_rechazados) }}</strong>
              </div>
              <div class="dm-pista"><div class="dm-relleno gris" :style="{ width: ancho(datos.confianza.promedio_rechazados) }"></div></div>
            </div>
          </div>
          <p class="dm-ayuda">{{ textoConfianza }}</p>
        </section>

        <!-- Evolución mensual -->
        <section class="card dm-panel">
          <h3><i class="ti ti-chart-line"></i> Evolución mensual del porcentaje de aciertos</h3>

          <div v-if="puntos.length" class="dm-grafico">
            <svg :viewBox="`0 0 ${GW} ${GH}`" role="img" aria-label="Porcentaje de aciertos por mes">
              <g v-for="marca in [0, 25, 50, 75, 100]" :key="marca">
                <line :x1="GM" :x2="GW - 10" :y1="yDe(marca)" :y2="yDe(marca)" class="rejilla" />
                <text :x="GM - 6" :y="yDe(marca) + 4" class="eje" text-anchor="end">{{ marca }}%</text>
              </g>

              <polyline v-if="puntos.length > 1" :points="lineaPuntos" class="linea" />

              <g v-for="punto in puntos" :key="punto.mes">
                <circle :cx="punto.x" :cy="punto.y" r="5" class="punto">
                  <title>{{ etiquetaMes(punto.mes) }}: {{ porcentaje(punto.aciertos_pct) }} ({{ punto.confirmados }} de {{ punto.confirmados + punto.corregidos }})</title>
                </circle>
                <text :x="punto.x" :y="punto.y - 10" class="valor" text-anchor="middle">{{ Math.round(punto.aciertos_pct) }}%</text>
                <text :x="punto.x" :y="GH - 6" class="eje" text-anchor="middle">{{ etiquetaMes(punto.mes) }}</text>
              </g>
            </svg>
          </div>

          <div v-else class="dm-vacio">
            <i class="ti ti-chart-line"></i>
            <span>Todavía no hay meses con diagnósticos validados.</span>
          </div>
        </section>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

import diagnosticoService from '@/services/modules/diagnosticoService';
import { descargarBlob } from '@/services/api';
import { obtenerMensajeError } from '@/services/errorService';

// Mismo límite que valida el backend (DesempenoModeloRequest).
const RANGO_MAXIMO_DIAS = 1095;

const filtros = reactive({ fecha_desde: '', fecha_hasta: '', modelo_version: '' });
const errores = reactive({ fecha_desde: '', fecha_hasta: '', modelo_version: '' });
const errorGeneral = ref('');
const datos = ref(null);
const cargando = ref(true);
const exportando = ref(false);

const versiones = computed(() => datos.value?.modelos_disponibles || []);

const matriz = computed(() => ({
  codigos: datos.value?.matriz?.filas_reales || [],
  valores: datos.value?.matriz?.valores || [],
}));

const maxCelda = computed(() => Math.max(1, ...matriz.value.valores.flat()));

onMounted(consultar);

function nombre(codigo) {
  return datos.value?.matriz?.nombres?.[codigo] || codigo;
}

function totalFila(i) {
  return (matriz.value.valores[i] || []).reduce((s, v) => s + v, 0);
}

// Verde para aciertos y rojo para errores; la intensidad depende de la cantidad de casos.
function estiloCelda(valor, i, j) {
  if (!valor) {
    return {};
  }

  const alfa = 0.15 + 0.6 * (valor / maxCelda.value);

  return {
    background: i === j ? `rgba(34, 197, 94, ${alfa})` : `rgba(239, 68, 68, ${alfa})`,
  };
}

function porcentaje(valor) {
  return valor === null || valor === undefined
    ? '—'
    : `${Number(valor).toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
}

function fraccion(valor) {
  return valor === null || valor === undefined ? '—' : porcentaje(Number(valor) * 100);
}

function ancho(valor) {
  return `${Math.max(0, Math.min(100, Number(valor) || 0))}%`;
}

const textoConfianza = computed(() => {
  const c = datos.value?.confianza;

  if (!c || c.promedio_aciertos === null || c.promedio_errores === null) {
    return 'Para comparar se necesitan diagnósticos confirmados y corregidos.';
  }

  const diferencia = c.promedio_aciertos - c.promedio_errores;

  return diferencia >= 10
    ? 'La IA suele tener menos confianza cuando se equivoca: la confianza es un buen indicador para decidir qué diagnósticos revisar primero.'
    : 'La confianza de la IA es parecida cuando acierta y cuando se equivoca: no conviene fiarse solo de ese porcentaje.';
});

// ---------------------------------------------------------------- gráfico (SVG)
const GW = 560;
const GH = 230;
const GM = 44; // margen izquierdo

const puntos = computed(() => {
  const meses = (datos.value?.evolucion_mensual || []).filter((m) => m.aciertos_pct !== null);
  // Margen a ambos lados para que las etiquetas de mes no se corten en los bordes.
  const paso = meses.length > 1 ? (GW - GM - 64) / (meses.length - 1) : 0;

  return meses.map((m, i) => ({
    ...m,
    x: meses.length > 1 ? GM + 28 + i * paso : (GM + GW) / 2,
    y: yDe(m.aciertos_pct),
  }));
});

const lineaPuntos = computed(() => puntos.value.map((p) => `${p.x},${p.y}`).join(' '));

function yDe(pct) {
  const alto = GH - 40;

  return 12 + alto - (Number(pct) / 100) * alto;
}

function etiquetaMes(mes) {
  const [anio, m] = String(mes).split('-');
  const nombres = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  return `${nombres[Number(m) - 1] || m} ${anio}`;
}

// ---------------------------------------------------------------- filtros y consulta
function limpiarErrores() {
  errores.fecha_desde = '';
  errores.fecha_hasta = '';
  errores.modelo_version = '';
  errorGeneral.value = '';
}

function validar() {
  limpiarErrores();

  const { fecha_desde: desde, fecha_hasta: hasta } = filtros;

  if (desde && hasta) {
    const dias = (new Date(hasta) - new Date(desde)) / 86400000;

    if (dias < 0) {
      errores.fecha_hasta = 'La fecha final no puede ser anterior a la fecha inicial.';
    } else if (dias > RANGO_MAXIMO_DIAS) {
      errores.fecha_hasta = `El rango de fechas no puede superar ${RANGO_MAXIMO_DIAS} días (3 años). Acorta el período.`;
    }
  }

  return !errores.fecha_hasta;
}

function parametros() {
  return Object.fromEntries(Object.entries(filtros).filter(([, v]) => v !== ''));
}

function aplicarErroresServidor(err) {
  const lista = err?.response?.data?.errors;

  if (err?.response?.status === 422 && lista) {
    for (const campo of Object.keys(errores)) {
      if (lista[campo]?.[0]) {
        errores[campo] = lista[campo][0];
      }
    }

    return Object.values(errores).some(Boolean);
  }

  return false;
}

async function consultar() {
  cargando.value = true;

  try {
    const { data } = await diagnosticoService.desempenoModelo(parametros());
    datos.value = data;
  } catch (err) {
    console.error(err);

    if (!aplicarErroresServidor(err)) {
      errorGeneral.value = obtenerMensajeError(err, 'No se pudo calcular el desempeño del modelo.');
    }
  } finally {
    cargando.value = false;
  }
}

function aplicar() {
  if (cargando.value || !validar()) {
    return;
  }

  consultar();
}

function limpiar() {
  filtros.fecha_desde = '';
  filtros.fecha_hasta = '';
  filtros.modelo_version = '';
  limpiarErrores();
  consultar();
}

async function exportarPdf() {
  if (exportando.value || !validar()) {
    return;
  }

  exportando.value = true;

  try {
    const { data } = await diagnosticoService.desempenoModeloPdf(parametros());

    descargarBlob(data, `desempeno_modelo_${new Date().toISOString().slice(0, 10)}.pdf`, 'application/pdf');
  } catch (err) {
    console.error(err);
    errorGeneral.value = obtenerMensajeError(err, 'No se pudo exportar el PDF.');
  } finally {
    exportando.value = false;
  }
}
</script>

<style scoped>
.dm-page {
  padding-bottom: 32px;
}

.dm-header,
.dm-filtros,
.dm-error,
.dm-aviso,
.dm-kpis,
.dm-fila {
  margin-inline: 22px;
}

.dm-header {
  margin-bottom: 14px;
}

.dm-filtros {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 14px 16px;
  padding: 16px 18px;
  margin-bottom: 16px;
}

.dm-campo {
  display: grid;
  gap: 5px;
  flex: 1 1 170px;
  min-width: 0;
}

.dm-campo-ancho {
  flex-basis: 260px;
}

.dm-campo label {
  color: var(--gris-600);
  font-size: 11.5px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.dm-campo input,
.dm-campo select {
  min-height: 40px;
  width: 100%;
  padding: 8px 12px;
  border: 1.5px solid var(--gris-300);
  border-radius: 10px;
  background: var(--gris-100);
  color: var(--gris-800);
  font-size: 13px;
  font-weight: 700;
}

.dm-campo .invalido {
  border-color: #ef4444;
  background: #fef2f2;
}

.dm-error-campo {
  color: #b91c1c;
  font-size: 11.5px;
  font-weight: 700;
}

.dm-acciones {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.dm-error {
  margin-bottom: 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--rojo-100);
  color: var(--rojo-600);
  font-size: 13px;
  font-weight: 800;
}

.dm-aviso {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 10px;
  padding: 11px 14px;
  border: 1px solid #fcd34d;
  border-radius: 12px;
  background: #fffbeb;
  color: #92400e;
  font-size: 12.5px;
  font-weight: 700;
  line-height: 1.45;
}

.dm-aviso i {
  margin-top: 1px;
  font-size: 17px;
}

.dm-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.dm-kpi {
  padding: 14px 16px;
  border: 1px solid var(--gris-200);
  border-top: 4px solid var(--gris-300);
  border-radius: 14px;
  background: white;
}

.dm-kpi.principal {
  border-top-color: #16a34a;
  background: linear-gradient(135deg, #f0fdf4, white);
}

.dm-kpi.ok { border-top-color: #22c55e; }
.dm-kpi.aviso { border-top-color: #f59e0b; }
.dm-kpi.malo { border-top-color: #ef4444; }

.dm-kpi label {
  display: block;
  color: var(--gris-500);
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.dm-kpi strong {
  display: block;
  margin-top: 4px;
  color: var(--gris-950);
  font-size: 24px;
  font-weight: 900;
}

.dm-kpi small {
  color: var(--gris-500);
  font-size: 11.5px;
  font-weight: 600;
}

.dm-fila {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 420px), 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.dm-panel {
  min-width: 0;
  padding: 18px;
}

.dm-panel h3 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 8px;
  color: var(--gris-950);
  font-size: 15px;
  font-weight: 900;
}

.dm-panel h3 i {
  color: var(--verde-700);
}

.dm-ayuda {
  margin: 8px 0 12px;
  color: var(--gris-500);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.5;
}

.dm-tabla-scroll {
  overflow-x: auto;
}

.dm-matriz,
.dm-tabla {
  width: 100%;
  border-collapse: separate;
  border-spacing: 4px;
  font-size: 12.5px;
}

.dm-matriz th,
.dm-tabla th {
  padding: 8px 10px;
  color: var(--gris-600);
  font-size: 11px;
  font-weight: 900;
  text-align: center;
}

.dm-matriz tbody th,
.dm-tabla td:first-child {
  text-align: left;
}

.dm-matriz td {
  padding: 10px 8px;
  border-radius: 10px;
  background: var(--gris-100);
  text-align: center;
}

.dm-matriz td strong {
  display: block;
  font-size: 17px;
  font-weight: 900;
}

.dm-matriz td small {
  color: var(--gris-600);
  font-size: 10.5px;
  font-weight: 700;
}

.dm-matriz .total {
  background: transparent;
  color: var(--gris-500);
  font-weight: 900;
}

.dm-tabla td,
.dm-tabla th {
  border-bottom: 1px solid var(--gris-100);
  border-radius: 0;
}

.dm-tabla td {
  padding: 8px 10px;
  color: var(--gris-800);
  font-weight: 700;
}

.dm-tabla .num {
  text-align: right;
}

.dm-barras {
  display: grid;
  gap: 14px;
  margin-top: 10px;
}

.dm-barra-cab {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  color: var(--gris-700);
  font-size: 12.5px;
  font-weight: 700;
}

.dm-pista {
  height: 12px;
  margin-top: 6px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--gris-100);
}

.dm-relleno {
  height: 100%;
  border-radius: 999px;
  transition: width 0.35s ease;
}

.dm-relleno.ok { background: linear-gradient(90deg, #16a34a, #4ade80); }
.dm-relleno.malo { background: linear-gradient(90deg, #dc2626, #f87171); }
.dm-relleno.gris { background: linear-gradient(90deg, #6b7280, #d1d5db); }

.dm-grafico svg {
  width: 100%;
  height: auto;
}

.dm-grafico .rejilla {
  stroke: var(--gris-200);
  stroke-width: 1;
}

.dm-grafico .linea {
  fill: none;
  stroke: #16a34a;
  stroke-width: 3;
  stroke-linejoin: round;
}

.dm-grafico .punto {
  fill: white;
  stroke: #16a34a;
  stroke-width: 3;
}

.dm-grafico .eje {
  fill: var(--gris-500);
  font-size: 11px;
  font-weight: 700;
}

.dm-grafico .valor {
  fill: var(--gris-800);
  font-size: 11px;
  font-weight: 900;
}

.dm-vacio {
  display: grid;
  gap: 8px;
  justify-items: center;
  padding: 28px 12px;
  color: var(--gris-400);
  font-size: 13px;
  font-weight: 700;
  text-align: center;
}

.dm-vacio i {
  font-size: 32px;
}

@media (max-width: 760px) {
  .dm-header,
  .dm-filtros,
  .dm-error,
  .dm-aviso,
  .dm-kpis,
  .dm-fila {
    margin-inline: 14px;
  }
}
</style>
