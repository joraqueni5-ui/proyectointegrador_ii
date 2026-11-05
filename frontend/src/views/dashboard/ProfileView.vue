<template>
  <div class="profile-page">
    <div class="page-header">
      <h1>Mi Perfil</h1>
      <p>Información de tu cuenta registrada en el sistema</p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando perfil...</p>
    </div>

    <div v-else class="profile-content">
      
      <div class="profile-card shadow-sm">
        <div class="photo-section">
          <div class="photo-container">
            <img v-if="user.face_photo" :src="user.face_photo" alt="Foto de perfil" class="profile-photo" />
            <div v-else class="photo-placeholder">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5">
                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
              </svg>
            </div>
            <div class="photo-badge" :class="user.face_photo ? 'badge-active' : 'badge-inactive'">
              <svg v-if="user.face_photo" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              {{ user.face_photo ? 'Facial activo' : 'Sin foto facial' }}
            </div>
          </div>
        </div>

        <div class="profile-main">
          <div class="profile-name">
            {{ user.name }} {{ user.paterno }} {{ user.materno }}
          </div>
          <div class="profile-email">{{ user.email }}</div>
          <div class="role-badge">
            {{ user.rol?.nombre || 'Sin rol asignado' }}
          </div>
        </div>
      </div>

      <div class="data-grid">

        <div class="data-card shadow-sm">
          <div class="data-card-header">
            <div class="icon-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
              </svg>
            </div>
            <h3>Información personal</h3>
          </div>

          <div class="data-body">
            <div class="data-row">
              <span class="data-label">Nombres</span>
              <span class="data-value">{{ user.name }}</span>
            </div>
            <div class="data-row">
              <span class="data-label">Apellido paterno</span>
              <span class="data-value">{{ user.paterno }}</span>
            </div>
            <div class="data-row">
              <span class="data-label">Apellido materno</span>
              <span class="data-value">{{ user.materno }}</span>
            </div>
            <div class="data-row">
              <span class="data-label">Celular</span>
              <span class="data-value">{{ user.celular || '—' }}</span>
            </div>
          </div>
        </div>

        <div class="data-card shadow-sm">
          <div class="data-card-header">
            <div class="icon-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
              </svg>
            </div>
            <h3>Cuenta</h3>
          </div>

          <div class="data-body">
            <div class="data-row">
              <span class="data-label">Correo electrónico</span>
              <span class="data-value">{{ user.email }}</span>
            </div>
            <div class="data-row">
              <span class="data-label">Rol del sistema</span>
              <span class="data-value capitalize">{{ user.rol?.nombre || 'Sin rol asignado' }}</span>
            </div>
            <div class="data-row">
              <span class="data-label">Autenticación facial</span>
              <span class="data-value font-medium" :class="user.face_photo ? 'text-green' : 'text-gray'">
                {{ user.face_photo ? 'Configurado ✓' : 'No configurado' }}
              </span>
            </div>
            <div class="data-row">
              <span class="data-label">Miembro desde</span>
              <span class="data-value">{{ formatDate(user.created_at) }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import usuarioService from '@/services/modules/usuarioService';

const user = ref({});
const loading = ref(true);

onMounted(async () => {
  try {
    const response = await usuarioService.perfil();
    user.value = response.data;
  } catch (err) {
    console.error('Error al cargar perfil:', err);
  } finally {
    loading.value = false;
  }
});

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('es-BO', {
    year: 'numeric', month: 'long', day: 'numeric'
  });
};
</script>

<style scoped>
/* Contenedor principal */
.profile-page { 
  max-width: 950px; 
  font-family: system-ui, -apple-system, sans-serif;
  color: #374151;
}

.page-header { margin-bottom: 28px; }
.page-header h1 {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  margin: 0 0 6px 0;
}
.page-header p { 
  font-size: 14px; 
  color: #6B7280; 
  margin: 0;
}

/* Loading */
.loading-state {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  height: 250px; gap: 16px; color: #4B5563;
  font-size: 14px; font-weight: 500;
}
.spinner {
  width: 36px; height: 36px;
  border: 3px solid #E5E7EB;
  border-top-color: #2E7D32;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* Utilidades compartidas */
.shadow-sm {
  background: #ffffff;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

/* Tarjeta principal */
.profile-card {
  padding: 32px 40px;
  display: flex;
  align-items: center;
  gap: 36px;
  margin-bottom: 24px;
}

.photo-section {
  flex-shrink: 0;
}

.photo-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.profile-photo {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #F0FDF4; /* Verde muy claro */
  box-shadow: 0 0 0 1px #BBF7D0;
}

.photo-placeholder {
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: #F3F4F6;
  border: 2px dashed #D1D5DB;
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  padding: 4px 12px;
  border-radius: 20px;
  font-weight: 600;
}
.badge-active  { background: #DEF7EC; color: #03543F; }
.badge-inactive{ background: #F3F4F6; color: #6B7280; }

.profile-main {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.profile-name {
  font-size: 26px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 4px;
  letter-spacing: -0.01em;
}

.profile-email {
  font-size: 15px;
  color: #6B7280;
  margin-bottom: 16px;
}

.role-badge {
  display: inline-block;
  font-size: 13px;
  font-weight: 600;
  padding: 6px 14px;
  border-radius: 8px;
}
.role-badge.admin { background: #DEF7EC; color: #03543F; }
.role-badge.user  { background: #E1F5FE; color: #0277BD; }

/* Grid de datos */
.data-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.data-card {
  overflow: hidden;
}

.data-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 24px;
  background: #F9FAFB;
  border-bottom: 1px solid #E5E7EB;
}

.icon-wrapper {
  background: #E8F5E9;
  color: #2E7D32;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.data-card-header h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.data-body {
  padding: 8px 24px 24px 24px;
}

.data-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #F3F4F6;
}
.data-row:last-child { border-bottom: none; padding-bottom: 0; }

.data-label { 
  font-size: 13px; 
  color: #6B7280; 
  font-weight: 500;
}
.data-value { 
  font-size: 14px; 
  color: #111827;
  font-weight: 500;
  text-align: right;
}

.capitalize { text-transform: capitalize; }
.font-medium { font-weight: 600; }
.text-green  { color: #059669 !important; } /* Verde esmeralda Tailwind */
.text-gray   { color: #9CA3AF !important; }

/* Responsive básico */
@media (max-width: 768px) {
  .profile-card { flex-direction: column; text-align: center; gap: 20px; padding: 24px; }
  .profile-main { align-items: center; }
  .data-grid { grid-template-columns: 1fr; }
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>