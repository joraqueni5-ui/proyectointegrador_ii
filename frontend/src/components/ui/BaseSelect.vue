<template>
  <div ref="raiz" class="ui-sel" :class="{ 'tiene-error': mostrarError, 'is-abierto': abierto }">
    <label v-if="etiqueta" class="ui-sel__etiqueta" :for="idCampo">
      {{ etiqueta }}<span v-if="obligatorio" class="ui-sel__req" aria-hidden="true"> *</span>
    </label>

    <div class="ui-sel__caja">
      <input
        :id="idCampo"
        ref="entrada"
        class="ui-sel__control"
        type="text"
        role="combobox"
        autocomplete="off"
        :aria-expanded="abierto"
        :aria-controls="`${idCampo}-lista`"
        :aria-invalid="mostrarError ? 'true' : undefined"
        :aria-describedby="mostrarError ? `${idCampo}-error` : undefined"
        :placeholder="seleccionada ? '' : placeholder"
        :disabled="disabled"
        :value="abierto ? consulta : seleccionada?.etiqueta || ''"
        @focus="abrir"
        @click="abrir"
        @input="alEscribir"
        @keydown.down.prevent="mover(1)"
        @keydown.up.prevent="mover(-1)"
        @keydown.enter.prevent="elegirActual"
        @keydown.esc="cerrar"
        @blur="alSalir"
      />
      <button
        v-if="limpiable && seleccionada && !disabled"
        type="button"
        class="ui-sel__limpiar"
        aria-label="Quitar selección"
        @mousedown.prevent="elegir(null)"
      >
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
      <i class="ti ti-chevron-down ui-sel__flecha" aria-hidden="true"></i>
    </div>

    <ul v-if="abierto" :id="`${idCampo}-lista`" class="ui-sel__lista" role="listbox">
      <li v-if="!filtradas.length" class="ui-sel__vacio">{{ textoVacio }}</li>
      <li
        v-for="(op, i) in filtradas"
        :key="op.valor"
        role="option"
        class="ui-sel__op"
        :class="{ 'is-activa': i === indice, 'is-elegida': op.valor === modelValue, 'is-deshabilitada': op.deshabilitada }"
        :aria-selected="op.valor === modelValue"
        @mousedown.prevent="!op.deshabilitada && elegir(op)"
        @mousemove="indice = i"
      >
        <strong>{{ op.etiqueta }}</strong>
        <small v-if="op.detalle">{{ op.detalle }}</small>
      </li>
    </ul>

    <p v-if="mostrarError" :id="`${idCampo}-error`" class="ui-sel__error" role="alert">{{ error }}</p>
    <p v-else-if="ayuda" class="ui-sel__ayuda">{{ ayuda }}</p>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, useId, watch } from 'vue';

// Lista desplegable con buscador. opciones: [{ valor, etiqueta, detalle?, deshabilitada? }].
// Cada opción muestra la etiqueta en negrita y un dato secundario en gris.
const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  opciones: { type: Array, default: () => [] },
  etiqueta: { type: String, default: '' },
  obligatorio: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Escribe para buscar…' },
  ayuda: { type: String, default: '' },
  error: { type: String, default: '' },
  mostrarErrores: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  limpiable: { type: Boolean, default: false },
  textoVacio: { type: String, default: 'Sin resultados.' },
});

const emit = defineEmits(['update:modelValue', 'cambio']);

const uid = useId();
const idCampo = `sel-${uid}`;
const raiz = ref(null);
const entrada = ref(null);
const abierto = ref(false);
const consulta = ref('');
const indice = ref(0);
const tocado = ref(false);

const seleccionada = computed(() => props.opciones.find((o) => o.valor === props.modelValue) || null);
const mostrarError = computed(() => Boolean(props.error) && (tocado.value || props.mostrarErrores));

function normalizar(t) {
  return String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

const filtradas = computed(() => {
  const q = normalizar(consulta.value).trim();

  if (!q) return props.opciones;

  return props.opciones.filter((o) => normalizar(`${o.etiqueta} ${o.detalle || ''}`).includes(q));
});

watch(filtradas, () => (indice.value = 0));

function abrir() {
  if (props.disabled || abierto.value) return;

  abierto.value = true;
  consulta.value = '';
  indice.value = Math.max(0, props.opciones.findIndex((o) => o.valor === props.modelValue));
  nextTick(() => entrada.value?.select());
}

function cerrar() {
  abierto.value = false;
  consulta.value = '';
}

function alSalir() {
  tocado.value = true;
  cerrar();
}

function alEscribir(evento) {
  consulta.value = evento.target.value;
  abierto.value = true;
}

function mover(paso) {
  if (!abierto.value) return abrir();
  if (!filtradas.value.length) return;

  indice.value = (indice.value + paso + filtradas.value.length) % filtradas.value.length;
  nextTick(() => raiz.value?.querySelector('.is-activa')?.scrollIntoView({ block: 'nearest' }));
}

function elegirActual() {
  const op = filtradas.value[indice.value];

  if (op && !op.deshabilitada) elegir(op);
}

function elegir(opcion) {
  emit('update:modelValue', opcion ? opcion.valor : null);
  emit('cambio', opcion);
  tocado.value = true;
  cerrar();
  entrada.value?.blur();
}
</script>

<style scoped>
.ui-sel {
  position: relative;
  display: grid;
  gap: 6px;
}

.ui-sel__etiqueta {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.ui-sel__req {
  color: #b91c1c;
}

.ui-sel__caja {
  position: relative;
  display: flex;
  align-items: center;
  background: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 12px;
  min-height: 48px;
}

.ui-sel__caja:focus-within {
  border-color: #3da87a;
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.14);
}

.tiene-error .ui-sel__caja {
  border-color: #dc2626;
}

.ui-sel__control {
  flex: 1;
  min-width: 0;
  height: 44px;
  border: 0;
  outline: 0;
  background: transparent;
  padding: 0 8px 0 14px;
  font-family: inherit;
  font-size: 15px;
  color: #111827;
  text-overflow: ellipsis;
}

.ui-sel__control::placeholder {
  color: #64748b;
}

.ui-sel__limpiar {
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #4a6070;
  cursor: pointer;
}

.ui-sel__flecha {
  padding: 0 12px 0 4px;
  color: #4a6070;
  pointer-events: none;
}

.ui-sel__lista {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 40;
  margin: 0;
  padding: 6px;
  list-style: none;
  max-height: 280px;
  overflow-y: auto;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
}

.ui-sel__vacio {
  padding: 14px 12px;
  color: #4a6070;
  font-size: 13px;
}

.ui-sel__op {
  display: grid;
  gap: 2px;
  min-height: 44px;
  padding: 8px 12px;
  border-radius: 10px;
  cursor: pointer;
}

.ui-sel__op strong {
  font-size: 14px;
  color: #111827;
}

.ui-sel__op small {
  font-size: 12px;
  color: #4a6070;
}

.ui-sel__op.is-activa {
  background: #edfaf4;
}

.ui-sel__op.is-elegida strong {
  color: #1a5c3e;
}

.ui-sel__op.is-deshabilitada {
  opacity: 0.5;
  cursor: not-allowed;
}

.ui-sel__error {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #b91c1c;
}

.ui-sel__ayuda {
  margin: 0;
  font-size: 12px;
  color: #4a6070;
}
</style>
