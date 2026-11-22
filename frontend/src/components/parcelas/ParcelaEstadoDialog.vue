<template>
  <Teleport to="body">
    <div v-if="modelValue" class="ped__fondo" @mousedown.self="cerrar">
      <div ref="caja" class="ped" role="dialog" aria-modal="true" :aria-labelledby="`${uid}-t`">
        <h2 :id="`${uid}-t`">Cambiar el estado de {{ parcela?.nombre_visible }}</h2>
        <p class="ped__texto">No se borra ningún dato; solo cambia cómo se muestra y si se puede registrar trabajo en ella.</p>

        <BaseRadioCards v-model="estado" :opciones="OPCIONES" :columnas="1" />

        <p v-if="error" class="ped__error" role="alert">{{ error }}</p>

        <div class="ped__acciones">
          <BaseButton variante="secundario" :disabled="guardando" @click="cerrar">No, volver</BaseButton>
          <BaseButton :cargando="guardando" texto-cargando="Guardando…" :disabled="estado === parcela?.estado" @click="$emit('confirmar', estado)">Cambiar estado</BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseRadioCards from '@/components/ui/BaseRadioCards.vue';

// Cambio de estado de una parcela (Activa, En descanso, Inactiva). El servidor puede rechazar
// "Inactiva" si hay cultivos activos; ese mensaje se muestra aquí.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  parcela: { type: Object, default: null },
  guardando: { type: Boolean, default: false },
  error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'confirmar']);

const OPCIONES = [
  { valor: 'Activa', titulo: 'Activa', descripcion: 'En uso: se pueden registrar cultivos, visitas y cosechas.', icono: 'ti-circle-check' },
  { valor: 'En_descanso', titulo: 'En descanso', descripcion: 'Sin cultivo por esta campaña; sigue visible en el mapa en gris.', icono: 'ti-moon' },
  { valor: 'Inactiva', titulo: 'Inactiva', descripcion: 'Fuera de uso. No se puede si tiene cultivos activos.', icono: 'ti-circle-off' },
];

const uid = `ped-${useId()}`;
const estado = ref('Activa');

function alTeclado(e) {
  if (e.key === 'Escape') cerrar();
}

watch(
  () => props.modelValue,
  (abierto) => {
    window[abierto ? 'addEventListener' : 'removeEventListener']('keydown', alTeclado);

    if (abierto) estado.value = props.parcela?.estado || 'Activa';
  }
);

onBeforeUnmount(() => window.removeEventListener('keydown', alTeclado));

function cerrar() {
  if (props.guardando) return;

  emit('update:modelValue', false);
}
</script>

<style scoped>
.ped__fondo {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.55);
}

.ped {
  width: min(480px, 100%);
  display: grid;
  gap: 14px;
  padding: 24px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.3);
}

.ped h2 {
  margin: 0;
  font-size: 19px;
  font-weight: 800;
}

.ped__texto {
  margin: 0;
  font-size: 14px;
  color: #4a6070;
}

.ped__error {
  margin: 0;
  padding: 10px 12px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.ped__acciones {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
