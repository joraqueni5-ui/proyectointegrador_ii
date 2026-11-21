<template>
  <fieldset class="ui-rc" :class="{ 'tiene-error': mostrarError }" :aria-describedby="mostrarError ? `${nombre}-error` : undefined">
    <legend v-if="etiqueta" class="ui-rc__etiqueta">
      {{ etiqueta }}<span v-if="obligatorio" class="ui-rc__req" aria-hidden="true"> *</span>
    </legend>

    <div class="ui-rc__rejilla" :style="{ '--columnas': columnas }">
      <label
        v-for="op in opciones"
        :key="op.valor"
        class="ui-rc__tarjeta"
        :class="{ 'is-elegida': op.valor === modelValue, 'is-deshabilitada': op.deshabilitada }"
      >
        <input
          type="radio"
          class="ui-rc__radio"
          :name="nombre"
          :value="op.valor"
          :checked="op.valor === modelValue"
          :disabled="disabled || op.deshabilitada"
          @change="elegir(op.valor)"
        />
        <i v-if="op.icono" :class="['ti', op.icono, 'ui-rc__icono']" aria-hidden="true"></i>
        <span class="ui-rc__texto">
          <strong>{{ op.titulo }}</strong>
          <small v-if="op.descripcion">{{ op.descripcion }}</small>
        </span>
      </label>
    </div>

    <p v-if="mostrarError" :id="`${nombre}-error`" class="ui-rc__error" role="alert">{{ error }}</p>
  </fieldset>
</template>

<script setup>
import { computed, ref, useId } from 'vue';

// Opciones como tarjetas grandes. opciones: [{ valor, titulo, descripcion?, icono?, deshabilitada? }].
const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  opciones: { type: Array, default: () => [] },
  etiqueta: { type: String, default: '' },
  obligatorio: { type: Boolean, default: false },
  columnas: { type: Number, default: 3 },
  error: { type: String, default: '' },
  mostrarErrores: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const nombre = `rc-${useId()}`;
const tocado = ref(false);
const mostrarError = computed(() => Boolean(props.error) && (tocado.value || props.mostrarErrores));

function elegir(valor) {
  tocado.value = true;
  emit('update:modelValue', valor);
}
</script>

<style scoped>
.ui-rc {
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
}

.ui-rc__etiqueta {
  padding: 0;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.ui-rc__req {
  color: #b91c1c;
}

.ui-rc__rejilla {
  display: grid;
  grid-template-columns: repeat(var(--columnas), minmax(0, 1fr));
  gap: 10px;
}

.ui-rc__tarjeta {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-height: 64px;
  padding: 12px 14px;
  background: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 14px;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease;
}

.ui-rc__tarjeta:hover {
  border-color: #8ba3b5;
}

.ui-rc__tarjeta.is-elegida {
  border-color: #1a5c3e;
  background: #edfaf4;
}

.ui-rc__tarjeta:focus-within {
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.25);
}

.ui-rc__tarjeta.is-deshabilitada {
  opacity: 0.55;
  cursor: not-allowed;
}

.ui-rc__radio {
  position: absolute;
  opacity: 0;
  inset: 0;
  margin: 0;
  cursor: inherit;
}

.ui-rc__icono {
  font-size: 22px;
  color: #1a5c3e;
}

.ui-rc__texto {
  display: grid;
  gap: 2px;
}

.ui-rc__texto strong {
  font-size: 14px;
  color: #111827;
}

.ui-rc__texto small {
  font-size: 12px;
  color: #4a6070;
  line-height: 1.35;
}

.ui-rc__error {
  margin: 8px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #b91c1c;
}

.tiene-error .ui-rc__tarjeta {
  border-color: #dc2626;
}

@media (max-width: 560px) {
  .ui-rc__rejilla {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
