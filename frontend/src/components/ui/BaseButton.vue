<template>
  <button
    :type="type"
    class="ui-btn"
    :class="[`ui-btn--${variante}`, { 'ui-btn--block': bloque, 'is-cargando': cargando }]"
    :disabled="disabled"
    :aria-busy="cargando ? 'true' : undefined"
    @click.capture="bloquearSiCarga"
  >
    <span v-if="cargando" class="ui-btn__spinner" aria-hidden="true"></span>
    <i v-else-if="icono" :class="['ti', icono]" aria-hidden="true"></i>
    <span class="ui-btn__texto">
      <template v-if="cargando">{{ textoCargando }}</template>
      <slot v-else />
    </span>
  </button>
</template>

<script setup>
// Botón base. variante: primario | secundario | peligro | fantasma.
// `cargando` muestra el spinner y el texto de la acción en curso, y bloquea el doble clic.
const props = defineProps({
  variante: { type: String, default: 'primario' },
  type: { type: String, default: 'button' },
  cargando: { type: Boolean, default: false },
  textoCargando: { type: String, default: 'Guardando…' },
  disabled: { type: Boolean, default: false },
  bloque: { type: Boolean, default: false },
  icono: { type: String, default: '' },
});

function bloquearSiCarga(evento) {
  if (props.cargando) {
    evento.stopImmediatePropagation();
    evento.preventDefault();
  }
}
</script>

<style scoped>
.ui-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 46px;
  padding: 0 20px;
  border: 2px solid transparent;
  border-radius: 12px;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.ui-btn:focus-visible {
  outline: 0;
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.3);
}

.ui-btn--bloque,
.ui-btn--block {
  width: 100%;
}

.ui-btn--primario {
  background: #1a5c3e;
  color: #fff;
}

.ui-btn--primario:hover {
  background: #0d3d2b;
}

.ui-btn--secundario {
  background: #fff;
  color: #111827;
  border-color: #cbd5e1;
}

.ui-btn--secundario:hover {
  background: #f4f7f9;
}

.ui-btn--peligro {
  background: #b91c1c;
  color: #fff;
}

.ui-btn--peligro:hover {
  background: #991b1b;
}

.ui-btn--fantasma {
  background: transparent;
  color: #1a5c3e;
  padding: 0 12px;
}

.ui-btn--fantasma:hover {
  background: #edfaf4;
}

/* Deshabilitado: gris, nunca verde */
.ui-btn:disabled {
  background: #dce6ed;
  border-color: transparent;
  color: #4a6070;
  cursor: not-allowed;
}

.is-cargando {
  cursor: progress;
}

.ui-btn__spinner {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 2px solid currentColor;
  border-right-color: transparent;
  animation: ui-giro 0.7s linear infinite;
}

.ui-btn i {
  font-size: 18px;
}

@keyframes ui-giro {
  to {
    transform: rotate(360deg);
  }
}
</style>
