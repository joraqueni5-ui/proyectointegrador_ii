<template>
  <article class="ui-kpi" :class="{ 'ui-kpi--alerta': alerta }">
    <div class="ui-kpi__cabeza">
      <span class="ui-kpi__etiqueta">{{ etiqueta }}</span>
      <i v-if="icono" :class="['ti', icono, 'ui-kpi__icono']" aria-hidden="true"></i>
    </div>

    <div class="ui-kpi__medio">
      <strong class="ui-kpi__valor">{{ valor }}</strong>
      <Sparkline v-if="serie && serie.length > 1" :valores="serie" :color="alerta ? '#f07a2a' : '#1a5c3e'" />
    </div>

    <div class="ui-kpi__pie">
      <span v-if="variacion !== null && variacion !== undefined" class="ui-kpi__variacion" :class="variacion >= 0 === !invertirVariacion ? 'es-buena' : 'es-mala'">
        {{ variacion >= 0 ? '▲' : '▼' }} {{ Math.abs(variacion).toLocaleString('es-BO', { maximumFractionDigits: 1 }) }} %
      </span>
      <span class="ui-kpi__contexto"><slot>{{ contexto }}</slot></span>
    </div>
  </article>
</template>

<script setup>
import Sparkline from './Sparkline.vue';

// Tarjeta de indicador: etiqueta, número grande, dato de contexto, mini gráfico y variación opcionales.
// `alerta` la pinta de naranja (algo necesita atención). `invertirVariacion`: subir es malo (p. ej. stock bajo).
defineProps({
  etiqueta: { type: String, required: true },
  valor: { type: [String, Number], default: '—' },
  contexto: { type: String, default: '' },
  variacion: { type: Number, default: null },
  invertirVariacion: { type: Boolean, default: false },
  serie: { type: Array, default: () => [] },
  alerta: { type: Boolean, default: false },
  icono: { type: String, default: '' },
});
</script>

<style scoped>
.ui-kpi {
  display: grid;
  gap: 8px;
  padding: 18px 20px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.ui-kpi--alerta {
  background: #fff3e8;
}

.ui-kpi__cabeza {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.ui-kpi__etiqueta {
  font-size: 13px;
  font-weight: 600;
  color: #4a6070;
}

.ui-kpi--alerta .ui-kpi__etiqueta {
  color: #9a3412;
}

.ui-kpi__icono {
  font-size: 20px;
  color: #4a6070;
}

.ui-kpi__medio {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
}

.ui-kpi__valor {
  font-size: 28px;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.1;
  color: #111827;
}

.ui-kpi--alerta .ui-kpi__valor {
  color: #9a3412;
}

.ui-kpi__pie {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 8px;
  font-size: 12px;
  color: #4a6070;
}

.ui-kpi__variacion {
  font-weight: 800;
}

.ui-kpi__variacion.es-buena {
  color: #166534;
}

.ui-kpi__variacion.es-mala {
  color: #b91c1c;
}
</style>
