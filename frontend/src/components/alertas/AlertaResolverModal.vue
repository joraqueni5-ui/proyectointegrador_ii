<template>
  <div class="modal-overlay alerta-modal-overlay" @click.self="$emit('close')">
    <div class="modal alerta-atender-modal">
      <!-- =====================================================
           CABECERA
      ====================================================== -->

      <div class="alerta-atender-header">
        <div class="alerta-atender-icon">
          <i class="ti ti-circle-check"></i>
        </div>

        <div class="alerta-atender-header-info">
          <h3>Resolver alerta</h3>

          <p>
            Registra el resultado final del seguimiento fitosanitario antes de cerrar la alerta.
          </p>
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

        <div class="resolucion-info-grid">
          <!-- RESPONSABLE -->

          <div class="resolucion-info-card">
            <span>
              <i class="ti ti-user-check"></i>

              Responsable
            </span>

            <strong>
              {{ nombreResponsable }}
            </strong>
          </div>

          <!-- PROGRESO -->

          <div class="resolucion-info-card">
            <span>
              <i class="ti ti-progress-check"></i>

              Progreso actual
            </span>

            <strong> {{ alerta?.progreso_seguimiento ?? 0 }}% </strong>
          </div>

          <!-- RESULTADO -->

          <div class="resolucion-info-card">
            <span>
              <i class="ti ti-chart-line"></i>

              Resultado actual
            </span>

            <strong>
              {{ textoResultado(alerta?.resultado_seguimiento) }}
            </strong>
          </div>

          <!-- INICIO -->

          <div class="resolucion-info-card">
            <span>
              <i class="ti ti-calendar-check"></i>

              Inicio tratamiento
            </span>

            <strong>
              {{ formatFechaHora(alerta?.fecha_inicio_tratamiento) }}
            </strong>
          </div>
        </div>

        <!-- =================================================
             RESULTADO FINAL
        ================================================== -->

        <div class="form-group">
          <label for="resultadoFinal">
            Resultado final

            <span class="required"> * </span>
          </label>

          <select id="resultadoFinal" v-model="form.resultado_seguimiento" :disabled="saving">
            <option value="">Selecciona el resultado final</option>

            <option value="Controlado">Controlado</option>

            <option value="Mejorando">Mejorando</option>

            <option value="Sin_cambios">Sin cambios</option>

            <option value="Empeorando">Empeorando</option>
          </select>

          <small class="campo-ayuda">
            Selecciona el estado observado al finalizar este seguimiento.
          </small>
        </div>

        <!-- =================================================
             ACCIÓN FINAL
        ================================================== -->

        <div class="form-group">
          <label for="accionFinal">
            Acción final realizada

            <span class="required"> * </span>
          </label>

          <textarea
            id="accionFinal"
            v-model="form.accion_tomada"
            maxlength="2000"
            :disabled="saving"
            placeholder="Ej. Se realizó una inspección final de la parcela y se verificó la respuesta del cultivo al tratamiento aplicado..."
          ></textarea>

          <div class="revision-char-count">{{ form.accion_tomada.length }}/2000</div>
        </div>

        <!-- =================================================
             OBSERVACIÓN DE CIERRE
        ================================================== -->

        <div class="form-group">
          <label for="observacionCierre">
            Observación de cierre

            <span class="required"> * </span>
          </label>

          <textarea
            id="observacionCierre"
            v-model="form.observacion_cierre"
            maxlength="3000"
            :disabled="saving"
            placeholder="Ej. El cultivo presenta evolución favorable y la enfermedad se considera controlada. Se recomienda mantener vigilancia preventiva en las siguientes inspecciones."
          ></textarea>

          <div class="revision-char-count">{{ form.observacion_cierre.length }}/3000</div>
        </div>

        <!-- =================================================
             AVISO
        ================================================== -->

        <div class="resolucion-warning">
          <i class="ti ti-circle-check"></i>

          <div>
            <strong> Cierre del seguimiento </strong>

            <p>
              Al confirmar, la alerta cambiará a
              <b>Resuelta</b>, el progreso quedará en <b>100%</b> y se registrará automáticamente la
              fecha y hora de resolución.
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

        <button type="button" class="btn btn-p" :disabled="saving" @click="resolver">
          <i :class="saving ? 'ti ti-loader-2' : 'ti ti-circle-check'"></i>

          {{ saving ? 'Resolviendo...' : 'Resolver alerta' }}
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
  resultado_seguimiento: 'Controlado',
  accion_tomada: '',
  observacion_cierre: '',
});

/* ============================================================
   ERROR LOCAL
============================================================ */

const localError = ref('');

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
   CARGAR DATOS CUANDO CAMBIA LA ALERTA
============================================================ */

watch(
  () => props.alerta?.id,

  () => {
    localError.value = '';

    form.resultado_seguimiento = props.alerta?.resultado_seguimiento || 'Controlado';

    form.accion_tomada = '';

    form.observacion_cierre = '';
  },

  {
    immediate: true,
  }
);

/* ============================================================
   RESOLVER ALERTA
============================================================ */

function resolver() {
  localError.value = '';

  /* ----------------------------------------------------------
     RESULTADO FINAL
  ---------------------------------------------------------- */

  if (!form.resultado_seguimiento) {
    localError.value = 'Debes seleccionar el resultado final.';

    return;
  }

  /* ----------------------------------------------------------
     ACCIÓN FINAL
  ---------------------------------------------------------- */

  const accion = form.accion_tomada.trim();

  if (!accion) {
    localError.value = 'Debes registrar la acción final realizada.';

    return;
  }

  if (accion.length < 10) {
    localError.value = 'Describe con un poco más de detalle la acción final realizada.';

    return;
  }

  /* ----------------------------------------------------------
     OBSERVACIÓN DE CIERRE
  ---------------------------------------------------------- */

  const observacion = form.observacion_cierre.trim();

  if (!observacion) {
    localError.value = 'Debes registrar una observación final antes de resolver la alerta.';

    return;
  }

  if (observacion.length < 10) {
    localError.value = 'La observación de cierre debe contener un poco más de detalle.';

    return;
  }

  /* ----------------------------------------------------------
     ENVIAR AL COMPONENTE PADRE
  ---------------------------------------------------------- */

  emit('save', {
    resultado_seguimiento: form.resultado_seguimiento,
    accion_tomada: accion,
    observacion_cierre: observacion,
  });
}

/* ============================================================
   TEXTO DEL RESULTADO
============================================================ */

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

/* ============================================================
   FORMATEAR FECHA Y HORA
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
</script>
