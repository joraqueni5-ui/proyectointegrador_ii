<template>
  <!-- Escritorio: menú compacto de 88 px -->
  <aside v-if="!esMovil" class="menu" aria-label="Menú principal">
    <RouterLink to="/dashboard" class="menu-logo" aria-label="Ir al inicio">
      <img src="/logo-proinpa.png" alt="PROINPA" />
    </RouterLink>

    <nav class="menu-nav" @keydown.esc="cerrar">
      <div
        v-for="item in items"
        :key="item.id"
        class="menu-celda"
        @mouseenter="item.hijos && abrirHover($event, item)"
        @mouseleave="item.hijos && programarCierre()"
      >
        <RouterLink
          v-if="!item.hijos"
          :to="item.to"
          class="menu-item"
          :class="{ 'is-activo': activo(item) }"
          :aria-current="activo(item) ? 'page' : undefined"
        >
          <i :class="['ti', item.icono]" aria-hidden="true"></i>
          <span>{{ item.etiqueta }}</span>
          <b v-if="item.contador && alertasCount > 0" class="menu-contador">{{ alertasCount > 99 ? '99+' : alertasCount }}</b>
        </RouterLink>

        <button
          v-else
          type="button"
          class="menu-item"
          :class="{ 'is-activo': activo(item), 'is-abierto': abierto === item.id }"
          :aria-expanded="abierto === item.id"
          aria-haspopup="menu"
          @click="alternar($event, item)"
        >
          <i :class="['ti', item.icono]" aria-hidden="true"></i>
          <span>{{ item.etiqueta }}</span>
        </button>
      </div>
    </nav>

    <RouterLink to="/dashboard/perfil" class="menu-avatar" :title="nombreCompleto" :aria-label="`Mi perfil: ${nombreCompleto}`">
      <img v-if="usuario.face_photo" :src="usuario.face_photo" alt="" />
      <span v-else>{{ iniciales }}</span>
    </RouterLink>

    <!-- Panel flotante de submódulos -->
    <div
      v-if="grupoAbierto"
      class="menu-panel"
      role="menu"
      :style="{ top: `${panelTop}px` }"
      @mouseenter="cancelarCierre"
      @mouseleave="programarCierre"
    >
      <div class="menu-panel-titulo">{{ grupoAbierto.etiqueta }}</div>
      <RouterLink
        v-for="hijo in grupoAbierto.hijos"
        :key="hijo.to"
        :to="hijo.to"
        class="menu-panel-link"
        :class="{ 'is-activo': activoHijo(hijo) }"
        role="menuitem"
        @click="cerrar"
      >
        {{ hijo.etiqueta }}
      </RouterLink>
    </div>
  </aside>

  <!-- Celular: barra inferior -->
  <template v-else>
    <nav class="barra-inferior" aria-label="Menú principal">
      <template v-for="item in itemsBarra" :key="item.id">
        <RouterLink
          v-if="!item.hijos"
          :to="item.to"
          class="barra-item"
          :class="{ 'is-activo': activo(item) }"
          @click="cerrar"
        >
          <i :class="['ti', item.icono]" aria-hidden="true"></i>
          <span>{{ item.etiqueta }}</span>
          <b v-if="item.contador && alertasCount > 0" class="menu-contador">{{ alertasCount > 99 ? '99+' : alertasCount }}</b>
        </RouterLink>
        <button
          v-else
          type="button"
          class="barra-item"
          :class="{ 'is-activo': activo(item) }"
          :aria-expanded="abierto === item.id"
          @click="abrirHoja(item.id)"
        >
          <i :class="['ti', item.icono]" aria-hidden="true"></i>
          <span>{{ item.etiqueta }}</span>
        </button>
      </template>

      <button
        v-if="itemsMas.length"
        type="button"
        class="barra-item"
        :class="{ 'is-activo': itemsMas.some(activo) }"
        :aria-expanded="abierto === 'mas'"
        @click="abrirHoja('mas')"
      >
        <i class="ti ti-dots" aria-hidden="true"></i>
        <span>Más</span>
      </button>
    </nav>

    <div v-if="abierto" class="hoja-fondo" @click="cerrar"></div>
    <section v-if="abierto" class="hoja" role="dialog" aria-modal="true" :aria-label="hojaTitulo">
      <header class="hoja-cabecera">
        <strong>{{ hojaTitulo }}</strong>
        <button type="button" class="hoja-cerrar" aria-label="Cerrar" @click="cerrar"><i class="ti ti-x"></i></button>
      </header>

      <div v-for="grupo in hojaGrupos" :key="grupo.id" class="hoja-grupo">
        <div v-if="abierto === 'mas'" class="hoja-grupo-titulo">{{ grupo.etiqueta }}</div>
        <RouterLink
          v-for="hijo in grupo.hijos || [{ etiqueta: grupo.etiqueta, to: grupo.to, exacto: grupo.exacto }]"
          :key="hijo.to"
          :to="hijo.to"
          class="hoja-link"
          :class="{ 'is-activo': activoHijo(hijo) }"
          @click="cerrar"
        >
          {{ hijo.etiqueta }}
        </RouterLink>
      </div>

      <div v-if="abierto === 'mas'" class="hoja-grupo">
        <div class="hoja-grupo-titulo">Cuenta</div>
        <RouterLink to="/dashboard/perfil" class="hoja-link" @click="cerrar">Mi perfil</RouterLink>
        <button type="button" class="hoja-link hoja-salir" @click="salir">Cerrar sesión</button>
      </div>
    </section>
  </template>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { menuVisible, rutaActiva } from '@/config/menu';
