<template>
  <div class="modal-overlay user-modal-overlay" @click.self="close">
    <div class="modal-card user-view-modal">
      <div class="user-view-header">
        <div class="user-view-avatar">
          <img v-if="user?.face_photo" :src="user.face_photo" alt="foto usuario" />
          <span v-else>{{ inicialUsuario }}</span>
        </div>

        <div class="user-view-main">
          <h3>{{ nombreCompleto }}</h3>
          <div class="user-view-badges">
            <span class="b" :class="badgeRol(user?.rol?.nombre)">
              {{ user?.rol?.nombre || 'Sin rol asignado' }}
            </span>

            <span class="b" :class="user?.activo ? 'bg' : 'bgr'">
              {{ user?.activo ? 'Activo' : 'Inactivo' }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="user-view-body">
        <div class="user-view-section-title">Datos personales</div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-id"></i>
          </div>
          <div>
            <label>Carnet de identidad</label>
            <p>{{ ciCompleto }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-gender-bigender"></i>
          </div>
          <div>
            <label>Sexo</label>
            <p>{{ user?.sexo || '—' }}</p>
          </div>
        </div>

        <div class="user-view-section-title">Contacto</div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-mail"></i>
          </div>
          <div>
            <label>Correo institucional</label>
            <p>{{ user?.email || '—' }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-phone"></i>
          </div>
          <div>
            <label>Celular</label>
            <p>{{ user?.celular || '—' }}</p>
          </div>
        </div>

        <div class="user-view-section-title">Datos institucionales</div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-briefcase"></i>
          </div>
          <div>
            <label>Cargo</label>
            <p>{{ user?.cargo || '—' }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-building"></i>
          </div>
          <div>
            <label>Área / Unidad</label>
            <p>{{ user?.area || '—' }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-map-pin"></i>
          </div>
          <div>
            <label>Oficina regional</label>
            <p>{{ user?.oficina_regional || '—' }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-face-id"></i>
          </div>
          <div>
            <label>Biometría facial</label>
            <p>{{ user?.face_photo ? 'Registrada' : 'No registrada' }}</p>
          </div>
        </div>

        <div class="user-view-section-title">Auditoría</div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-calendar"></i>
          </div>
          <div>
            <label>Fecha de registro</label>
            <p>{{ formatDate(user?.created_at) }}</p>
          </div>
        </div>

        <div class="user-detail-card">
          <div class="user-detail-icon">
            <i class="ti ti-clock-edit"></i>
          </div>
          <div>
            <label>Última actualización</label>
            <p>{{ formatDate(user?.updated_at) }}</p>
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
  user: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const nombreCompleto = computed(() => {
  return [
    props.user?.name,
    props.user?.paterno,
    props.user?.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
});

const inicialUsuario = computed(() => {
  return nombreCompleto.value.charAt(0).toUpperCase() || 'U';
});

const ciCompleto = computed(() => {
  if (!props.user?.ci) {
    return '—';
  }

  return `${props.user.ci}${props.user.ci_extension ? ' ' + props.user.ci_extension : ''}`;
});

function close() {
  emit('close');
}

function badgeRol(nombre) {
  return {
    Superadmin: 'bg',
    Administrador: 'bg',
    Agrónomo: 'bb',
    Técnico: 'ba',
    'Encargado de almacén': 'bb',
    Comercial: 'ba',
    Agricultor: 'bgr',
  }[nombre] || 'badge-gray';
}

function formatDate(dateStr) {
  if (!dateStr) {
    return '—';
  }

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}
</script>