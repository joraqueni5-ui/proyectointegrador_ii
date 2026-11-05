<template>
  <div class="modal-overlay productor-modal-overlay" @click.self="close">
    <div class="modal-card productor-parcelas-modal">
      <div class="modal-header">
        <div>
          <div class="modal-title">
            <i class="ti ti-map-2"></i>
            Parcelas del productor
          </div>
          <p class="productor-modal-subtitle">
            {{ nombreProductor }}
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <span>Cargando parcelas...</span>
        </div>

        <div v-else-if="!parcelas.length" class="productor-empty-parcelas">
          <i class="ti ti-map-off"></i>
          <span>Sin parcelas registradas</span>
        </div>

        <div v-else class="productor-parcelas-table-wrap">
          <table class="tbl productor-parcelas-table">
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre referencial</th>
                <th>Superficie</th>
                <th>Tipo suelo</th>
                <th>Riego</th>
                <th>Estado</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="parc in parcelas" :key="parc.id">
                <td>
                  <strong>{{ parc.codigo_parcela }}</strong>
                </td>
                <td>{{ parc.nombre_referencial || '—' }}</td>
                <td>{{ parc.superficie_ha ? parc.superficie_ha + ' ha' : '—' }}</td>
                <td>{{ parc.tipo_suelo || '—' }}</td>
                <td>
                  <span class="b bb">
                    {{ parc.sistema_riego || '—' }}
                  </span>
                </td>
                <td>
                  <span class="b" :class="badgeEstado(parc.estado)">
                    {{ limpiarEstado(parc.estado) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
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
  productor: {
    type: Object,
    default: null,
  },
  parcelas: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const nombreProductor = computed(() => {
  if (!props.productor) return '';

  return props.productor.nombre_completo || [
    props.productor.nombres,
    props.productor.apellido_paterno,
    props.productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ');
});

function close() {
  emit('close');
}

function badgeEstado(estado) {
  return {
    Activa: 'bg',
    En_descanso: 'ba',
    Inactiva: 'bgr',
  }[estado] || 'badge-gray';
}

function limpiarEstado(estado) {
  if (!estado) return '—';
  return String(estado).replaceAll('_', ' ');
}
</script>