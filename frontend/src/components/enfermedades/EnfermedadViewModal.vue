<template>
  <div class="modal-overlay enfermedad-modal-overlay" @click.self="close">
    <div class="modal-card enfermedad-view-modal">
      <div class="enfermedad-view-header" :class="riesgoClass(enfermedad?.nivel_riesgo_base)">
        <div class="enfermedad-view-icon">
          <i class="ti ti-virus"></i>
        </div>

        <div class="enfermedad-view-main">
          <h3>{{ enfermedad?.nombre_comun || 'Enfermedad' }}</h3>
          <p>
            Código:
            <strong>{{ enfermedad?.codigo_enfermedad || '—' }}</strong>
          </p>

          <div class="enfermedad-view-badges">
            <span class="b" :class="badgeRiesgo(enfermedad?.nivel_riesgo_base)">
              Riesgo {{ limpiarTexto(enfermedad?.nivel_riesgo_base) }}
            </span>

            <span class="b bb">
              {{ enfermedad?.tipo || 'Sin tipo' }}
            </span>

            <span class="b" :class="enfermedad?.activo ? 'bg' : 'bgr'">
              {{ enfermedad?.activo ? 'Activa' : 'Inactiva' }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="enfermedad-view-body">
        <div class="enfermedad-view-section-title">Información general</div>

        <div class="enfermedad-detail-card">
          <div class="enfermedad-detail-icon">
            <i class="ti ti-signature"></i>
          </div>
          <div>
            <label>Nombre común</label>
            <p>{{ enfermedad?.nombre_comun || '—' }}</p>
          </div>
        </div>

        <div class="enfermedad-detail-card">
          <div class="enfermedad-detail-icon">
            <i class="ti ti-dna"></i>
          </div>
          <div>
            <label>Nombre científico</label>
            <p class="italic">{{ enfermedad?.nombre_cientifico || '—' }}</p>
          </div>
        </div>

        <div class="enfermedad-detail-card">
          <div class="enfermedad-detail-icon">
            <i class="ti ti-category"></i>
          </div>
          <div>
            <label>Tipo</label>
            <p>{{ enfermedad?.tipo || '—' }}</p>
          </div>
        </div>

        <div class="enfermedad-detail-card">
          <div class="enfermedad-detail-icon">
            <i class="ti ti-alert-triangle"></i>
          </div>
          <div>
            <label>Nivel de riesgo</label>
            <p>{{ limpiarTexto(enfermedad?.nivel_riesgo_base) }}</p>
          </div>
        </div>

        <div class="enfermedad-view-section-title">Descripción técnica</div>

        <div class="enfermedad-text-card span-2">
          <label>Síntomas</label>
          <p>{{ enfermedad?.sintomas || 'Sin síntomas registrados.' }}</p>
        </div>

        <div class="enfermedad-text-card span-2">
          <label>Descripción</label>
          <p>{{ enfermedad?.descripcion || 'Sin descripción registrada.' }}</p>
        </div>

        <div class="enfermedad-text-card span-2 control">
          <label>Medidas de control</label>
          <p>{{ enfermedad?.medidas_control || 'Sin medidas de control registradas.' }}</p>
        </div>

        <div class="enfermedad-view-section-title">Productos recomendados</div>

        <div v-if="!productos.length" class="enfermedad-empty-block span-2">
          <i class="ti ti-package-off"></i>
          <span>No hay productos recomendados vinculados.</span>
        </div>

        <div v-else class="enfermedad-product-list span-2">
          <div v-for="p in productos" :key="p.id" class="enfermedad-product-item">
            <div>
              <strong>{{ p.nombre_producto }}</strong>
              <small>
                {{ p.categoria || 'Sin categoría' }} — {{ p.unidad_medida || 'Sin unidad' }}
              </small>
              <small>
                {{ p.pivot?.tipo_recomendacion || 'Sin tipo' }}
                · Prioridad {{ p.pivot?.prioridad || '—' }}
              </small>
            </div>

            <span>Stock: {{ stockTotal(p) }}</span>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  enfermedad: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const productos = computed(() => props.enfermedad?.productos_recomendados || []);

function close() {
  emit('close');
}

function badgeRiesgo(riesgo) {
  return {
    Bajo: 'bg',
    Medio: 'bb',
    Alto: 'ba',
    Critico: 'br',
  }[riesgo] || 'bgr';
}

function riesgoClass(riesgo) {
  return {
    Bajo: 'riesgo-bajo',
    Medio: 'riesgo-medio',
    Alto: 'riesgo-alto',
    Critico: 'riesgo-critico',
  }[riesgo] || 'riesgo-medio';
}

function limpiarTexto(value) {
  if (!value) return '—';
  return String(value).replaceAll('_', ' ');
}

function stockTotal(producto) {
  if (!producto?.inventarios) return 0;

  return producto.inventarios.reduce(
    (sum, item) => sum + (Number(item.stock_actual) || 0),
    0
  );
}
</script>