<template>
  <div class="alerta-detalle-panel">
    <template v-if="alerta">
      <div class="alerta-detalle-card">
        <!-- =====================================================
             CABECERA
        ====================================================== -->

        <div class="alerta-detalle-header" :class="nivelClass(alerta.nivel_riesgo)">
          <div class="alerta-detalle-icon">
            <i :class="nivelIcon(alerta.nivel_riesgo)"></i>
          </div>

          <div>
            <span>
              Prioridad final —
              {{ textoPrioridad(alerta.nivel_riesgo) }}
            </span>

            <h3>
              {{ alerta.titulo || 'Alerta fitosanitaria' }}
            </h3>

            <p>
              {{ alerta.mensaje || 'Sin mensaje registrado.' }}
            </p>
          </div>

          <button type="button" class="alerta-detalle-close" @click="$emit('close')">
            <i class="ti ti-x"></i>
          </button>
        </div>

        <!-- =====================================================
             CUERPO
        ====================================================== -->

        <div class="alerta-detalle-body">
          <!-- =================================================
               INFORMACIÓN DEL DIAGNÓSTICO
          ================================================== -->

          <div class="alerta-info-grid">
            <div class="alerta-info-card">
              <label> Parcela </label>

              <p>
                {{ alerta.diagnostico?.inspeccion?.parcela?.codigo_parcela || '—' }}
              </p>
            </div>

            <div class="alerta-info-card">
              <label> Enfermedad </label>

              <p>
                {{ alerta.diagnostico?.enfermedad?.nombre_comun || '—' }}
              </p>
            </div>

            <div class="alerta-info-card">
              <label> Confianza CNN </label>

              <p>{{ alerta.diagnostico?.porcentaje_confianza ?? '—' }}%</p>
            </div>

            <div class="alerta-info-card">
              <label> Severidad </label>

              <p>
                {{ textoSeveridad(alerta.diagnostico?.nivel_severidad) }}
              </p>
            </div>

            <div class="alerta-info-card">
              <label> Fecha emisión </label>

              <p>
                {{ formatFecha(alerta.fecha_emision) }}
              </p>
            </div>

            <div class="alerta-info-card">
              <label> Fecha límite </label>

              <p
                :class="{
                  danger: vencida(alerta),
                }"
              >
                {{ formatFecha(alerta.fecha_limite_accion) }}
              </p>
            </div>
          </div>

          <!-- =================================================
               MOTOR DE PRIORIDAD
          ================================================== -->

          <div v-if="tienePrioridadInteligente(alerta)" class="prioridad-inteligente">
            <div class="prioridad-head">
              <div>
                <span class="prioridad-label">
                  <i class="ti ti-brain"></i>

                  Motor de prioridad
                </span>

                <h4>
                  ¿Por qué esta alerta es
                  {{ textoPrioridad(alerta.nivel_riesgo).toLowerCase() }}?
                </h4>
              </div>

              <div class="prioridad-score" :class="nivelClass(alerta.nivel_riesgo)">
                <strong>
                  {{ alerta.puntaje_prioridad }}
                </strong>

                <span> /100 </span>
              </div>
            </div>

            <!-- BARRA -->

            <div class="prioridad-barra">
              <div
                class="prioridad-barra-fill"
                :class="nivelClass(alerta.nivel_riesgo)"
                :style="{
                  width: porcentajePuntaje(alerta.puntaje_prioridad) + '%',
                }"
              ></div>
            </div>

            <div class="prioridad-nivel-row">
              <span> Puntaje calculado </span>

              <strong :class="'prioridad-text-' + nivelClass(alerta.nivel_riesgo)">
                {{ textoPrioridad(alerta.nivel_riesgo) }}
              </strong>
            </div>

            <!-- FACTORES -->

            <div class="prioridad-factores">
              <div
                v-for="(motivo, index) in motivosPrioridad(alerta)"
                :key="index"
                class="prioridad-factor"
                :class="{
                  regla: motivo.factor === 'Regla de prioridad mínima',
                }"
              >
                <div class="factor-icon">
                  <i
                    :class="
                      motivo.factor === 'Regla de prioridad mínima'
                        ? 'ti ti-shield-check'
                        : motivo.puntos > 0
                          ? 'ti ti-circle-check'
                          : 'ti ti-circle'
                    "
                  ></i>
                </div>

                <div class="factor-info">
                  <strong>
                    {{ motivo.factor }}
                  </strong>

                  <span>
                    {{ motivo.valor }}
                  </span>
                </div>

                <div
                  v-if="motivo.factor !== 'Regla de prioridad mínima'"
                  class="factor-puntos"
                  :class="{
                    activo: motivo.puntos > 0,
                  }"
                >
                  +{{ motivo.puntos }}
                </div>
              </div>
            </div>

            <!-- REGLA DE SEGURIDAD -->

            <div v-if="tieneReglaMinima(alerta)" class="prioridad-regla-aviso">
              <i class="ti ti-shield-exclamation"></i>

              <div>
                <strong> Regla de seguridad aplicada </strong>

                <p>
                  Aunque el puntaje obtenido corresponda a una prioridad menor, una enfermedad con
                  riesgo base crítico no puede generar una alerta inferior a crítica.
                </p>
              </div>
            </div>

            <!-- NOTA CNN -->

            <div class="prioridad-nota">
              <i class="ti ti-info-circle"></i>

              <span>
                La confianza de la CNN representa la certeza de la clasificación. No representa la
                severidad de la enfermedad.
              </span>
            </div>
          </div>

          <!-- =================================================
               ACCIÓN RECOMENDADA
          ================================================== -->

          <div v-if="alerta.accion_recomendada" class="alerta-action-box recommended">
            <h4>
              <i class="ti ti-bulb"></i>

              Acción recomendada
            </h4>

            <p>
              {{ alerta.accion_recomendada }}
            </p>
          </div>

          <!-- =================================================
               SEGUIMIENTO FITOSANITARIO
          ================================================== -->

          <div class="seguimiento-mini-card">
            <!-- CABECERA -->

            <div class="seguimiento-mini-head">
              <div class="seguimiento-mini-title">
                <div class="seguimiento-mini-icon">
                  <i class="ti ti-activity-heartbeat"></i>
                </div>

                <div>
                  <span> Seguimiento fitosanitario </span>

                  <strong>
                    {{ textoEstadoSeguimiento(estadoSeguimiento(alerta)) }}
                  </strong>
                </div>
              </div>

              <span
                class="seguimiento-mini-badge"
                :class="estadoSeguimientoClass(estadoSeguimiento(alerta))"
              >
                {{ textoEstadoSeguimiento(estadoSeguimiento(alerta)) }}
              </span>
            </div>

            <!-- PROGRESO -->

            <div class="seguimiento-mini-progreso">
              <div class="seguimiento-mini-progreso-head">
                <span> Progreso </span>

                <strong> {{ alerta.progreso_seguimiento ?? 0 }}% </strong>
              </div>

              <div class="seguimiento-mini-bar">
                <div
                  class="seguimiento-mini-bar-fill"
                  :style="{
                    width: porcentajeProgreso(alerta.progreso_seguimiento) + '%',
                  }"
                ></div>
              </div>
            </div>

            <!-- DATOS -->

            <div class="seguimiento-mini-grid">
              <!-- RESPONSABLE -->

              <div>
                <span>
                  <i class="ti ti-user-check"></i>

                  Responsable
                </span>

                <strong>
                  {{ nombreResponsable(alerta.responsable) }}
                </strong>
              </div>

              <!-- PRÓXIMA REVISIÓN -->

              <div>
                <span>
                  <i class="ti ti-calendar-time"></i>

                  Próxima revisión
                </span>

                <strong
                  :class="{
                    danger: r9yMnTm4NSzvG9rrwjM2ec8xZgh1cafXH8(alerta),
                  }"
                >
                  {{ formatFechaHora(alerta.proxima_revision) }}
                </strong>
              </div>

              <!-- RESULTADO -->

              <div>
                <span>
                  <i class="ti ti-chart-line"></i>

                  Resultado
                </span>

                <strong>
                  {{ textoResultado(alerta.resultado_seguimiento) }}
                </strong>
              </div>

              <!-- INICIO TRATAMIENTO -->

              <div>
                <span>
                  <i class="ti ti-calendar-check"></i>

                  Inicio tratamiento
                </span>

                <strong>
                  {{ formatFechaHora(alerta.fecha_inicio_tratamiento) }}
                </strong>
              </div>
            </div>

            <!-- PLAN DE MANEJO -->

            <div v-if="alerta.plan_manejo" class="seguimiento-mini-plan">
              <span>
                <i class="ti ti-list-check"></i>

                Plan de manejo
              </span>

              <p>
                {{ alerta.plan_manejo }}
              </p>
            </div>

            <!-- REVISIÓN VENCIDA -->

            <div v-if="r9yMnTm4NSzvG9rrwjM2ec8xZgh1cafXH8(alerta)" class="seguimiento-mini-vencido">
              <i class="ti ti-clock-exclamation"></i>

              <span> La revisión programada se encuentra vencida. </span>
            </div>
          </div>

          <!-- =================================================
               ACCIÓN TOMADA
          ================================================== -->

          <div v-if="alerta.accion_tomada" class="alerta-action-box taken">
            <h4>
              <i class="ti ti-check"></i>

              Acción tomada
            </h4>

            <p>
              {{ alerta.accion_tomada }}
            </p>
          </div>

          <!-- =================================================
               ESTADOS GENERALES
          ================================================== -->

          <div class="alerta-detalle-status">
            <span class="b" :class="estadoBadge(alerta.estado)">
              {{ alerta.estado || 'Sin estado' }}
            </span>

            <span class="b" :class="alerta.leida ? 'bg' : 'br'">
              {{ alerta.leida ? 'Leída' : 'Sin leer' }}
            </span>

            <span v-if="alerta.prioridad_automatica" class="b bg">
              <i class="ti ti-cpu"></i>

              Prioridad automática
            </span>
          </div>

          <!-- =================================================
               ACCIONES DEL SEGUIMIENTO
          ================================================== -->

          <div class="alerta-detalle-actions">
            <!-- MARCAR COMO LEÍDA -->

            <button
              v-if="!alerta.leida"
              type="button"
              class="btn btn-s"
              :disabled="saving"
              @click="$emit('marcar-leida', alerta)"
            >
              <i class="ti ti-eye"></i>

              Marcar leída
            </button>

            <!-- Acciones de seguimiento: solo con permiso gestionar_alertas -->
            <template v-if="puedeGestionar">
              <!-- NUEVA -->

              <button
                v-if="estadoSeguimiento(alerta) === 'Nueva'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('atender', alerta)"
              >
                <i class="ti ti-player-play"></i>

                Iniciar revisión
              </button>

              <!-- EN REVISIÓN -->

              <button
                v-else-if="estadoSeguimiento(alerta) === 'En_revision'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('planificar', alerta)"
              >
                <i class="ti ti-clipboard-text"></i>

                Crear plan de manejo
              </button>

              <!-- PLANIFICADA -->

              <button
                v-else-if="estadoSeguimiento(alerta) === 'Planificada'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('iniciar-tratamiento', alerta)"
              >
                <i class="ti ti-first-aid-kit"></i>

                Iniciar tratamiento
              </button>

              <!-- EN TRATAMIENTO -->

              <button
                v-else-if="estadoSeguimiento(alerta) === 'En_tratamiento'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('registrar-seguimiento', alerta)"
              >
                <i class="ti ti-notes"></i>

                Registrar seguimiento
              </button>

              <!-- EN SEGUIMIENTO -->

              <template v-else-if="estadoSeguimiento(alerta) === 'En_seguimiento'">
                <button
                  type="button"
                  class="btn btn-s"
                  :disabled="saving"
                  @click="$emit('registrar-seguimiento', alerta)"
                >
                  <i class="ti ti-refresh"></i>

                  Actualizar
                </button>

                <button
                  type="button"
                  class="btn btn-p"
                  :disabled="saving"
                  @click="$emit('resolver', alerta)"
                >
                  <i class="ti ti-circle-check"></i>

                  Resolver alerta
                </button>
              </template>

              <!-- ESCALADA -->

              <button
                v-else-if="estadoSeguimiento(alerta) === 'Escalada'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('planificar', alerta)"
              >
                <i class="ti ti-alert-triangle"></i>

                Revisar nuevamente
              </button>

              <!-- REINCIDENTE -->

              <button
                v-else-if="estadoSeguimiento(alerta) === 'Reincidente'"
                type="button"
                class="btn btn-p"
                :disabled="saving"
                @click="$emit('planificar', alerta)"
              >
                <i class="ti ti-repeat"></i>

                Nuevo plan de manejo
              </button>
            </template>

            <!-- RESUELTA -->

            <div v-if="estadoSeguimiento(alerta) === 'Resuelta'" class="seguimiento-finalizado">
              <i class="ti ti-circle-check"></i>

              <span> Seguimiento completado </span>
            </div>

            <!-- DESCARTADA -->

            <div
              v-else-if="estadoSeguimiento(alerta) === 'Descartada'"
              class="seguimiento-finalizado"
            >
              <i class="ti ti-ban"></i>

              <span> Alerta descartada </span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- =====================================================
         SIN ALERTA SELECCIONADA
    ====================================================== -->

    <div v-else class="alerta-detalle-empty">
      <i class="ti ti-hand-click"></i>

      <strong> Selecciona una alerta </strong>

      <span>
        Selecciona una alerta para consultar su prioridad, factores de riesgo y seguimiento
        fitosanitario.
      </span>
    </div>
  </div>
