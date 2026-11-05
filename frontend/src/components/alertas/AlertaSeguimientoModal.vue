<template>
  <div class="modal-overlay alerta-modal-overlay" @click.self="$emit('close')">
    <div class="modal alerta-atender-modal">
      <!-- =====================================================
           CABECERA
      ====================================================== -->

      <div class="alerta-atender-header">
        <div class="alerta-atender-icon">
          <i class="ti ti-chart-line"></i>
        </div>

        <div class="alerta-atender-header-info">
          <h3>Registrar seguimiento</h3>

          <p>Evalúa la evolución del tratamiento y programa la siguiente revisión de la parcela.</p>
        </div>

        <button type="button" class="modal-close" :disabled="saving" @click="$emit('close')">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <!-- =====================================================
           CUERPO
      ====================================================== -->

      <div class="alerta-atender-body">
        <!-- =================================================
             ALERTA SELECCIONADA
        ================================================== -->

        <div class="alerta-atender-summary">
          <span> ALERTA SELECCIONADA </span>

          <strong>
            {{ alerta?.titulo || 'Alerta fitosanitaria' }}
          </strong>

          <p>
            {{ alerta?.mensaje || 'Sin información adicional.' }}
          </p>
        </div>

        <!-- =================================================
             INFORMACIÓN ACTUAL
        ================================================== -->

        <div class="seguimiento-form-info-grid">
          <!-- RESPONSABLE -->

          <div class="seguimiento-form-info-card">
            <span>
              <i class="ti ti-user-check"></i>

              Responsable
            </span>

            <strong>
              {{ nombreResponsable }}
            </strong>
          </div>

          <!-- INICIO DEL TRATAMIENTO -->

          <div class="seguimiento-form-info-card">
            <span>
              <i class="ti ti-calendar-check"></i>

              Inicio tratamiento
            </span>

            <strong>
              {{ inicioTratamiento }}
            </strong>
          </div>
        </div>

        <!-- =================================================
             RESULTADO OBSERVADO
        ================================================== -->

        <div class="form-group">
          <label for="resultadoSeguimiento">
            Resultado observado

            <span class="required"> * </span>
          </label>

          <select id="resultadoSeguimiento" v-model="form.resultado_seguimiento" :disabled="saving">
            <option value="">Selecciona el estado actual</option>

            <option value="Mejorando">Mejorando</option>

            <option value="Sin_cambios">Sin cambios</option>

            <option value="Empeorando">Empeorando</option>

            <option value="Controlado">Controlado</option>
          </select>
        </div>

        <!-- =================================================
             PROGRESO
        ================================================== -->

        <div class="form-group">
          <div class="seguimiento-progress-label">
            <label for="progresoSeguimiento"> Progreso del tratamiento </label>

            <strong> {{ form.progreso_seguimiento }}% </strong>
          </div>

          <input
            id="progresoSeguimiento"
            v-model.number="form.progreso_seguimiento"
            class="seguimiento-range"
            type="range"
            min="0"
            max="100"
            step="5"
            :disabled="saving"
          />

          <div class="seguimiento-range-scale">
            <span> 0% </span>

            <span> 50% </span>

            <span> 100% </span>
          </div>
        </div>

        <!-- =================================================
             OBSERVACIÓN
        ================================================== -->

        <div class="form-group">
          <label for="accionSeguimiento">
            Observación del seguimiento

            <span class="required"> * </span>
          </label>

          <textarea
            id="accionSeguimiento"
            v-model="form.accion_tomada"
            maxlength="2000"
            :disabled="saving"
            placeholder="Ej. Se realizó una nueva inspección de la parcela. Se observa reducción de síntomas y mejor respuesta de las plantas al tratamiento aplicado..."
          ></textarea>

          <div class="revision-char-count">{{ form.accion_tomada.length }}/2000</div>
        </div>

        <!-- =================================================
             PRÓXIMA REVISIÓN
        ================================================== -->

        <div class="form-group">
          <label for="proximaRevisionSeguimiento">
            Próxima revisión

            <span class="required"> * </span>
          </label>

          <input
            id="proximaRevisionSeguimiento"
            v-model="form.proxima_revision"
            type="datetime-local"
            :min="fechaMinima"
            :disabled="saving"
          />

          <small class="campo-ayuda">
            Programa la fecha de la siguiente verificación del tratamiento.
          </small>
        </div>

        <!-- =================================================
             AYUDA
        ================================================== -->

        <div class="seguimiento-ayuda">
          <i class="ti ti-info-circle"></i>

          <div>
            <strong> Evaluación del tratamiento </strong>

            <p>
              Registra el resultado observado en campo. El porcentaje representa el avance del
              seguimiento y no la confianza de la CNN.
            </p>
          </div>
        </div>

        <!-- =================================================
             ERROR LOCAL
        ================================================== -->

        <div v-if="localError" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ localError }}
        </div>

        <!-- =================================================
             ERROR BACKEND
        ================================================== -->

        <div v-if="error" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ error }}
        </div>
      </div>

      <!-- =====================================================
           FOOTER
      ====================================================== -->

      <div class="modal-footer">
        <button type="button" class="btn btn-s" :disabled="saving" @click="$emit('close')">
          Cancelar
        </button>

        <button type="button" class="btn btn-p" :disabled="saving" @click="guardar">
          <i :class="saving ? 'ti ti-loader-2' : 'ti ti-device-floppy'"></i>

          {{ saving ? 'Guardando...' : 'Registrar seguimiento' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

/* ============================================================
   PROPS
============================================================ */

const props = defineProps({
  alerta: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
});

/* ============================================================
   EMITS
============================================================ */

const emit = defineEmits(['close', 'save']);

/* ============================================================
   FORMULARIO
============================================================ */

const form = reactive({
  resultado_seguimiento: '',
  progreso_seguimiento: 0,
  accion_tomada: '',
  proxima_revision: '',
});

/* ============================================================
   ERROR LOCAL
============================================================ */

const localError = ref('');

/* ============================================================
   FECHA MÍNIMA
============================================================ */

const fechaMinima = computed(() => {
  const ahora = new Date();

  return new Intl.DateTimeFormat('sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/La_Paz',
  })
    .format(ahora)
    .replace(' ', 'T');
});

/* ============================================================
   RESPONSABLE
============================================================ */

const nombreResponsable = computed(() => {
  const responsable = props.alerta?.responsable;

  if (!responsable) {
    return 'Sin asignar';
  }

  const nombre = [responsable.name, responsable.paterno, responsable.materno]
    .filter(Boolean)
    .join(' ');

  return nombre || responsable.email || 'Responsable asignado';
});

/* ============================================================
   INICIO DEL TRATAMIENTO
============================================================ */

const inicioTratamiento = computed(() => {
  return formatFechaHora(props.alerta?.fecha_inicio_tratamiento);
});

/* ============================================================
   CARGAR DATOS AL CAMBIAR ALERTA
============================================================ */

watch(
  () => props.alerta?.id,

  () => {
    localError.value = '';

    form.resultado_seguimiento = props.alerta?.resultado_seguimiento || '';

    form.progreso_seguimiento = Number(props.alerta?.progreso_seguimiento || 0);

    /*
     * Dejamos la observación vacía porque cada
     * seguimiento debe registrar una nueva acción.
     */
    form.accion_tomada = '';

    form.proxima_revision = formatearParaInput(props.alerta?.proxima_revision);
  },

  {
    immediate: true,
  }
);

/* ============================================================
   GUARDAR
============================================================ */

function guardar() {
  localError.value = '';

  /* ----------------------------------------------------------
     RESULTADO
  ---------------------------------------------------------- */

  if (!form.resultado_seguimiento) {
    localError.value = 'Debes indicar el resultado observado.';

    return;
  }

  /* ----------------------------------------------------------
     PROGRESO
  ---------------------------------------------------------- */

  const progreso = Number(form.progreso_seguimiento);

  if (Number.isNaN(progreso) || progreso < 0 || progreso > 100) {
    localError.value = 'El progreso debe estar entre 0% y 100%.';

    return;
  }

  /* ----------------------------------------------------------
     OBSERVACIÓN
  ---------------------------------------------------------- */

  const observacion = form.accion_tomada.trim();

  if (!observacion) {
    localError.value = 'Debes registrar una observación del seguimiento.';

    return;
  }

  if (observacion.length < 10) {
    localError.value = 'Describe con un poco más de detalle el seguimiento realizado.';

    return;
  }

  /* ----------------------------------------------------------
     PRÓXIMA REVISIÓN
  ---------------------------------------------------------- */

  if (!form.proxima_revision) {
    localError.value = 'Debes programar la próxima revisión.';

    return;
  }

  const revision = new Date(form.proxima_revision);

  if (Number.isNaN(revision.getTime())) {
    localError.value = 'La fecha de próxima revisión no es válida.';

    return;
  }

  if (revision.getTime() <= new Date().getTime()) {
    localError.value = 'La próxima revisión debe ser una fecha futura.';

    return;
  }

  /* ----------------------------------------------------------
     ENVIAR
  ---------------------------------------------------------- */

  emit('save', {
    resultado_seguimiento: form.resultado_seguimiento,
    progreso_seguimiento: progreso,
    accion_tomada: observacion,
    proxima_revision: form.proxima_revision,
  });
}

/* ============================================================
   FORMATEAR FECHA
============================================================ */

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

/* ============================================================
   FECHA PARA DATETIME-LOCAL
============================================================ */

function formatearParaInput(fecha) {
  if (!fecha) {
    return '';
  }

  const date = new Date(fecha);

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  return new Intl.DateTimeFormat('sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/La_Paz',
  })
    .format(date)
    .replace(' ', 'T');
}
</script>
