<template>
  <div class="modal-overlay diagnostico-modal-overlay" @click.self="close">
    <div class="modal-card diagnostico-detalle-modal">
      <div
        class="diagnostico-detalle-header"
        :class="riesgoClass(diagnostico?.enfermedad?.nivel_riesgo_base)"
      >
        <div class="diagnostico-detalle-icon">
          <i class="ti ti-microscope"></i>
        </div>

        <div>
          <h3>Diagnóstico #{{ diagnostico?.id || '—' }}</h3>
          <p>
            Resultado preliminar de análisis foliar.
          </p>

          <div class="diagnostico-detalle-badges">
            <span
              class="b"
              :class="badgeValidacion(diagnostico?.estado_validacion)"
            >
              {{ diagnostico?.estado_validacion || 'Pendiente' }}
            </span>

            <span
              v-if="diagnostico?.nivel_severidad"
              class="b"
              :class="badgeSeveridad(diagnostico?.nivel_severidad)"
            >
              {{ textoSeveridad(diagnostico?.nivel_severidad) }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="diagnostico-detalle-body">
        <div class="diagnostico-detalle-photo">
          <DiagnosticoMapaCalor :diagnostico="diagnostico" />
        </div>

        <div class="diagnostico-detalle-info-grid">
          <div class="diagnostico-detail-card">
            <label>Inspección</label>
            <p>#{{ diagnostico?.inspeccion?.id || '—' }}</p>
            <small>{{ formatDate(diagnostico?.inspeccion?.fecha_visita) }}</small>
          </div>

          <div class="diagnostico-detail-card">
            <label>Parcela</label>
            <p>{{ diagnostico?.inspeccion?.parcela?.codigo_parcela || '—' }}</p>
            <small>{{ diagnostico?.inspeccion?.parcela?.nombre_referencial || 'Sin referencia' }}</small>
          </div>

          <div class="diagnostico-detail-card">
            <label>Enfermedad detectada</label>
            <p>{{ diagnostico?.enfermedad?.nombre_comun || 'Sin clasificar' }}</p>
            <small class="italic">
              {{ diagnostico?.enfermedad?.nombre_cientifico || '—' }}
            </small>
          </div>

          <div class="diagnostico-detail-card">
            <label>Confianza</label>
            <p>{{ numero(diagnostico?.porcentaje_confianza) }}%</p>

            <div class="diagnostico-confidence-mini-bar">
              <div
                :class="confianzaClass(diagnostico?.porcentaje_confianza)"
                :style="{ width: `${Math.min(Number(diagnostico?.porcentaje_confianza || 0), 100)}%` }"
              ></div>
            </div>
          </div>

          <div class="diagnostico-detail-card">
            <label>Validado por</label>
            <p>{{ nombreUsuario(diagnostico?.validado_por || diagnostico?.validadoPor) }}</p>
            <small>{{ diagnostico?.fecha_validacion ? formatDate(diagnostico.fecha_validacion) : 'Sin validar' }}</small>
          </div>

          <div class="diagnostico-detail-card">
            <label>Riesgo base</label>
            <p>{{ diagnostico?.enfermedad?.nivel_riesgo_base || '—' }}</p>
          </div>
        </div>

        <div class="diagnostico-detail-section span-2">
          <h4>
            <i class="ti ti-brain"></i>
            Lo que calculó la IA
          </h4>

          <DiagnosticoIaInforme :diagnostico="diagnostico" />
        </div>

        <div class="diagnostico-detail-section span-2">
          <h4>
            <i class="ti ti-notes"></i>
            Recomendación registrada
          </h4>

          <p>
            {{ diagnostico?.recomendacion || 'Sin recomendación registrada.' }}
          </p>
        </div>

        <div class="diagnostico-detail-section span-2 control">
          <h4>
            <i class="ti ti-clipboard-check"></i>
            Medidas de control del catálogo
          </h4>

          <p>
            {{ diagnostico?.enfermedad?.medidas_control || 'Sin medidas de control registradas.' }}
          </p>
        </div>

        <div class="diagnostico-detail-section span-2">
          <h4>
            <i class="ti ti-package"></i>
            Productos recomendados y stock disponible
          </h4>

          <div v-if="productos.length === 0" class="diagnostico-mini-empty">
            No hay productos recomendados vinculados a esta enfermedad.
          </div>

          <div v-else class="diagnostico-productos-list">
            <div
              v-for="producto in productos"
              :key="producto.id"
              class="diagnostico-producto-item"
            >
              <div>
                <strong>{{ producto.nombre_producto }}</strong>
                <small>
                  {{ producto.categoria || 'Sin categoría' }}
                  · {{ producto.pivot?.tipo_recomendacion || 'Recomendado' }}
                  · Prioridad {{ producto.pivot?.prioridad || '—' }}
                </small>
                <small v-if="producto.pivot?.dosis_recomendada">
                  Dosis: {{ producto.pivot.dosis_recomendada }}
                </small>
              </div>

              <div class="diagnostico-producto-stock">
                <span>Stock</span>
                <strong>
                  {{ numero(stockTotal(producto)) }}
                  {{ producto.unidad_medida || '' }}
                </strong>
              </div>
            </div>
          </div>

          <div class="diagnostico-movimiento-help">
            <i class="ti ti-arrows-transfer-down"></i>
            El stock mostrado se actualiza desde el módulo de Movimientos de inventario.
          </div>
        </div>

        <div
          v-if="diagnostico?.observaciones_validacion"
          class="diagnostico-detail-section span-2"
        >
          <h4>
            <i class="ti ti-message-2"></i>
            Observaciones de validación
          </h4>

          <p>{{ diagnostico.observaciones_validacion }}</p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>

        <button
          class="btn btn-p"
          type="button"
          @click="$emit('ir-movimientos')"
        >
          <i class="ti ti-arrows-transfer-down"></i>
          Ir a movimientos
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import DiagnosticoIaInforme from '@/components/diagnosticos/DiagnosticoIaInforme.vue';
import DiagnosticoMapaCalor from '@/components/diagnosticos/DiagnosticoMapaCalor.vue';

const props = defineProps({
  diagnostico: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close', 'ir-movimientos']);

const productos = computed(() => {
  return props.diagnostico?.enfermedad?.productos_recomendados
    || props.diagnostico?.enfermedad?.productosRecomendados
    || [];
});

function close() {
  emit('close');
}


function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });
}

function formatDate(fecha) {
  if (!fecha) {
    return '—';
  }

  return new Date(fecha).toLocaleDateString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function nombreUsuario(usuario) {
  if (!usuario) {
    return '—';
  }

  return [
    usuario.name,
    usuario.paterno,
    usuario.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
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