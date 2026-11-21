<template>
  <div ref="raiz" class="ui-rm">
    <button
      type="button"
      class="ui-rm__boton"
      :aria-label="`Más acciones: ${etiqueta}`"
      aria-haspopup="menu"
      :aria-expanded="abierto"
      @click.stop="abierto = !abierto"
      @keydown.esc="abierto = false"
    >
      <i class="ti ti-dots" aria-hidden="true"></i>
    </button>

    <div v-if="abierto" class="ui-rm__menu" role="menu">
      <button
        v-for="a in acciones"
        :key="a.id"
        type="button"
        role="menuitem"
        class="ui-rm__opcion"
        :class="{ 'es-peligro': a.peligro }"
        :disabled="a.deshabilitada"
        @click.stop="elegir(a)"
      >
        <i v-if="a.icono" :class="['ti', a.icono]" aria-hidden="true"></i>{{ a.etiqueta }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Menú "⋯" de una fila: Editar, Cambiar estado, Eliminar…
defineProps({
  acciones: { type: Array, default: () => [] }, // [{ id, etiqueta, icono?, peligro?, deshabilitada? }]
  etiqueta: { type: String, default: 'fila' },
});

const emit = defineEmits(['accion']);

const abierto = ref(false);
const raiz = ref(null);

function elegir(a) {
  abierto.value = false;
  emit('accion', a.id);
}

function clicFuera(e) {
  if (raiz.value && !raiz.value.contains(e.target)) abierto.value = false;
}

onMounted(() => document.addEventListener('mousedown', clicFuera));
onBeforeUnmount(() => document.removeEventListener('mousedown', clicFuera));
</script>

<style scoped>
.ui-rm {
  position: relative;
  display: inline-block;
}

.ui-rm__boton {
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  font-size: 20px;
  color: #4a6070;
  cursor: pointer;
}

.ui-rm__boton:hover {
  background: #f4f7f9;
}

.ui-rm__boton:focus-visible,
.ui-rm__opcion:focus-visible {
  outline: 3px solid #3da87a;
  outline-offset: 2px;
}

.ui-rm__menu {
  position: absolute;
  right: 0;
  top: 100%;
  z-index: 30;
  min-width: 190px;
  padding: 6px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.2);
}

.ui-rm__opcion {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 44px;
  padding: 0 12px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  text-align: left;
  cursor: pointer;
}

.ui-rm__opcion:hover:not(:disabled) {
  background: #f4f7f9;
}

.ui-rm__opcion.es-peligro {
  color: #b91c1c;
}

.ui-rm__opcion:disabled {
  color: #4a6070;
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
