<template>
  <div class="ui-campo" :class="{ 'tiene-error': mostrarError, 'is-deshabilitado': disabled }">
    <label v-if="etiqueta" class="ui-campo__etiqueta" :for="idCampo">
      {{ etiqueta }}<span v-if="obligatorio" class="ui-campo__req" aria-hidden="true"> *</span>
    </label>

    <div class="ui-campo__caja">
      <textarea
        v-if="multilinea"
        :id="idCampo"
        class="ui-campo__control"
        :value="modelValue"
        :rows="filas"
        :placeholder="placeholder"
        :maxlength="maxlength"
        :disabled="disabled"
        :required="obligatorio"
        :aria-invalid="mostrarError ? 'true' : undefined"
        :aria-describedby="descripcionId"
        @input="alEscribir"
        @blur="alSalir"
      ></textarea>
      <input
        v-else
        :id="idCampo"
        class="ui-campo__control"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :maxlength="maxlength"
        :min="min"
        :max="max"
        :step="step"
        :inputmode="inputmode"
        :autocomplete="autocomplete"
        :disabled="disabled"
        :required="obligatorio"
        :aria-invalid="mostrarError ? 'true' : undefined"
        :aria-describedby="descripcionId"
        @input="alEscribir"
        @blur="alSalir"
      />
      <span v-if="unidad" class="ui-campo__unidad">{{ unidad }}</span>
    </div>

    <p v-if="mostrarError" :id="`${idCampo}-error`" class="ui-campo__error" role="alert">{{ error }}</p>
    <p v-else-if="ayuda" :id="`${idCampo}-ayuda`" class="ui-campo__ayuda">{{ ayuda }}</p>
  </div>
</template>

<script setup>
import { computed, ref, useId } from 'vue';

// Campo de texto. El error aparece al salir del campo o al enviar (`mostrarErrores`), no mientras
// se escribe por primera vez. Los textos se recortan (trim) al salir del campo.
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  etiqueta: { type: String, default: '' },
  id: { type: String, default: '' },
  type: { type: String, default: 'text' },
  obligatorio: { type: Boolean, default: false },
  unidad: { type: String, default: '' },
  ayuda: { type: String, default: '' },
  error: { type: String, default: '' },
  mostrarErrores: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  maxlength: { type: [Number, String], default: undefined },
  min: { type: [Number, String], default: undefined },
  max: { type: [Number, String], default: undefined },
  step: { type: [Number, String], default: undefined },
  inputmode: { type: String, default: undefined },
  autocomplete: { type: String, default: 'off' },
  disabled: { type: Boolean, default: false },
  multilinea: { type: Boolean, default: false },
  filas: { type: Number, default: 3 },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const uid = useId();
const idCampo = computed(() => props.id || `campo-${uid}`);
const tocado = ref(false);

const mostrarError = computed(() => Boolean(props.error) && (tocado.value || props.mostrarErrores));
const descripcionId = computed(() =>
  mostrarError.value ? `${idCampo.value}-error` : props.ayuda ? `${idCampo.value}-ayuda` : undefined
);

function alEscribir(evento) {
  emit('update:modelValue', evento.target.value);
}

function alSalir(evento) {
  tocado.value = true;

  const valor = evento.target.value;
  const recortado = typeof valor === 'string' ? valor.trim() : valor;

  if (props.type !== 'password' && recortado !== valor) {
    emit('update:modelValue', recortado);
  }

  emit('blur', evento);
}
</script>

<style scoped>
.ui-campo {
  display: grid;
  gap: 6px;
}

.ui-campo__etiqueta {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.ui-campo__req {
  color: #b91c1c;
}

.ui-campo__caja {
  display: flex;
  align-items: stretch;
  background: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 12px;
  overflow: hidden;
  min-height: 48px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.ui-campo__caja:focus-within {
  border-color: #3da87a;
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.14);
}

.tiene-error .ui-campo__caja {
  border-color: #dc2626;
}

.tiene-error .ui-campo__caja:focus-within {
  box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.14);
}

.ui-campo__control {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  padding: 0 14px;
  font-family: inherit;
  font-size: 15px;
  color: #111827;
}

textarea.ui-campo__control {
  padding: 12px 14px;
  resize: vertical;
}

.ui-campo__control::placeholder {
  color: #64748b;
}

.ui-campo__unidad {
  display: grid;
  place-items: center;
  padding: 0 14px;
  background: #f4f7f9;
  border-left: 1px solid #dce6ed;
  color: #4a6070;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

.ui-campo__error {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #b91c1c;
}

.ui-campo__ayuda {
  margin: 0;
  font-size: 12px;
  color: #4a6070;
}

.is-deshabilitado .ui-campo__caja {
  background: #f4f7f9;
}
</style>
