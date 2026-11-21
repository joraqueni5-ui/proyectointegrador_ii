import { createRouter, createWebHistory } from 'vue-router';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

// ── Auth ───────────────────────────────────────────────────────────────────
import LoginView from '../views/auth/LoginView.vue';
import OtpView from '../views/auth/OtpView.vue';

// ── Dashboard ──────────────────────────────────────────────────────────────
import DashboardLayout from '../components/layout/DashboardLayout.vue';
import HomeView from '../views/dashboard/HomeView.vue';
import ProfileView from '../views/dashboard/ProfileView.vue';

// ── Admin ──────────────────────────────────────────────────────────────────
import UsersView from '../views/dashboard/admin/UsersView.vue';

// ── Agrícola ───────────────────────────────────────────────────────────────
import ComunidadesView from '../views/dashboard/agricola/ComunidadesView.vue';
import ProductoresView from '../views/dashboard/agricola/ProductoresView.vue';
import ParcelasView from '../views/dashboard/agricola/ParcelasView.vue';
import CosechasView from '../views/dashboard/agricola/CosechasView.vue';

// ── Diagnóstico ────────────────────────────────────────────────────────────
import InspeccionesView from '../views/dashboard/diagnostico/InspeccionesView.vue';
import DiagnosticosView from '../views/dashboard/diagnostico/DiagnosticosView.vue';
import AlertasView from '../views/dashboard/diagnostico/AlertasView.vue';

