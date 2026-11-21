<template>
  <Teleport to="body">
    <div v-if="modelValue" class="ui-conf__fondo" @mousedown.self="cancelar">
      <div
        ref="caja"
        class="ui-conf"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="`${uid}-t`"
        :aria-describedby="`${uid}-d`"
        @keydown.tab="atraparTab"
      >
        <h2 :id="`${uid}-t`" class="ui-conf__titulo">{{ titulo }}</h2>
        <div :id="`${uid}-d`" class="ui-conf__texto">
          <slot>{{ mensaje }}</slot>
        </div>

        <div class="ui-conf__acciones">
          <BaseButton variante="secundario" :disabled="cargando" @click="cancelar">{{ textoCancelar }}</BaseButton>
          <BaseButton
            :variante="peligro ? 'peligro' : 'primario'"
            :cargando="cargando"
            :texto-cargando="textoCargando"
            @click="$emit('confirmar')"
          >
            {{ textoConfirmar }}
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import BaseButton from './BaseButton.vue';

// Confirmación de acciones destructivas o irreversibles: una pregunta como título, qué va a pasar
// exactamente, "No, volver" y la acción en rojo ("Sí, anular").
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  titulo: { type: String, required: true },
  mensaje: { type: String, default: '' },
  textoConfirmar: { type: String, default: 'Sí, continuar' },
  textoCancelar: { type: String, default: 'No, volver' },
  textoCargando: { type: String, default: 'Procesando…' },
  peligro: { type: Boolean, default: true },
  cargando: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirmar', 'cancelar']);

const uid = `conf-${useId()}`;
const caja = ref(null);
let previo = null;

function alTeclado(evento) {
  if (evento.key === 'Escape') cancelar();
}

watch(
  () => props.modelValue,
  async (abierto) => {
    window[abierto ? 'addEventListener' : 'removeEventListener']('keydown', alTeclado);

    if (abierto) {
      previo = document.activeElement;
      await nextTick();
      // El foco empieza en la opción segura ("No, volver").
      caja.value?.querySelector('button')?.focus();
    } else {
      previo?.focus?.();
    }
  }
);

onBeforeUnmount(() => window.removeEventListener('keydown', alTeclado));

function cancelar() {
  if (props.cargando) return;

  emit('update:modelValue', false);
  emit('cancelar');
}

function atraparTab(evento) {
  const botones = [...caja.value.querySelectorAll('button:not(:disabled)')];

  if (!botones.length) return;

  const primero = botones[0];
  const ultimo = botones.at(-1);

  if (evento.shiftKey && document.activeElement === primero) {
    evento.preventDefault();
    ultimo.focus();
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault();
    primero.focus();
  }
}
</script>

<style scoped>
.ui-conf__fondo {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.55);
}

.ui-conf {
  width: min(440px, 100%);
  padding: 24px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.3);
  display: grid;
  gap: 12px;
}

.ui-conf__titulo {
  margin: 0;
  font-size: 19px;
  font-weight: 800;
  color: #111827;
}

.ui-conf__texto {
  font-size: 14px;
  line-height: 1.5;
  color: #4a6070;
}

.ui-conf__acciones {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 8px;
}

@media (max-width: 480px) {
  .ui-conf__acciones > * {
    flex: 1;
  }
}
</style>
