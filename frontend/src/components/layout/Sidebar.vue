<template>
  <aside
    class="sidebar"
    :class="{
      'is-collapsed': collapsed,
      'is-mobile-open': mobileOpen,
    }"
  >
    <!-- Logo institucional -->
    <div class="sb-logo-row">
      <RouterLink
        to="/dashboard"
        class="sb-logo"
        @click="handleNavClick"
      >
        <div class="sb-logo-box">
          <img
            src="https://www.proinpa.org/web/wp-content/uploads/2024/01/Logo-PROINPA-2024-con-resplandor-2.png"
            alt="Logo PROINPA"
          />
        </div>

        <div class="sb-brand">
          <div class="sb-brand-name">Sistema PROINPA</div>
          <div class="sb-brand-sub">Gestión Agrícola IA</div>
        </div>
      </RouterLink>

      <button
        class="sb-collapse-btn"
        type="button"
        :title="collapsed ? 'Expandir menú' : 'Contraer menú'"
        @click="$emit('toggle-collapse')"
      >
        <i :class="collapsed ? 'ti ti-layout-sidebar-right-expand' : 'ti ti-layout-sidebar-left-collapse'"></i>
      </button>

      <button
        class="sb-mobile-close"
        type="button"
        title="Cerrar menú"
        @click="$emit('close-mobile')"
      >
        <i class="ti ti-x"></i>
      </button>
    </div>

    <!-- Usuario -->
    <div class="sb-user">
      <div class="sb-avatar">
        <img v-if="usuario.face_photo" :src="usuario.face_photo" alt="foto" />
        <span v-else>{{ inicialUsuario }}</span>
      </div>

      <div class="sb-user-info">
        <div class="sb-name">
          {{ nombreCompleto }}
        </div>
        <div class="sb-role">
          {{ rolNombre || 'Sin rol asignado' }}
        </div>
      </div>
    </div>

    <!-- Navegación -->
    <nav class="sb-nav">
      <!-- GENERAL -->
      <div class="sb-sec">General</div>

      <RouterLink
        to="/dashboard"
        class="sb-link"
        data-title="Dashboard"
        exact-active-class="active"
        @click="handleNavClick"
      >
        <i class="ti ti-layout-dashboard"></i>
        <span>Dashboard</span>
      </RouterLink>

      <!-- ADMINISTRACIÓN -->
      <template v-if="puedeVerGrupo(['gestionar_usuarios', 'gestionar_roles'])">
        <div class="sb-sec">Administración</div>

        <RouterLink
          v-if="tienePermiso('gestionar_usuarios')"
          to="/dashboard/usuarios"
          class="sb-link"
          data-title="Usuarios"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-users"></i>
          <span>Usuarios</span>
        </RouterLink>
      </template>

      <!-- MÓDULO AGRÍCOLA -->
      <template v-if="puedeVerGrupo(['gestionar_productores', 'gestionar_parcelas', 'registrar_cosechas'])">
        <div class="sb-sec">Módulo Agrícola</div>

        <RouterLink
          v-if="tienePermiso('gestionar_productores')"
          to="/dashboard/comunidades"
          class="sb-link"
          data-title="Comunidades"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-building-community"></i>
          <span>Comunidades</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_productores')"
          to="/dashboard/productores"
          class="sb-link"
          data-title="Productores"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-user-check"></i>
          <span>Productores</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_parcelas')"
          to="/dashboard/parcelas"
          class="sb-link"
          data-title="Parcelas"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-map-2"></i>
          <span>Parcelas</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_parcelas')"
          to="/dashboard/historial-fitosanitario"
          class="sb-link"
          data-title="Historial Fitosanitario"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-history"></i>
          <span>Historial Fitosanitario</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_parcelas')"
          to="/dashboard/cultivos"
          class="sb-link"
          data-title="Cultivos"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-seeding"></i>
          <span>Cultivos</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('registrar_cosechas')"
          to="/dashboard/cosechas"
          class="sb-link"
          data-title="Cosechas"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-plant-2"></i>
          <span>Cosechas</span>
        </RouterLink>
      </template>

      <!-- DIAGNÓSTICO IA -->
      <template v-if="puedeVerGrupo(['ver_inspecciones', 'ver_diagnosticos', 'gestionar_enfermedades', 'ver_alertas', 'ver_reportes'])">
        <div class="sb-sec">Diagnóstico</div>

        <RouterLink
          v-if="tienePermiso('ver_inspecciones')"
          to="/dashboard/inspecciones"
          class="sb-link"
          data-title="Inspecciones"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-clipboard-check"></i>
          <span>Inspecciones</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_enfermedades')"
          to="/dashboard/enfermedades"
          class="sb-link"
          data-title="Enfermedades"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-virus-search"></i>
          <span>Enfermedades</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('ver_diagnosticos')"
          to="/dashboard/diagnosticos"
          class="sb-link"
          data-title="Diagnósticos CNN"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-microscope"></i>
          <span>Diagnósticos</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso(['ver_reportes', 'ver_diagnosticos'])"
          to="/dashboard/desempeno-modelo"
          class="sb-link"
          data-title="Desempeño del modelo"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-chart-dots-3"></i>
          <span>Desempeño del modelo</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('ver_alertas')"
          to="/dashboard/alertas"
          class="sb-link"
          data-title="Alertas"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-bell"></i>
          <span>Alertas</span>

          <span v-if="alertasCount > 0" class="sb-badge">
            {{ alertasCount }}
          </span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso(['ver_reportes', 'ver_diagnosticos', 'ver_inspecciones'])"
          to="/dashboard/reportes-fitosanitarios"
          class="sb-link"
          data-title="Reportes Fitosanitarios"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-report-analytics"></i>
          <span>Reportes Fitosanitarios</span>
        </RouterLink>
      </template>

      <!-- COMERCIAL -->
      <template
        v-if="puedeVerGrupo([
          'ver_inventario',
          'gestionar_productos',
          'gestionar_almacenes',
          'gestionar_movimientos',
          'ver_alertas_stock',
          'gestionar_ventas',
          'gestionar_cotizaciones',
          'ver_reportes'
        ])"
      >
        <div class="sb-sec">Comercial</div>

        <RouterLink
          v-if="tienePermiso('ver_inventario')"
          to="/dashboard/inventario"
          class="sb-link"
          data-title="Inventario"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-package"></i>
          <span>Inventario</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_productos')"
          to="/dashboard/inventario/productos"
          class="sb-link"
          data-title="Productos"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-box"></i>
          <span>Productos</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_almacenes')"
          to="/dashboard/inventario/almacenes"
          class="sb-link"
          data-title="Almacenes"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-building-warehouse"></i>
          <span>Almacenes</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_movimientos')"
          to="/dashboard/inventario/movimientos"
          class="sb-link"
          data-title="Movimientos"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-arrows-exchange"></i>
          <span>Movimientos</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('ver_alertas_stock')"
          to="/dashboard/inventario/alertas-stock"
          class="sb-link"
          data-title="Alertas stock"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-alert-triangle"></i>
          <span>Alertas stock</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_ventas')"
          to="/dashboard/ventas"
          class="sb-link"
          data-title="Ventas"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-receipt"></i>
          <span>Ventas</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('gestionar_cotizaciones')"
          to="/dashboard/cotizaciones"
          class="sb-link"
          data-title="Cotizaciones"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-file-description"></i>
          <span>Cotizaciones</span>
        </RouterLink>

        <RouterLink
          v-if="tienePermiso('ver_reportes')"
          to="/dashboard/reportes"
          class="sb-link"
          data-title="Reportes"
          active-class="active"
          @click="handleNavClick"
        >
          <i class="ti ti-report-analytics"></i>
          <span>Reportes</span>
        </RouterLink>
      </template>

      <!-- CUENTA -->
      <div class="sb-sec">Cuenta</div>

      <RouterLink
        to="/dashboard/perfil"
        class="sb-link"
        data-title="Mi Perfil"
        active-class="active"
        @click="handleNavClick"
      >
        <i class="ti ti-user-circle"></i>
        <span>Mi Perfil</span>
      </RouterLink>
    </nav>

    <!-- Logout -->
    <div class="sb-footer">
      <button
        class="sb-link logout"
        type="button"
        data-title="Cerrar sesión"
        @click="handleLogout"
      >
        <i class="ti ti-logout"></i>
        <span>Cerrar sesión</span>
      </button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { obtenerRolNombre, tienePermiso as usuarioTienePermiso } from '@/utils/permisos';
import '@/styles/sidebar.css';

const props = defineProps({
  user: {
    type: Object,
    default: () => ({}),
  },
  alertasCount: {
    type: Number,
    default: 0,
  },
  collapsed: {
    type: Boolean,
    default: false,
  },
  mobileOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['logout', 'toggle-collapse', 'close-mobile']);

const usuario = computed(() => props.user || {});

const rolNombre = computed(() => obtenerRolNombre(usuario.value));

const nombreCompleto = computed(() => {
  return [
    usuario.value.name,
    usuario.value.paterno,
    usuario.value.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
});

const inicialUsuario = computed(() => {
  return nombreCompleto.value.charAt(0).toUpperCase() || 'U';
});

function tienePermiso(permiso) {
  return usuarioTienePermiso(usuario.value, permiso);
}

function puedeVerGrupo(permisosGrupo = []) {
  return tienePermiso(permisosGrupo);
}

function handleNavClick() {
  emit('close-mobile');
}

function handleLogout() {
  emit('close-mobile');
  emit('logout');
}
</script>