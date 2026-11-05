<template>
  <div class="modal-overlay comunidad-modal-overlay" @click.self="close">
    <div class="modal-card comunidad-view-modal">
      <div class="comunidad-view-header">
        <div class="comunidad-view-icon">
          {{ inicial }}
        </div>

        <div class="comunidad-view-main">
          <h3>{{ comunidad?.nombre_comunidad || 'Comunidad' }}</h3>
          <div class="comunidad-view-badges">
            <span class="b" :class="comunidad?.activo ? 'bg' : 'bgr'">
              {{ comunidad?.activo ? 'Activa' : 'Inactiva' }}
            </span>

            <span class="b bb">
              {{ productores.length }} productores
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="comunidad-view-body">
        <div class="comunidad-view-section-title">Datos territoriales</div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-map"></i>
          </div>
          <div>
            <label>Municipio</label>
            <p>{{ comunidad?.municipio || '—' }}</p>
          </div>
        </div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-map-2"></i>
          </div>
          <div>
            <label>Provincia</label>
            <p>{{ comunidad?.provincia || '—' }}</p>
          </div>
        </div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-building"></i>
          </div>
          <div>
            <label>Departamento</label>
            <p>{{ comunidad?.departamento || '—' }}</p>
          </div>
        </div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-mountain"></i>
          </div>
          <div>
            <label>Altitud</label>
            <p>{{ formatAltitud(comunidad?.altitud_msnm) }}</p>
          </div>
        </div>

        <div class="comunidad-view-section-title">Coordenadas GPS</div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-current-location"></i>
          </div>
          <div>
            <label>Latitud</label>
            <p>{{ comunidad?.latitud ?? '—' }}</p>
          </div>
        </div>

        <div class="comunidad-detail-card">
          <div class="comunidad-detail-icon">
            <i class="ti ti-location"></i>
          </div>
          <div>
            <label>Longitud</label>
            <p>{{ comunidad?.longitud ?? '—' }}</p>
          </div>
        </div>

        <div class="comunidad-view-section-title">Productores registrados</div>

        <div v-if="!productores.length" class="comunidad-empty-producers">
          <i class="ti ti-users"></i>
          <span>Sin productores registrados</span>
        </div>

        <div v-else class="comunidad-producers-list">
          <div
            v-for="p in productores"
            :key="p.id"
            class="comunidad-producer-item"
          >
            <div class="comunidad-producer-avatar">
              {{ inicialProductor(p) }}
            </div>

            <div>
              <strong>{{ nombreProductor(p) }}</strong>
              <small>ID productor: {{ p.id }}</small>
            </div>
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
  comunidad: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const productores = computed(() => props.comunidad?.productores || []);

const inicial = computed(() => {
  return props.comunidad?.nombre_comunidad?.charAt(0)?.toUpperCase() || 'C';
});

function close() {
  emit('close');
}

function formatAltitud(value) {
  if (!value) {
    return '—';
  }

  return `${Number(value).toLocaleString('es-BO')} m.s.n.m.`;
}

function nombreProductor(productor) {
  if (!productor) return '—';

  if (productor.nombre_completo) {
    return productor.nombre_completo;
  }

  return [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function inicialProductor(productor) {
  const nombre = nombreProductor(productor);
  return nombre && nombre !== '—' ? nombre.charAt(0).toUpperCase() : '?';
}
</script>