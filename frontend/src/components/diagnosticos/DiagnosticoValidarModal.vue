<template>
  <div
    class="modal-overlay diagnostico-modal-overlay"
    @click.self="close"
  >
    <div class="modal-card diagnostico-validar-modal">

      <!-- CABECERA -->
      <div class="diagnostico-validar-header">
        <div class="diagnostico-validar-icon">
          <i class="ti ti-shield-check"></i>
        </div>

        <div>
          <h3>
            Validar diagnóstico #{{ diagnostico?.id || '—' }}
          </h3>

          <p>
            Revisa el resultado preliminar y confirma, corrige
            o rechaza el diagnóstico.
          </p>
        </div>

        <button
          class="modal-close"
          type="button"
          @click="close"
        >
          ✕
        </button>
      </div>

      <!-- CUERPO -->
      <div class="diagnostico-validar-body">

        <!-- RESUMEN DEL DIAGNÓSTICO -->
        <div class="diagnostico-validar-summary">
          <div>
            <label>Enfermedad preliminar</label>

            <strong>
              {{
                diagnostico?.enfermedad?.nombre_comun
                || 'Sin clasificar'
              }}
            </strong>
          </div>

          <div>
            <label>Confianza CNN</label>

            <strong>
              {{ numero(diagnostico?.porcentaje_confianza) }}%
            </strong>
          </div>

          <div>
            <label>Severidad registrada</label>

            <strong>
              {{ textoSeveridad(diagnostico?.nivel_severidad) }}
            </strong>
          </div>
        </div>

        <!-- FOTO ORIGINAL Y MAPA DE CALOR (para decidir con información) -->
        <details class="diagnostico-validar-ia" open>
          <summary>
            <i class="ti ti-flame"></i>
            Foto y mapa de calor
          </summary>

          <DiagnosticoMapaCalor :diagnostico="diagnostico" />
        </details>

        <!-- LO QUE CALCULÓ LA IA (para decidir con información) -->
        <details class="diagnostico-validar-ia" open>
          <summary>
            <i class="ti ti-brain"></i>
            Lo que calculó la IA
          </summary>

          <DiagnosticoIaInforme :diagnostico="diagnostico" />
        </details>

        <!-- OPCIONES DE VALIDACIÓN -->
        <div class="diagnostico-validar-options">

          <button
            type="button"
            :class="{
              active: form.estado_validacion === 'Confirmado',
              confirmado: form.estado_validacion === 'Confirmado',
            }"
            @click="setEstado('Confirmado')"
          >
            <i class="ti ti-circle-check"></i>

            <strong>Confirmar</strong>

            <span>
              El diagnóstico generado por la CNN es correcto
            </span>
          </button>

          <button
            type="button"
            :class="{
              active: form.estado_validacion === 'Corregido',
              corregido: form.estado_validacion === 'Corregido',
            }"
            @click="setEstado('Corregido')"
          >
            <i class="ti ti-edit"></i>

            <strong>Corregir</strong>

            <span>
              Cambiar la enfermedad detectada
            </span>
          </button>

          <button
            type="button"
            :class="{
              active: form.estado_validacion === 'Rechazado',
              rechazado: form.estado_validacion === 'Rechazado',
            }"
            @click="setEstado('Rechazado')"
          >
            <i class="ti ti-circle-x"></i>

            <strong>Rechazar</strong>

            <span>
              Foto inválida o diagnóstico no utilizable
            </span>
          </button>

        </div>

        <!-- ENFERMEDAD CORRECTA -->
        <div
          v-if="form.estado_validacion === 'Corregido'"
          class="form-group"
        >
          <label for="id_enfermedad">
            Enfermedad correcta *
          </label>

          <select
            id="id_enfermedad"
            v-model="form.id_enfermedad"
          >
            <option value="">
              Selecciona la enfermedad correcta
            </option>

            <option
              v-for="enfermedad in enfermedades"
              :key="enfermedad.id"
              :value="enfermedad.id"
            >
              {{ enfermedad.nombre_comun }}
              —
              {{ enfermedad.codigo_enfermedad }}
            </option>
          </select>
        </div>

        <!-- DATOS AGRONÓMICOS -->
        <template v-if="form.estado_validacion !== 'Rechazado'">

          <!-- SEVERIDAD -->
          <div class="form-group">
            <label for="nivel_severidad">
              Severidad observada *
            </label>

            <select
              id="nivel_severidad"
              v-model="form.nivel_severidad"
            >
              <option value="">
                Selecciona la severidad
              </option>

              <option value="Sin_afeccion">
                Sin afección
              </option>

              <option value="Leve">
                Leve
              </option>

              <option value="Moderada">
                Moderada
              </option>

              <option value="Severa">
                Severa
              </option>

              <option value="Muy_severa">
                Muy severa
              </option>
            </select>

            <small>
              La CNN identifica la enfermedad. La severidad
              corresponde a la evaluación agronómica realizada
              durante la validación.
            </small>
          </div>

          <!-- PORCENTAJE DE PLANTAS AFECTADAS -->
          <div class="form-group">
            <label for="pct_plantas_afectadas">
              Porcentaje estimado de plantas afectadas *
            </label>

            <div class="input-percent-wrapper">
              <input
                id="pct_plantas_afectadas"
                v-model.number="form.pct_plantas_afectadas"
                type="number"
                min="0"
                max="100"
                step="0.1"
                placeholder="Ej. 35"
              />

              <span>%</span>
            </div>

            <small>
              Estima qué porcentaje de plantas presenta síntomas
              dentro de la inspección realizada.
            </small>
          </div>

        </template>

        <!-- OBSERVACIONES -->
        <div class="form-group">
          <label for="observaciones_validacion">
            Observaciones del supervisor
          </label>

          <textarea
            id="observaciones_validacion"
            v-model.trim="form.observaciones_validacion"
            maxlength="1000"
            placeholder="Registra observaciones sobre la validación, corrección o rechazo del diagnóstico..."
          ></textarea>

          <small>
            {{ form.observaciones_validacion.length }}/1000 caracteres
          </small>
        </div>

        <!-- ERROR LOCAL -->
        <div
          v-if="localError || error"
          class="diagnostico-form-error"
        >
          {{ localError || error }}
        </div>

      </div>

      <!-- FOOTER -->
      <div class="modal-footer">

        <button
          class="btn btn-s"
          type="button"
          :disabled="saving"
          @click="close"
        >
          Cancelar
        </button>

        <button
          class="btn btn-p"
          type="button"
          :disabled="saving"
          @click="submit"
        >
          <i class="ti ti-shield-check"></i>

          {{
            saving
              ? 'Guardando...'
              : 'Confirmar validación'
          }}
        </button>

      </div>

    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';