</template>

<script setup>
import '@/styles/alerta-detalle.css';

defineProps({
  alerta: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  puedeGestionar: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'close',
  'marcar-leida',
  'atender',
  'planificar',
  'iniciar-tratamiento',
  'registrar-seguimiento',
  'resolver',
]);

/* ============================================================
   SEGUIMIENTO
============================================================ */

function estadoSeguimiento(alerta) {
  return alerta?.estado_seguimiento || 'Nueva';
}

function textoEstadoSeguimiento(estado) {
  return (
    {
      Nueva: 'Nueva',
      En_revision: 'En revisión',
      Planificada: 'Planificada',
      En_tratamiento: 'En tratamiento',
      En_seguimiento: 'En seguimiento',
      Resuelta: 'Resuelta',
      Escalada: 'Escalada',
      Reincidente: 'Reincidente',
      Descartada: 'Descartada',
    }[estado] ||
    estado ||
    'Nueva'
  );
}

function estadoSeguimientoClass(estado) {
  return (
    {
      Nueva: 'nueva',
      En_revision: 'revision',
      Planificada: 'planificada',
      En_tratamiento: 'tratamiento',
      En_seguimiento: 'seguimiento',
      Resuelta: 'resuelta',
      Escalada: 'escalada',
      Reincidente: 'reincidente',
      Descartada: 'descartada',
    }[estado] || 'nueva'
  );
}

