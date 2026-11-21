<template>
  <Teleport to="body">
    <Transition name="ui-detalle">
      <div v-if="modelValue" class="ui-dd__fondo" @mousedown.self="cerrar">
        <aside
          ref="panel"
          class="ui-dd"
          :style="{ width: `min(${ancho}px, 100%)` }"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="`${uid}-t`"
          @keydown.esc.stop="cerrar"
          @keydown.tab="atraparTab"
        >
          <header class="ui-dd__cabeza">
            <div class="ui-dd__identidad">
              <h2 :id="`${uid}-t`" class="ui-dd__titulo">{{ titulo }}</h2>
              <div class="ui-dd__meta">
                <span v-if="codigo" class="codigo">{{ codigo }}</span>
                <slot name="estado" />
              </div>
              <p v-if="subtitulo" class="ui-dd__subtitulo">{{ subtitulo }}</p>
            </div>
            <button type="button" class="ui-dd__cerrar" aria-label="Cerrar panel" @click="cerrar">
              <i class="ti ti-x" aria-hidden="true"></i>
            </button>
          </header>

          <div class="ui-dd__cuerpo"><slot /></div>

          <footer v-if="$slots.acciones" class="ui-dd__pie"><slot name="acciones" /></footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

// Vista de "Ver" en el mismo panel lateral: encabezado (nombre, código, estado) + bloques de datos
// (usa <DetailBlock> dentro del slot) + acciones en el pie (slot `acciones`).
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  titulo: { type: String, required: true },
  codigo: { type: String, default: '' },
  subtitulo: { type: String, default: '' },
  ancho: { type: Number, default: 560 },
});

const emit = defineEmits(['update:modelValue', 'cerrar']);

const uid = `dd-${useId()}`;
const panel = ref(null);
let previo = null;

watch(
  () => props.modelValue,
  async (abierto) => {
    document.body.style.overflow = abierto ? 'hidden' : '';

    if (abierto) {
      previo = document.activeElement;
      await nextTick();
      panel.value?.querySelector('.ui-dd__cerrar')?.focus();
    } else {
      previo?.focus?.();
    }
  }
);

onBeforeUnmount(() => {
  document.body.style.overflow = '';
});

function cerrar() {
  emit('update:modelValue', false);
  emit('cerrar');
}

function atraparTab(evento) {
  const enfocables = [...panel.value.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')];

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
.ui-dd__fondo {
  position: fixed;
  inset: 0;
  z-index: 1500;
  display: flex;
  justify-content: flex-end;
  background: rgba(15, 23, 42, 0.5);
}

.ui-dd {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: #fff;
  box-shadow: -18px 0 50px rgba(15, 23, 42, 0.25);
}

.ui-dd__cabeza {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 22px 24px 16px;
  border-bottom: 1px solid #dce6ed;
}

.ui-dd__titulo {
  margin: 0 0 8px;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.01em;
  color: #111827;
}

.ui-dd__meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.ui-dd__subtitulo {
  margin: 8px 0 0;
  font-size: 13px;
  color: #4a6070;
}

.ui-dd__cerrar {
  flex: none;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 12px;
  background: #f4f7f9;
  font-size: 20px;
  cursor: pointer;
}

.ui-dd__cuerpo {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: grid;
  align-content: start;
  gap: 22px;
  padding: 20px 24px 28px;
}

.ui-dd__pie {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  padding: 14px 24px;
  border-top: 1px solid #dce6ed;
}

.ui-detalle-enter-active,
.ui-detalle-leave-active {
  transition: background 0.2s ease;
}

.ui-detalle-enter-active .ui-dd,
.ui-detalle-leave-active .ui-dd {
  transition: transform 0.22s ease;
}

.ui-detalle-enter-from,
.ui-detalle-leave-to {
  background: rgba(15, 23, 42, 0);
}

.ui-detalle-enter-from .ui-dd,
.ui-detalle-leave-to .ui-dd {
  transform: translateX(40px);
}

@media (max-width: 767px) {
  .ui-dd {
    width: 100% !important;
  }

  .ui-dd__cabeza,
  .ui-dd__cuerpo,
  .ui-dd__pie {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