import { tienePermiso } from '@/utils/permisos';
import '@/styles/sidebar.css';

const props = defineProps({
  user: { type: Object, default: () => ({}) },
  alertasCount: { type: Number, default: 0 },
});

const emit = defineEmits(['logout']);

const route = useRoute();
const usuario = computed(() => props.user || {});

const items = computed(() => menuVisible((permiso) => tienePermiso(usuario.value, permiso)));

const nombreCompleto = computed(
  () => [usuario.value.name, usuario.value.paterno, usuario.value.materno].filter(Boolean).join(' ') || 'Usuario'
);

const iniciales = computed(() => {
  const partes = [usuario.value.name, usuario.value.paterno].filter(Boolean);

  return (partes.map((p) => p.charAt(0)).join('') || 'U').toUpperCase();
});

function activoHijo(hijo) {
  return rutaActiva(route.path, hijo.to, hijo.exacto);
}

function activo(item) {
  return item.hijos ? item.hijos.some(activoHijo) : rutaActiva(route.path, item.to, item.exacto);
}

// ---------- pantalla angosta
const esMovil = ref(false);
let consulta = null;
let temporizador = null;

function actualizarMovil() {
  esMovil.value = consulta?.matches ?? false;
  abierto.value = null;
}

onMounted(() => {
  consulta = window.matchMedia('(max-width: 767px)');
  esMovil.value = consulta.matches;
  consulta.addEventListener('change', actualizarMovil);
});

onBeforeUnmount(() => {
  consulta?.removeEventListener('change', actualizarMovil);
  clearTimeout(temporizador);
});

// ---------- panel flotante (escritorio) y hoja inferior (celular)
const abierto = ref(null);
const panelTop = ref(0);

const grupoAbierto = computed(() => items.value.find((i) => i.id === abierto.value && i.hijos) || null);

function posicionar(evento) {
  const caja = evento.currentTarget.getBoundingClientRect();

  panelTop.value = Math.max(8, Math.min(caja.top, window.innerHeight - 8 - 44 * 8));
}

function abrirHover(evento, item) {
  cancelarCierre();
  posicionar(evento);
  abierto.value = item.id;
}

function alternar(evento, item) {
  cancelarCierre();
  posicionar(evento);
  abierto.value = abierto.value === item.id ? null : item.id;
}

function programarCierre() {
  cancelarCierre();
  temporizador = setTimeout(() => (abierto.value = null), 180);
}

function cancelarCierre() {
  clearTimeout(temporizador);
}

function cerrar() {
  cancelarCierre();
  abierto.value = null;
}

function salir() {
  cerrar();
  emit('logout');
}

watch(() => route.fullPath, cerrar);

// ---------- barra inferior
const itemsBarra = computed(() => (items.value.length <= 5 ? items.value : items.value.slice(0, 4)));
const itemsMas = computed(() => (items.value.length <= 5 ? [] : items.value.slice(4)));

function abrirHoja(id) {
  abierto.value = abierto.value === id ? null : id;
}

const hojaGrupos = computed(() => {
  if (abierto.value === 'mas') return itemsMas.value;

  const grupo = items.value.find((i) => i.id === abierto.value);

  return grupo ? [grupo] : [];
});

const hojaTitulo = computed(() =>
  abierto.value === 'mas' ? 'Más opciones' : items.value.find((i) => i.id === abierto.value)?.etiqueta || ''
);
</script>