function porcentajeProgreso(valor) {
  return Math.min(100, Math.max(0, Number(valor || 0)));
}

function nombreResponsable(responsable) {
  if (!responsable) {
    return 'Sin asignar';
  }

  const nombre = [responsable.name, responsable.paterno, responsable.materno]
    .filter(Boolean)
    .join(' ');

  return nombre || responsable.email || 'Responsable asignado';
}

function textoResultado(resultado) {
  return (
    {
      Mejorando: 'Mejorando',
      Sin_cambios: 'Sin cambios',
      Empeorando: 'Empeorando',
      Controlado: 'Controlado',
    }[resultado] ||
    resultado ||
    'Sin registro'
  );
}

function r9yMnTm4NSzvG9rrwjM2ec8xZgh1cafXH8(alerta) {
  if (!alerta?.proxima_revision) {
    return false;
  }

  if (['Resuelta', 'Descartada'].includes(estadoSeguimiento(alerta))) {
    return false;
  }

  const revision = new Date(alerta.proxima_revision);

  if (Number.isNaN(revision.getTime())) {
    return false;
  }

  return revision.getTime() < new Date().getTime();
}

/* ============================================================
   PRIORIDAD
============================================================ */

function tienePrioridadInteligente(alerta) {
  return (
    alerta?.prioridad_automatica === true &&
    alerta?.puntaje_prioridad !== null &&
    alerta?.puntaje_prioridad !== undefined
  );
}