const routes = [
  // ── Auth ─────────────────────────────────────────────────────────────────
  {
    path: '/',
    component: LoginView,
    name: 'login',
    meta: { guestOnly: true },
  },

  // Sistema interno: no hay registro público
  {
    path: '/register',
    redirect: { name: 'login' },
  },

  {
    path: '/verificar-otp',
    component: OtpView,
    name: 'otp',
    meta: { guestOnly: true, requiresPendingOtp: true },
    props: (route) => ({ email: route.query.email }),
  },

  // ── Dashboard ────────────────────────────────────────────────────────────
  {
    path: '/dashboard',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        component: HomeView,
        name: 'home',
      },
      {
        path: 'perfil',
        component: ProfileView,
        name: 'perfil',
      },

      // ── Comercial / Inventario ───────────────────────────────────────────
      {
        path: 'inventario',
        component: () => import('../views/dashboard/comercial/InventarioView.vue'),
        name: 'inventario',
        meta: { permiso: 'ver_inventario' },
      },
      {
        path: 'inventario/productos',
        component: () => import('../views/dashboard/comercial/inventario/ProductosView.vue'),
        name: 'inventario-productos',
        meta: { permiso: 'gestionar_productos' },
      },
      {
        path: 'inventario/almacenes',
        component: () => import('../views/dashboard/comercial/inventario/AlmacenesView.vue'),
        name: 'inventario-almacenes',
        meta: { permiso: 'gestionar_almacenes' },
      },
      {
        path: 'inventario/movimientos',
        component: () => import('../views/dashboard/comercial/inventario/MovimientosView.vue'),
        name: 'inventario-movimientos',
        meta: { permiso: 'gestionar_movimientos' },
      },
      {
        path: 'inventario/alertas-stock',
        component: () => import('../views/dashboard/comercial/inventario/AlertasStockView.vue'),
        name: 'inventario-alertas-stock',
        meta: { permiso: 'ver_alertas_stock' },
      },
      {
        path: 'ventas',
        component: () => import('../views/dashboard/comercial/VentasView.vue'),
        name: 'ventas',
        meta: { permiso: 'gestionar_ventas' },
      },
      {
        path: 'cotizaciones',
        component: () => import('../views/dashboard/comercial/CotizacionesView.vue'),
        name: 'cotizaciones',
        meta: { permiso: 'gestionar_cotizaciones' },
      },
      {
        path: 'reportes',
        component: () => import('../views/dashboard/comercial/ReportesView.vue'),
        name: 'reportes',
        meta: { permiso: 'ver_reportes' },
      },

      // ── Admin ────────────────────────────────────────────────────────────
      {
        path: 'usuarios',
        component: UsersView,
        name: 'usuarios',
        meta: { permiso: 'gestionar_usuarios' },
      },
      {
        // Guía interna de componentes (solo administración)
        path: 'guia-ui',
        component: () => import('../views/dashboard/admin/GuiaUiView.vue'),
        name: 'guia-ui',
        meta: { permiso: 'gestionar_usuarios' },
      },

      // ── Agrícola ─────────────────────────────────────────────────────────
      {
        path: 'comunidades',
        component: ComunidadesView,
        name: 'comunidades',
        meta: { permiso: 'gestionar_productores' },
      },
      {
        path: 'productores',
        component: ProductoresView,
        name: 'productores',
        meta: { permiso: 'gestionar_productores' },
      },
      {
        path: 'parcelas',
        component: ParcelasView,
        name: 'parcelas',
        meta: { permiso: 'gestionar_parcelas' },
      },
      {
        path: 'historial-fitosanitario',
        name: 'historial-fitosanitario',
        component: () => import('../views/dashboard/agricola/HistorialFitosanitarioView.vue'),
        meta: { permiso: 'gestionar_parcelas' },
      },
      {
        path: 'cosechas',
        component: CosechasView,
        name: 'cosechas',
        meta: { permiso: 'registrar_cosechas' },
      },
      {
        path: 'cultivos',
        name: 'cultivos',
        component: () => import('@/views/dashboard/agricola/CultivosView.vue'),
        meta: { permiso: 'gestionar_parcelas' },
      },

      // ── Diagnóstico ──────────────────────────────────────────────────────
      {
        path: 'inspecciones',
        component: InspeccionesView,
        name: 'inspecciones',
        meta: { permiso: 'ver_inspecciones' },
      },
      {
        path: 'diagnosticos',
        component: DiagnosticosView,
        name: 'diagnosticos',
        meta: { permiso: 'ver_diagnosticos' },
      },
      {
        path: 'desempeno-modelo',
        component: () => import('../views/dashboard/diagnostico/DesempenoModeloView.vue'),
        name: 'desempeno-modelo',
        meta: { permiso: { todos: ['ver_diagnosticos', 'ver_reportes'] } },
      },
      {
        path: 'alertas',
        component: AlertasView,
        name: 'alertas',
        meta: { permiso: 'ver_alertas' },
      },
      {
        path: 'enfermedades',
        component: () => import('../views/dashboard/diagnostico/EnfermedadesView.vue'),
        name: 'enfermedades',
        meta: { permiso: 'gestionar_enfermedades' },
      },
      {
        path: 'reportes-fitosanitarios',
        component: () => import('../views/dashboard/diagnostico/ReportesFitosanitariosView.vue'),
        name: 'reportes-fitosanitarios',
        meta: { permiso: { todos: ['ver_reportes', 'ver_diagnosticos'] } },
      },
    ],
  },

  // ── Ruta no encontrada ───────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'login' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// ── Helpers ────────────────────────────────────────────────────────────────
function tieneOtpPendiente() {
  return Boolean(localStorage.getItem('pending_email'));
}

// ── Guards ────────────────────────────────────────────────────────────────
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('user_token');
  const userData = usuarioGuardado();

  const requiereAuth = to.matched.some((route) => route.meta.requiresAuth);
  const soloInvitado = to.matched.some((route) => route.meta.guestOnly);
  const requiereOtpPendiente = to.matched.some((route) => route.meta.requiresPendingOtp);

  const permisoNecesario = to.matched
    .map((route) => route.meta.permiso)
    .filter(Boolean)
    .at(-1);

  // 1. Si intenta entrar al dashboard sin token, vuelve al login
  if (requiereAuth && !token) {
    return next({ name: 'login' });
  }

  // 2. Si intenta entrar a OTP sin login pendiente, vuelve al login
  if (requiereOtpPendiente && !token && !tieneOtpPendiente()) {
    return next({ name: 'login' });
  }

  // 3. Si ya tiene token y entra a login u OTP, va al dashboard
  if (soloInvitado && token) {
    return next({ name: 'home' });
  }

  // 4. Si la ruta pide permiso y el usuario no lo tiene, va al home
  if (requiereAuth && permisoNecesario && !tienePermiso(userData, permisoNecesario)) {
    return next(to.name !== 'home' ? { name: 'home' } : false);
  }

  return next();
});

export default router;