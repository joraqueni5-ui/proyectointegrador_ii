<template>
  <div class="modal-overlay alerta-modal-overlay" @click.self="$emit('close')">
    <div class="modal alerta-atender-modal">
      <!-- =====================================================
           CABECERA
      ====================================================== -->

      <div class="alerta-atender-header">
        <div class="alerta-atender-icon">
          <i class="ti ti-first-aid-kit"></i>
        </div>

        <div class="alerta-atender-header-info">
          <h3>Iniciar tratamiento</h3>

          <p>Registra el inicio de las acciones definidas en el plan de manejo fitosanitario.</p>
        </div>

        <button type="button" class="modal-close" :disabled="saving" @click="$emit('close')">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <!-- =====================================================
           CUERPO
      ====================================================== -->

      <div class="alerta-atender-body">
        <!-- ALERTA -->

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
             INFORMACIÓN DEL PLAN
        ================================================== -->

        <div class="tratamiento-info-grid">
          <div class="tratamiento-info-card">
            <span>
              <i class="ti ti-user-check"></i>
              Responsable
            </span>

            <strong>
              {{ nombreResponsable }}
            </strong>
          </div>

          <div class="tratamiento-info-card">
            <span>
              <i class="ti ti-calendar-time"></i>
              Próxima revisión
            </span>

            <strong>
              {{ proximaRevision }}
            </strong>
          </div>
        </div>

        <!-- =================================================
             PLAN DE MANEJO
        ================================================== -->

        <div v-if="alerta?.plan_manejo" class="tratamiento-plan-box">
          <span>
            <i class="ti ti-list-check"></i>
            Plan de manejo
          </span>

          <p>
            {{ alerta.plan_manejo }}
          </p>
        </div>

        <!-- =================================================
             ACCIÓN REALIZADA
        ================================================== -->

        <div class="form-group">
          <label for="accionTratamiento">
            Acción realizada

            <span class="required"> * </span>
          </label>

          <textarea
            id="accionTratamiento"
            v-model="accion"
            maxlength="2000"
            :disabled="saving"
            placeholder="Ej. Se inició el tratamiento fitosanitario de acuerdo con el plan de manejo establecido para la parcela..."
          ></textarea>

          <div class="revision-char-count">{{ accion.length }}/2000</div>
        </div>

        <!-- =================================================
             INFORMACIÓN
        ================================================== -->

        <div class="revision-info-box">
          <i class="ti ti-info-circle"></i>

          <div>
            <strong> ¿Qué ocurrirá después? </strong>

            <p>
              La alerta cambiará de
              <b>Planificada</b> a <b>En tratamiento</b>. La fecha y hora de inicio se registrarán
              automáticamente.
            </p>
          </div>
        </div>

        <!-- ERROR LOCAL -->

        <div v-if="localError" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ localError }}
        </div>

        <!-- ERROR BACKEND -->

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

        <button type="button" class="btn btn-p" :disabled="saving" @click="confirmar">
          <i :class="saving ? 'ti ti-loader-2' : 'ti ti-player-play'"></i>

          {{ saving ? 'Iniciando...' : 'Iniciar tratamiento' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

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
   DATOS
============================================================ */

const accion = ref('');

const localError = ref('');

/* ============================================================
   LIMPIAR AL CAMBIAR ALERTA
============================================================ */

watch(
  () => props.alerta?.id,

  () => {
    accion.value = '';

    localError.value = '';
  },

  {
    immediate: true,
  }
);

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
   PRÓXIMA REVISIÓN
============================================================ */

const proximaRevision = computed(() => {
  const fecha = props.alerta?.proxima_revision;

  if (!fecha) {
    return 'Sin programar';
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
});

/* ============================================================
   CONFIRMAR
============================================================ */

function confirmar() {
  localError.value = '';

  const texto = accion.value.trim();

  if (!texto) {
    localError.value = 'Debes registrar la acción realizada al iniciar el tratamiento.';

    return;
  }

  if (texto.length < 10) {
    localError.value = 'Describe la acción realizada con un poco más de detalle.';

    return;
  }

  emit('save', {
    accion_tomada: texto,
  });
}
</script>