function motivosPrioridad(alerta) {
  return Array.isArray(alerta?.motivos_prioridad) ? alerta.motivos_prioridad : [];
}

function tieneReglaMinima(alerta) {
  return motivosPrioridad(alerta).some((motivo) => motivo.factor === 'Regla de prioridad mínima');
}

function porcentajePuntaje(valor) {
  return Math.min(100, Math.max(0, Number(valor || 0)));
}

/* ============================================================
   FECHAS
============================================================ */

function formatFecha(fecha) {
  if (!fecha) {
    return '—';
  }

  const texto = String(fecha).substring(0, 10);

  const [y, m, d] = texto.split('-');

  if (!y || !m || !d) {
    return texto;
  }

  return `${d}/${m}/${y}`;
}

function formatFechaHora(fecha) {
  if (!fecha) {
    return '—';
  }

  const date = new Date(fecha);

  if (Number.isNaN(date.getTime())) {
    return fecha;
  }

  return new Intl.DateTimeFormat('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/La_Paz',
  }).format(date);
}

function vencida(alerta) {
  if (!alerta?.fecha_limite_accion) {
    return false;
  }

  const fecha = new Date(alerta.fecha_limite_accion);

  if (Number.isNaN(fecha.getTime())) {
    return false;
  }

  return fecha.getTime() < new Date().getTime() && alerta.estado === 'Activa';
}

