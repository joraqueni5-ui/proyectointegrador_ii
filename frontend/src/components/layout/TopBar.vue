<template>
  <header class="barra-superior">
    <BuscadorGeneral />

    <div class="barra-derecha">
      <!-- Clima de la comunidad del usuario: se muestra cuando exista el dato (etapa B6) -->
      <div v-if="clima" class="barra-clima" :title="clima.detalle">
        <i :class="['ti', clima.icono]" aria-hidden="true"></i>
        <span>{{ clima.texto }}</span>
      </div>

      <RouterLink
        v-if="puedeVerAlertas"
        to="/dashboard/alertas"
        class="barra-boton"
        :aria-label="alertasCount > 0 ? `Notificaciones: ${alertasCount} alertas sin leer` : 'Notificaciones'"
      >
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span v-if="alertasCount > 0" class="barra-punto"></span>
      </RouterLink>

      <div ref="menuRaiz" class="usuario">
        <button
          type="button"
          class="usuario-boton"
          :aria-expanded="menuAbierto"
          aria-haspopup="menu"
          @click="menuAbierto = !menuAbierto"
          @keydown.esc="menuAbierto = false"
        >
          <span class="usuario-avatar">
            <img v-if="usuario.face_photo" :src="usuario.face_photo" alt="" />
            <span v-else>{{ iniciales }}</span>
          </span>
          <span class="usuario-datos">
            <strong>{{ nombreCorto }}</strong>
            <small>{{ rolNombre || 'Sin rol asignado' }}</small>
          </span>
          <i class="ti ti-chevron-down usuario-flecha" aria-hidden="true"></i>
        </button>

        <div v-if="menuAbierto" class="usuario-menu" role="menu">
          <RouterLink to="/dashboard/perfil" class="usuario-opcion" role="menuitem" @click="menuAbierto = false">
            <i class="ti ti-user-circle" aria-hidden="true"></i> Mi perfil
          </RouterLink>
          <button type="button" class="usuario-opcion usuario-salir" role="menuitem" @click="salir">
            <i class="ti ti-logout" aria-hidden="true"></i> Cerrar sesión
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import BuscadorGeneral from './BuscadorGeneral.vue';
import { obtenerRolNombre, tienePermiso } from '@/utils/permisos';

const props = defineProps({
  user: { type: Object, default: () => ({}) },
  alertasCount: { type: Number, default: 0 },
  clima: { type: Object, default: null },
});

const emit = defineEmits(['logout']);

const route = useRoute();
const usuario = computed(() => props.user || {});
const rolNombre = computed(() => obtenerRolNombre(usuario.value));
const puedeVerAlertas = computed(() => tienePermiso(usuario.value, 'ver_alertas'));

const nombreCorto = computed(() => [usuario.value.name, usuario.value.paterno].filter(Boolean).join(' ') || 'Usuario');
const iniciales = computed(() =>
  ([usuario.value.name, usuario.value.paterno].filter(Boolean).map((p) => p.charAt(0)).join('') || 'U').toUpperCase()
);

const menuAbierto = ref(false);
const menuRaiz = ref(null);

function clicFuera(evento) {
  if (menuRaiz.value && !menuRaiz.value.contains(evento.target)) menuAbierto.value = false;
}

function salir() {
  menuAbierto.value = false;
  emit('logout');
}

watch(() => route.fullPath, () => (menuAbierto.value = false));
onMounted(() => document.addEventListener('mousedown', clicFuera));
onBeforeUnmount(() => document.removeEventListener('mousedown', clicFuera));
</script>

<style scoped>
.barra-superior {
  flex: none;
  height: var(--barra-superior-h);
  background: #fff;
  border-bottom: 1px solid var(--borde);
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 0 24px;
  position: relative;
  z-index: 50;
}

.barra-derecha {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
}

.barra-clima {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 12px;
  height: 44px;
  border-radius: 12px;
  background: var(--gris-100);
  font-size: 13px;
  font-weight: 700;
  color: var(--texto);
}

.barra-boton {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--gris-100);
  display: grid;
  place-items: center;
  font-size: 20px;
  color: var(--texto);
  text-decoration: none;
}

.barra-boton:hover {
  background: var(--gris-200);
}

.barra-punto {
  position: absolute;
  top: 10px;
  right: 11px;
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--naranja-600);
  border: 2px solid var(--gris-100);
}

.usuario {
  position: relative;
}

.usuario-boton {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 4px 10px 4px 4px;
  border: 0;
  border-radius: 999px;
  background: transparent;
  font-family: inherit;
  cursor: pointer;
}

.usuario-boton:hover {
  background: var(--gris-100);
}

.usuario-avatar {
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: var(--verde-200);
  color: var(--verde-900);
  display: grid;
  place-items: center;
  font-weight: 800;
  font-size: 13px;
  overflow: hidden;
}

.usuario-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.usuario-datos {
  display: grid;
  text-align: left;
  line-height: 1.2;
}

.usuario-datos strong {
  font-size: 13px;
  color: var(--texto);
}

.usuario-datos small {
  font-size: 12px;
  color: var(--texto-secundario);
}

.usuario-flecha {
  color: var(--texto-secundario);
}

.usuario-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  width: 210px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
  padding: 6px;
  z-index: 100;
}

.usuario-opcion {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 44px;
  padding: 0 12px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--texto);
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}

.usuario-opcion:hover {
  background: var(--gris-100);
}

.usuario-salir {
  color: var(--rojo-700);
}

@media (max-width: 767px) {
  .barra-superior {
    height: 64px;
    padding: 0 12px;
    gap: 8px;
  }

  .usuario-datos,
  .usuario-flecha,
  .barra-clima span {
    display: none;
  }
}
</style>