import DiagnosticoIaInforme from '@/components/diagnosticos/DiagnosticoIaInforme.vue';
import DiagnosticoMapaCalor from '@/components/diagnosticos/DiagnosticoMapaCalor.vue';

const props = defineProps({
  diagnostico: {
    type: Object,
    default: null,
  },

  enfermedades: {
    type: Array,
    default: () => [],
  },

  saving: {
    type: Boolean,
    default: false,
  },

  // Error devuelto por el servidor al validar.
  error: {
    type: String,
    default: '',
  },
});

const emit = defineEmits([
  'close',
  'save',
]);

const localError = ref('');

const form = reactive({
  estado_validacion: 'Confirmado',
  id_enfermedad: '',
  nivel_severidad: '',
  pct_plantas_afectadas: '',
  observaciones_validacion: '',
});

/*
|--------------------------------------------------------------------------
| CARGAR DIAGNÓSTICO
|--------------------------------------------------------------------------
*/
watch(
  () => props.diagnostico,
  () => {
    resetForm();
  },
  {
    immediate: true,
  }
);

/*
|--------------------------------------------------------------------------
| RESTABLECER FORMULARIO
|--------------------------------------------------------------------------
*/
function resetForm() {
  localError.value = '';

  form.estado_validacion = 'Confirmado';

  form.id_enfermedad =
    props.diagnostico?.id_enfermedad
    || props.diagnostico?.enfermedad?.id
    || '';

  form.nivel_severidad =
    props.diagnostico?.nivel_severidad
    || '';

  form.pct_plantas_afectadas =
    props.diagnostico?.inspeccion?.pct_plantas_afectadas
    ?? '';

  form.observaciones_validacion = '';
}

