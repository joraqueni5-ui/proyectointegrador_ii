<template>
  <Teleport to="body">
    <div class="ui-avisos" aria-live="polite">
      <div v-for="a in avisos.items" :key="a.id" class="ui-aviso" :class="`ui-aviso--${a.tipo}`" role="status">
        <i :class="['ti', a.tipo === 'error' ? 'ti-alert-circle' : 'ti-circle-check']" aria-hidden="true"></i>
        <span class="ui-aviso__mensaje">{{ a.mensaje }}</span>
        <RouterLink v-if="a.enlace" :to="a.enlace.to" class="ui-aviso__enlace" @click="cerrar(a.id)">
          {{ a.enlace.texto || 'Ver' }}
        </RouterLink>
        <button type="button" class="ui-aviso__cerrar" aria-label="Cerrar aviso" @click="cerrar(a.id)">
          <i class="ti ti-x" aria-hidden="true"></i>
        </button>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { avisos, useToast } from '@/composables/useToast';

// Avisos abajo a la derecha (4 s). Se monta una sola vez en el layout.
const { cerrar } = useToast();
</script>

<style scoped>
.ui-avisos {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 3000;
  display: grid;
  gap: 10px;
  max-width: calc(100vw - 32px);
}

.ui-aviso {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 260px;
  padding: 12px 14px;
  background: #0d3d2b;
  color: #fff;
  border-radius: 14px;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.3);
  font-size: 14px;
  font-weight: 600;
  animation: ui-aviso-entra 0.2s ease both;
}

.ui-aviso > i:first-child {
  font-size: 20px;
  color: #a8e6c6;
}

.ui-aviso--error {
  background: #7f1d1d;
}

.ui-aviso--error > i:first-child {
  color: #fecaca;
}

.ui-aviso__mensaje {
  flex: 1;
}

.ui-aviso__enlace {
  color: #a8e6c6;
  font-weight: 800;
  text-decoration: underline;
}

.ui-aviso__cerrar {
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

@keyframes ui-aviso-entra {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: none;
  }
}

@media (max-width: 767px) {
  .ui-avisos {
    right: 12px;
    left: 12px;
    bottom: calc(var(--barra-inferior-h) + 12px);
  }
}
</style>
