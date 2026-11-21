<template>
  <section class="diagnostico-cnn-card diagnostico-resultado-card">
    <div class="diagnostico-cnn-card-head">
      <div>
        <h2>
          <i class="ti ti-chart-bar"></i>
          Resultado del diagnóstico
        </h2>
        <p>
          Último análisis registrado y recomendaciones asociadas.
        </p>
      </div>
    </div>

    <div v-if="!diagnostico" class="diagnostico-resultado-empty">
      <i class="ti ti-microscope"></i>
      <strong>Sin diagnóstico seleccionado</strong>
      <span>
        Registra un análisis o selecciona un diagnóstico del historial para visualizar el resultado.
      </span>
    </div>

    <div v-else class="diagnostico-resultado-body">
      <div
        class="diagnostico-resultado-main"
        :class="riesgoClass(diagnostico.enfermedad?.nivel_riesgo_base)"
      >
        <div class="diagnostico-resultado-label">
          Último diagnóstico
        </div>

        <div class="diagnostico-resultado-title">
          <i class="ti ti-virus"></i>
          <h3>
            {{ diagnostico.enfermedad?.nombre_comun || 'Sin clasificar' }}
          </h3>
        </div>

        <p class="diagnostico-resultado-scientific">
          {{ diagnostico.enfermedad?.nombre_cientifico || 'Resultado preliminar sin enfermedad asociada' }}
        </p>

        <div class="diagnostico-confidence-row">
          <span>Confianza del modelo</span>
          <strong>{{ numero(diagnostico.porcentaje_confianza) }}%</strong>
        </div>

        <div class="diagnostico-confidence-bar">
          <div
            :class="confianzaClass(diagnostico.porcentaje_confianza)"
            :style="{ width: `${Math.min(Number(diagnostico.porcentaje_confianza || 0), 100)}%` }"
          ></div>
        </div>

        <div class="diagnostico-resultado-badges">
          <span class="b" :class="badgeSeveridad(diagnostico.nivel_severidad)">
            {{ textoSeveridad(diagnostico.nivel_severidad) }}
          </span>

          <span class="b" :class="badgeValidacion(diagnostico.estado_validacion)">
            {{ diagnostico.estado_validacion || 'Pendiente' }}
          </span>
        </div>
      </div>

      <div class="diagnostico-resultado-section">
        <h4>
          <i class="ti ti-brain"></i>
          Lo que calculó la IA
        </h4>

        <DiagnosticoIaInforme :diagnostico="diagnostico" />
      </div>

      <div class="diagnostico-resultado-section diagnostico-recomendacion-box">
        <h4>
          <i class="ti ti-alert-triangle"></i>
          Acción recomendada
        </h4>

        <p>
          {{ diagnostico.recomendacion || diagnostico.enfermedad?.medidas_control || 'Sin recomendación registrada.' }}
        </p>
      </div>

      <div class="diagnostico-resultado-section">
        <h4>
          <i class="ti ti-package"></i>
          Productos sugeridos
        </h4>

        <div v-if="productos.length" class="diagnostico-productos-list">
          <div
            v-for="producto in productos"
            :key="producto.id"
            class="diagnostico-producto-item"
          >
            <div>
              <strong>{{ producto.nombre_producto }}</strong>
              <small>
                {{ producto.pivot?.tipo_recomendacion || 'Recomendado' }}
                · Prioridad {{ producto.pivot?.prioridad || '—' }}
              </small>
            </div>

            <span>
              Stock: {{ numero(stockTotal(producto)) }} {{ producto.unidad_medida || '' }}
            </span>
          </div>
        </div>

        <div v-else class="diagnostico-mini-empty">
          No hay productos recomendados vinculados a esta enfermedad.
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';

import DiagnosticoIaInforme from '@/components/diagnosticos/DiagnosticoIaInforme.vue';

const props = defineProps({
  diagnostico: {
    type: Object,
    default: null,
  },
});

const productos = computed(() => {
  return props.diagnostico?.enfermedad?.productos_recomendados
    || props.diagnostico?.enfermedad?.productosRecomendados
    || [];
});

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });
}

function confianzaClass(valor) {
  const numeroValor = Number(valor || 0);

  if (numeroValor >= 85) return 'alta';
  if (numeroValor >= 70) return 'media';

  return 'baja';
}

function riesgoClass(riesgo) {
  return {
    Bajo: 'riesgo-bajo',
    Medio: 'riesgo-medio',
    Alto: 'riesgo-alto',
    Critico: 'riesgo-critico',
  }[riesgo] || 'riesgo-medio';
}

function badgeSeveridad(severidad) {
  return {
    Sin_afeccion: 'bg',
    Leve: 'bb',
    Moderada: 'ba',
    Severa: 'br',
    Muy_severa: 'br',
  }[severidad] || 'bgr';
}

function badgeValidacion(estado) {
  return {
    Pendiente: 'bgr',
    Confirmado: 'bg',
    Corregido: 'ba',
    Rechazado: 'br',
  }[estado] || 'bgr';
}

function textoSeveridad(severidad) {
  if (!severidad) {
    return 'Sin severidad';
  }

  return String(severidad).replaceAll('_', ' ');
}

function stockTotal(producto) {
  if (!Array.isArray(producto?.inventarios)) {
    return 0;
  }

  return producto.inventarios.reduce(
    (sum, item) => sum + (Number(item.stock_actual) || 0),
    0
  );
}
</script>