/* ============================================================
   TEXTOS
============================================================ */

function textoPrioridad(nivel) {
  return (
    {
      Critico: 'Crítica',

      Crítico: 'Crítica',
      Alto: 'Alta',
      Medio: 'Media',
      Bajo: 'Baja',
    }[nivel] ||
    nivel ||
    'Sin prioridad'
  );
}

function textoSeveridad(severidad) {
  if (!severidad) {
    return 'No registrada';
  }

  const texto = String(severidad).replaceAll('_', ' ');

  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/* ============================================================
   CLASES
============================================================ */

function nivelClass(nivel) {
  return (
    {
      Critico: 'critico',

      Crítico: 'critico',
      Alto: 'alto',
      Medio: 'medio',
      Bajo: 'bajo',
    }[nivel] || 'bajo'
  );
}

function nivelIcon(nivel) {
  return (
    {
      Critico: 'ti ti-alert-octagon',

      Crítico: 'ti ti-alert-octagon',
      Alto: 'ti ti-alert-triangle',
      Medio: 'ti ti-alert-circle',
      Bajo: 'ti ti-info-circle',
    }[nivel] || 'ti ti-bell'
  );
}

function estadoBadge(estado) {
  return (
    {
      Activa: 'br',
      Atendida: 'bg',
      Ignorada: 'bgr',
      Vencida: 'ba',
    }[estado] || 'bgr'
  );
}
</script>