/*
|--------------------------------------------------------------------------
| CAMBIAR ESTADO
|--------------------------------------------------------------------------
*/
function setEstado(estado) {
  form.estado_validacion = estado;

  /*
   * Si no se está corrigiendo, se conserva
   * la enfermedad identificada originalmente.
   */
  if (estado !== 'Corregido') {
    form.id_enfermedad =
      props.diagnostico?.id_enfermedad
      || props.diagnostico?.enfermedad?.id
      || '';
  }

  /*
   * Si se rechaza el diagnóstico, la severidad
   * y el porcentaje de afectación dejan de ser obligatorios.
   */
  if (estado === 'Rechazado') {
    form.nivel_severidad = '';
    form.pct_plantas_afectadas = '';
  }

  localError.value = '';
}

/*
|--------------------------------------------------------------------------
| VALIDAR Y ENVIAR
|--------------------------------------------------------------------------
*/
function submit() {
  localError.value = '';

  if (!form.estado_validacion) {
    localError.value =
      'Debe seleccionar el resultado de validación.';

    return;
  }

  /*
   * Una corrección debe seleccionar una enfermedad.
   */
  if (
    form.estado_validacion === 'Corregido'
    && !form.id_enfermedad
  ) {
    localError.value =
      'Debe seleccionar la enfermedad correcta cuando el diagnóstico es corregido.';

    return;
  }

  /*
   * No tiene sentido corregir seleccionando
   * exactamente la misma enfermedad.
   */
  if (
    form.estado_validacion === 'Corregido'
    && String(form.id_enfermedad)
      === String(
        props.diagnostico?.id_enfermedad
        || props.diagnostico?.enfermedad?.id
        || ''
      )
  ) {
    localError.value =
      'Si la enfermedad es la misma, utilice Confirmar en lugar de Corregir.';

    return;
  }

  /*
   * Para Confirmado o Corregido se requieren
   * datos agronómicos.
   */
  if (form.estado_validacion !== 'Rechazado') {

    if (!form.nivel_severidad) {
      localError.value =
        'Debe registrar la severidad observada.';

      return;
    }

    if (
      form.pct_plantas_afectadas === ''
      || form.pct_plantas_afectadas === null
      || form.pct_plantas_afectadas === undefined
    ) {
      localError.value =
        'Debe registrar el porcentaje estimado de plantas afectadas.';

      return;
    }

    const porcentaje =
      Number(form.pct_plantas_afectadas);

    if (
      Number.isNaN(porcentaje)
      || porcentaje < 0
      || porcentaje > 100
    ) {
      localError.value =
        'El porcentaje de plantas afectadas debe estar entre 0 y 100.';

      return;
    }
  }

  if (
    form.observaciones_validacion.length > 1000
  ) {
    localError.value =
      'Las observaciones no deben superar los 1000 caracteres.';

    return;
  }

  /*
  |--------------------------------------------------------------------------
  | PAYLOAD
  |--------------------------------------------------------------------------
  */
  const payload = {
    estado_validacion:
      form.estado_validacion,

    id_enfermedad:
      form.id_enfermedad || null,

    nivel_severidad:
      form.estado_validacion === 'Rechazado'
        ? null
        : form.nivel_severidad,

    pct_plantas_afectadas:
      form.estado_validacion === 'Rechazado'
        ? null
        : Number(form.pct_plantas_afectadas),

    observaciones_validacion:
      form.observaciones_validacion || null,
  };

  emit('save', payload);
}

/*
|--------------------------------------------------------------------------
| CERRAR
|--------------------------------------------------------------------------
*/
function close() {
  if (props.saving) {
    return;
  }

  emit('close');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function numero(valor) {
  return Number(valor || 0).toLocaleString(
    'es-BO',
    {
      minimumFractionDigits: 1,
      maximumFractionDigits: 1,
    }
  );
}

function textoSeveridad(severidad) {
  if (!severidad) {
    return 'Sin severidad';
  }

  return String(severidad)
    .replaceAll('_', ' ');
}
</script>