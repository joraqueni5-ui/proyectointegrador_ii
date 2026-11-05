<template>
  <div class="modal-overlay alerta-modal-overlay" @click.self="$emit('close')">
    <div class="modal alerta-atender-modal">
      <!-- CABECERA -->
      <div class="alerta-atender-header">
        <div class="alerta-atender-icon">
          <i class="ti ti-clipboard-search"></i>
        </div>

        <div>
          <h3>Iniciar revisión</h3>

          <p>
            Inicia la evaluación de esta alerta para definir posteriormente el responsable y el plan
            de manejo.
          </p>
        </div>

        <button type="button" class="modal-close" :disabled="saving" @click="$emit('close')">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <!-- CUERPO -->
      <div class="alerta-atender-body">
        <!-- ALERTA SELECCIONADA -->
        <div class="alerta-atender-summary">
          <span> ALERTA SELECCIONADA </span>

          <strong>
            {{ alerta?.titulo || 'Alerta fitosanitaria' }}
          </strong>

          <p>
            {{ alerta?.mensaje || 'Sin información adicional.' }}
          </p>
        </div>

        <!-- EXPLICACIÓN -->
        <div class="revision-info-box">
          <i class="ti ti-info-circle"></i>

          <div>
            <strong> ¿Qué ocurrirá? </strong>

            <p>
              La alerta cambiará de
              <b>Nueva</b> a <b>En revisión</b>. Todavía no se marcará como resuelta.
            </p>
          </div>
        </div>

        <!-- OBSERVACIÓN OPCIONAL -->
        <div class="form-group">
          <label for="observacionRevision">
            Observación inicial
            <span class="optional"> opcional </span>
          </label>

          <textarea
            id="observacionRevision"
            v-model="observacion"
            maxlength="1500"
            :disabled="saving"
            placeholder="Ej. Se realizará una inspección detallada de la parcela para definir las acciones de manejo..."
          ></textarea>

          <div class="revision-char-count">{{ observacion.length }}/1500</div>
        </div>

        <!-- ERROR -->
        <div v-if="error" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ error }}
        </div>
      </div>

      <!-- FOOTER -->
      <div class="modal-footer">
        <button type="button" class="btn btn-s" :disabled="saving" @click="$emit('close')">
          Cancelar
        </button>

        <button type="button" class="btn btn-p" :disabled="saving" @click="confirmar">
          <i :class="saving ? 'ti ti-loader-2' : 'ti ti-player-play'"></i>

          {{ saving ? 'Iniciando...' : 'Iniciar revisión' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

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

const emit = defineEmits(['close', 'save']);

const observacion = ref('');

watch(
  () => props.alerta?.id,

  () => {
    observacion.value = '';
  },

  {
    immediate: true,
  }
);

function confirmar() {
  if (props.saving) {
    return;
  }

  const texto = observacion.value.trim();

  emit('save', {
    accion_tomada: texto || null,
  });
}
</script>
