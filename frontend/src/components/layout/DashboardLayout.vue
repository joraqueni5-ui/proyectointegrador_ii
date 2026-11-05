<template>
  <div
    class="app-layout"
    :class="{
      'is-sidebar-collapsed': sidebarCollapsed,
      'is-sidebar-open': sidebarMobileOpen,
    }"
  >
    <!-- Botón móvil -->
    <button
      class="layout-mobile-toggle"
      type="button"
      title="Abrir menú"
      @click="openMobileSidebar"
    >
      <i class="ti ti-menu-2"></i>
    </button>

    <!-- Fondo oscuro en móvil -->
    <div
      v-if="sidebarMobileOpen"
      class="layout-sidebar-backdrop"
      @click="closeMobileSidebar"
    ></div>

    <Sidebar
      :user="userData"
      :alertas-count="alertasCount"
      :collapsed="sidebarCollapsed"
      :mobile-open="sidebarMobileOpen"
      @toggle-collapse="toggleSidebar"
      @close-mobile="closeMobileSidebar"
      @logout="logout"
    />

    <main class="main-content">
      <div v-if="loading" class="layout-loading">
        <div class="layout-loader"></div>
        <p>Cargando sistema...</p>
      </div>

      <router-view v-else />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';

import Sidebar from './Sidebar.vue';
import alertaService from '@/services/modules/alertaService';
import authService from '@/services/modules/authService';
import usuarioService from '@/services/modules/usuarioService';
import { obtenerPermisos, tienePermiso } from '@/utils/permisos';

const router = useRouter();
const route = useRoute();

const loading = ref(true);
const alertasCount = ref(0);

const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const sidebarMobileOpen = ref(false);

const userData = ref({
  id: null,
  name: '',
  paterno: '',
  materno: '',
  email: '',
  rol: null,
  permisos: [],
  face_photo: null,
});

onMounted(() => {
  cargarDatosLayout();
});

watch(
  () => route.fullPath,
  () => {
    closeMobileSidebar();
  }
);

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value;
  localStorage.setItem('sidebar_collapsed', String(sidebarCollapsed.value));
}

function openMobileSidebar() {
  sidebarMobileOpen.value = true;
}

function closeMobileSidebar() {
  sidebarMobileOpen.value = false;
}

async function cargarDatosLayout() {
  loading.value = true;

  try {
    const profileRes = await usuarioService.perfil();

    userData.value = normalizarUsuario(profileRes.data);
    localStorage.setItem('user_data', JSON.stringify(userData.value));

    if (tienePermiso(userData.value, 'ver_alertas')) {
      const res = await alertaService.noLeidas();
      alertasCount.value = res.data?.count || 0;
    } else {
      alertasCount.value = 0;
    }
  } catch (error) {
    console.error('Error cargando layout:', error);

    limpiarSesion();
    router.push({ name: 'login' });
  } finally {
    loading.value = false;
  }
}

function normalizarUsuario(user) {
  return {
    id: user?.id || null,
    name: user?.name || '',
    paterno: user?.paterno || '',
    materno: user?.materno || '',
    email: user?.email || '',
    rol: user?.rol || null,
    permisos: obtenerPermisos(user),
    face_photo: user?.face_photo || null,
    activo: user?.activo ?? true,
  };
}

function limpiarSesion() {
  localStorage.removeItem('user_token');
  localStorage.removeItem('user_data');
  localStorage.removeItem('pending_email');
  localStorage.removeItem('pending_remember_device');
}

async function logout() {
  try {
    await authService.logout();
  } catch (error) {
    console.warn('No se pudo cerrar sesión en servidor:', error);
  } finally {
    limpiarSesion();
    router.push({ name: 'login' });
  }
}
</script>

<style scoped>
.app-layout {
  height: 100vh;
  display: flex;
  background: #f8fafc;
  overflow: hidden;
}

.main-content {
  flex: 1;
  min-width: 0;
  height: 100vh;
  background: #f8fafc;
  overflow-y: auto;
  overflow-x: hidden;
  transition: all 0.24s ease;
}

.layout-loading {
  min-height: 100vh;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 12px;
  color: #64748b;
  font-weight: 800;
}

.layout-loader {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 4px solid #e2e8f0;
  border-top-color: #198754;
  animation: layoutSpin 0.8s linear infinite;
}

.layout-mobile-toggle {
  display: none;
  position: fixed;
  top: 12px;
  left: 12px;
  z-index: 1300;
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, #0f5132, #198754);
  color: white;
  font-size: 24px;
  box-shadow: 0 12px 26px rgba(15, 81, 50, 0.28);
}

.layout-sidebar-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1150;
  background: rgba(15, 23, 42, 0.52);
  backdrop-filter: blur(3px);
  animation: fadeIn 0.18s ease both;
}

@keyframes layoutSpin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 900px) {
  .app-layout {
    display: block;
  }

  .layout-mobile-toggle {
    display: grid;
    place-items: center;
  }

  .main-content {
    height: 100vh;
    padding-top: 54px;
  }
}
</style>