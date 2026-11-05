<template>
  <div class="modal-overlay productor-modal-overlay" @click.self="close">
    <div class="modal-card productor-view-modal">
      <div class="productor-view-header">
        <div class="productor-view-avatar">
          {{ inicial }}
        </div>

        <div class="productor-view-main">
          <h3>{{ nombreCompleto }}</h3>

          <div class="productor-view-badges">
            <span class="b" :class="productor?.activo ? 'bg' : 'bgr'">
              {{ productor?.activo ? 'Activo' : 'Inactivo' }}
            </span>

            <span v-if="productor?.sexo" class="b" :class="badgeSexo(productor.sexo)">
              {{ textoSexo(productor.sexo) }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="productor-view-body">
        <div class="productor-view-section-title">Datos personales</div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-id"></i>
          </div>
          <div>
            <label>Carnet de identidad</label>
            <p>{{ ciCompleto }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-phone"></i>
          </div>
          <div>
            <label>Teléfono</label>
            <p>{{ productor?.telefono || '—' }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-calendar"></i>
          </div>
          <div>
            <label>Fecha de nacimiento</label>
            <p>{{ formatDate(productor?.fecha_nacimiento) }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-home"></i>
          </div>
          <div>
            <label>Dirección</label>
            <p>{{ productor?.direccion || '—' }}</p>
          </div>
        </div>

        <div class="productor-view-section-title">Datos agrícolas</div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-building-community"></i>
          </div>
          <div>
            <label>Comunidad</label>
            <p>{{ productor?.comunidad?.nombre_comunidad || '—' }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-map"></i>
          </div>
          <div>
            <label>Municipio</label>
            <p>{{ productor?.comunidad?.municipio || '—' }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-ruler-measure"></i>
          </div>
          <div>
            <label>Superficie total</label>
            <p>{{ productor?.superficie_total_ha ? productor.superficie_total_ha + ' ha' : '—' }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-notes"></i>
          </div>
          <div>
            <label>Observaciones</label>
            <p>{{ productor?.observaciones || '—' }}</p>
          </div>
        </div>

        <div class="productor-view-section-title">Auditoría</div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-calendar-plus"></i>
          </div>
          <div>
            <label>Fecha de registro</label>
            <p>{{ formatDate(productor?.created_at) }}</p>
          </div>
        </div>

        <div class="productor-detail-card">
          <div class="productor-detail-icon">
            <i class="ti ti-clock-edit"></i>
          </div>
          <div>
            <label>Última actualización</label>
            <p>{{ formatDate(productor?.updated_at) }}</p>
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
  productor: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const nombreCompleto = computed(() => {
  if (!props.productor) return 'Productor';

  return props.productor.nombre_completo || [
    props.productor.nombres,
    props.productor.apellido_paterno,
    props.productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ');
});

const inicial = computed(() => {
  return nombreCompleto.value.charAt(0).toUpperCase() || 'P';
});

const ciCompleto = computed(() => {
  if (!props.productor?.carnet_identidad) {
    return '—';
  }

  const numero = props.productor.carnet_identidad;
  const complemento = props.productor.complemento_ci ? `-${props.productor.complemento_ci}` : '';
  const extension = props.productor.ci_extension ? ` ${props.productor.ci_extension}` : '';

  return `${numero}${complemento}${extension}`;
});

function close() {
  emit('close');
}

function textoSexo(sexo) {
  return {
    M: 'Masculino',
    F: 'Femenino',
    Otro: 'Otro',
  }[sexo] || '—';
}

function badgeSexo(sexo) {
  return {
    M: 'bb',
    F: 'ba',
    Otro: 'badge-gray',
  }[sexo] || 'badge-gray';
}

function formatDate(dateStr) {
  if (!dateStr) return '—';

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) return '—';

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}
</script>