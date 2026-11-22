<template>
  <Teleport to="body">
    <Transition name="ui-drawer">
      <div v-if="modelValue" class="ui-dr__fondo" @mousedown.self="pedirCierre">
        <aside
          ref="panel"
          class="ui-dr"
          :style="{ width: `min(${ancho}px, 100%)` }"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="`${uid}-t`"
          @keydown.tab="atraparTab"
        >
          <header class="ui-dr__cabeza">
            <div>
              <h2 :id="`${uid}-t`" class="ui-dr__titulo">{{ titulo }}</h2>
              <p v-if="subtitulo" class="ui-dr__subtitulo">{{ subtitulo }}</p>
            </div>
            <button type="button" class="ui-dr__cerrar" aria-label="Cerrar panel" @click="pedirCierre">
              <i class="ti ti-x" aria-hidden="true"></i>
            </button>
          </header>

          <div v-if="pasos.length" class="ui-dr__pasos"><StepIndicator :pasos="pasos" :actual="paso" /></div>

          <div class="ui-dr__medio">
            <form class="ui-dr__cuerpo" novalidate @submit.prevent="guardar">
              <slot :paso="paso" />
            </form>
            <aside v-if="$slots.lateral" class="ui-dr__lateral" aria-label="Vista previa"><slot name="lateral" /></aside>
          </div>

          <footer class="ui-dr__pie">
            <BaseButton variante="secundario" :disabled="guardando" @click="pedirCierre">Cancelar</BaseButton>

            <div class="ui-dr__pie-der">
              <BaseButton v-if="pasos.length && paso > 1" variante="secundario" :disabled="guardando" @click="anterior">Anterior</BaseButton>
              <BaseButton v-if="pasos.length && paso < pasos.length" :disabled="guardando" @click="siguiente">Siguiente</BaseButton>
              <BaseButton
                v-else
                :cargando="guardando"
                :texto-cargando="textoGuardando"
                :disabled="!puedeGuardar"
                @click="guardar"
              >
                {{ textoGuardar }}
              </BaseButton>
            </div>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import BaseButton from './BaseButton.vue';
import StepIndicator from './StepIndicator.vue';

// Panel lateral derecho (560 px; pantalla completa en celular) para crear y editar.
// Título + subtítulo, secciones (slot), pie fijo con Cancelar y Guardar, y pasos opcionales.
// `validarPaso(paso)` puede devolver false (o una promesa) para impedir avanzar.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  titulo: { type: String, required: true },
  subtitulo: { type: String, default: '' },
  pasos: { type: Array, default: () => [] },
  paso: { type: Number, default: 1 },
  guardando: { type: Boolean, default: false },
  puedeGuardar: { type: Boolean, default: true },
  textoGuardar: { type: String, default: 'Guardar' },
  textoGuardando: { type: String, default: 'Guardando…' },
  validarPaso: { type: Function, default: null },
  ancho: { type: Number, default: 560 },
});

const emit = defineEmits(['update:modelValue', 'update:paso', 'guardar', 'cancelar']);

const uid = `dr-${useId()}`;
const panel = ref(null);
let previo = null;

// Esc cierra el panel aunque el foco se haya perdido (por ejemplo al cambiar de paso); si hay una
// confirmación encima, Esc cierra primero la confirmación.
function alTeclado(evento) {
  if (evento.key === 'Escape' && !document.querySelector('.ui-conf__fondo')) pedirCierre();
}

watch(
  () => props.modelValue,
  async (abierto) => {
    document.body.style.overflow = abierto ? 'hidden' : '';
    window[abierto ? 'addEventListener' : 'removeEventListener']('keydown', alTeclado);

    if (abierto) {
      previo = document.activeElement;
      await nextTick();
      panel.value?.querySelector('input:not([type=hidden]), select, textarea')?.focus();
    } else {
      previo?.focus?.();
    }
  }
);

onBeforeUnmount(() => {
  document.body.style.overflow = '';
  window.removeEventListener('keydown', alTeclado);
});

function pedirCierre() {
  if (props.guardando) return;

  emit('update:modelValue', false);
  emit('cancelar');
}

async function siguiente() {
  if (props.validarPaso && (await props.validarPaso(props.paso)) === false) return;

  emit('update:paso', Math.min(props.pasos.length, props.paso + 1));
}

function anterior() {
  emit('update:paso', Math.max(1, props.paso - 1));
}

async function guardar() {
  if (props.guardando) return;

  // Enter dentro de un formulario con pasos avanza en vez de guardar.
  if (props.pasos.length && props.paso < props.pasos.length) return siguiente();

  if (props.validarPaso && (await props.validarPaso(props.paso)) === false) return;

  emit('guardar');
}

function atraparTab(evento) {
  const enfocables = [...panel.value.querySelectorAll('a[href], button:not(:disabled), input:not([type=hidden]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')];

  if (!enfocables.length) return;

  const primero = enfocables[0];
  const ultimo = enfocables.at(-1);

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
.ui-dr__fondo {
  position: fixed;
  inset: 0;
  z-index: 1500;
  display: flex;
  justify-content: flex-end;
  background: rgba(15, 23, 42, 0.5);
}

.ui-dr {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #fff;
  box-shadow: -18px 0 50px rgba(15, 23, 42, 0.25);
}

.ui-dr__cabeza {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 22px 24px 14px;
}

.ui-dr__titulo {
  margin: 0;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: #111827;
}

.ui-dr__subtitulo {
  margin: 4px 0 0;
  font-size: 13px;
  color: #4a6070;
}

.ui-dr__cerrar {
  flex: none;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 12px;
  background: #f4f7f9;
  font-size: 20px;
  color: #111827;
  cursor: pointer;
}

.ui-dr__cerrar:focus-visible {
  outline: 3px solid #3da87a;
}

.ui-dr__pasos {
  padding: 0 24px 8px;
}

.ui-dr__medio {
  flex: 1;
  min-height: 0;
  display: flex;
}

.ui-dr__lateral {
  flex: 0 0 300px;
  overflow-y: auto;
  padding: 12px 24px 24px 0;
}

@media (max-width: 1100px) {
  .ui-dr__lateral {
    display: none;
  }
}

.ui-dr__cuerpo {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 22px;
  padding: 12px 24px 24px;
}

.ui-dr__pie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 24px;
  border-top: 1px solid #dce6ed;
  background: #fff;
}

.ui-dr__pie-der {
  display: flex;
  gap: 10px;
}

.ui-drawer-enter-active,
.ui-drawer-leave-active {
  transition: background 0.2s ease;
}

.ui-drawer-enter-active .ui-dr,
.ui-drawer-leave-active .ui-dr {
  transition: transform 0.22s ease;
}

.ui-drawer-enter-from,
.ui-drawer-leave-to {
  background: rgba(15, 23, 42, 0);
}

.ui-drawer-enter-from .ui-dr,
.ui-drawer-leave-to .ui-dr {
  transform: translateX(40px);
}

@media (max-width: 767px) {
  .ui-dr {
    width: 100%;
  }

  .ui-dr__cabeza,
  .ui-dr__pie {
    padding-left: 16px;
    padding-right: 16px;
  }

  .ui-dr__pasos,
  .ui-dr__cuerpo {
    padding-left: 16px;
    padding-right: 16px;
  }

  .ui-dr__pie {
    flex-wrap: wrap;
  }

  .ui-dr__pie-der {
    flex: 1;
    justify-content: flex-end;
  }
}
</style>
