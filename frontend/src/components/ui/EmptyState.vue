<template>
  <div class="ui-vacio" role="status">
    <i :class="['ti', icono, 'ui-vacio__icono']" aria-hidden="true"></i>
    <h3 class="ui-vacio__titulo">{{ titulo }}</h3>
    <p v-if="texto" class="ui-vacio__texto">{{ texto }}</p>
    <div v-if="$slots.default" class="ui-vacio__accion"><slot /></div>
  </div>
</template>

<script setup>
// Estado vacío: ícono de línea, un título, una explicación de una línea y la acción siguiente (slot).
defineProps({
  icono: { type: String, default: 'ti-inbox' },
  titulo: { type: String, required: true },
  texto: { type: String, default: '' },
});
</script>

<style scoped>
.ui-vacio {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 40px 20px;
  text-align: center;
}

.ui-vacio__icono {
  font-size: 48px;
  color: #8ba3b5;
}

.ui-vacio__titulo {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #111827;
}

.ui-vacio__texto {
  margin: 0;
  max-width: 420px;
  font-size: 14px;
  color: #4a6070;
}

.ui-vacio__accion {
  margin-top: 8px;
}
</style>
