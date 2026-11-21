<template>
  <div class="app-layout">
    <Sidebar :user="userData" :alertas-count="alertasCount" @logout="logout" />

    <div class="app-columna">
      <TopBar :user="userData" :alertas-count="alertasCount" @logout="logout" />

      <main class="main-content">
        <div v-if="loading" class="layout-loading">
          <div class="layout-loader"></div>
          <p>Cargando sistema...</p>
        </div>

        <router-view v-else />
      </main>
    </div>

    <ToastHost />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';

import Sidebar from './Sidebar.vue';
import TopBar from './TopBar.vue';
import ToastHost from '@/components/ui/ToastHost.vue';
import alertaService from '@/services/modules/alertaService';
import authService from '@/services/modules/authService';
import { cargarConfigAgricola } from '@/utils/campana';
import usuarioService from '@/services/modules/usuarioService';
import { obtenerPermisos, tienePermiso } from '@/utils/permisos';

const router = useRouter();

const loading = ref(true);
const alertasCount = ref(0);

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

async function cargarDatosLayout() {
  loading.value = true;

  try {
    const profileRes = await usuarioService.perfil();

    userData.value = normalizarUsuario(profileRes.data);
    localStorage.setItem('user_data', JSON.stringify(userData.value));

    cargarConfigAgricola(); // mes de inicio de la campaña (config del backend); no bloquea la carga

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
  overflow: hidden;
}

.app-columna {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.main-content {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
}

.layout-loading {
  min-height: 60vh;
  display: grid;
  place-items: center;
  align-content: center;
  gap: 12px;
  color: var(--texto-secundario);
  font-weight: 800;
}

.layout-loader {
  width: 42px;
  height: 42px;
  border-radius: 999px;
  border: 4px solid var(--borde);
  border-top-color: var(--verde-700);
  animation: layoutSpin 0.8s linear infinite;
}

@keyframes layoutSpin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 767px) {
  .main-content {
    padding-bottom: var(--barra-inferior-h);
  }
}
</style>
