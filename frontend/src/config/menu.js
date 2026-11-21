// Estructura del menú compacto. Cada permiso es el mismo que exige la ruta en el router,
// así el menú nunca muestra algo a lo que el usuario no puede entrar.

export const MENU = [
  { id: 'inicio', etiqueta: 'Inicio', icono: 'ti-home-2', to: '/dashboard', exacto: true },
  {
    id: 'campo',
    etiqueta: 'Campo',
    icono: 'ti-plant-2',
    hijos: [
      { etiqueta: 'Comunidades', to: '/dashboard/comunidades', permiso: 'gestionar_productores' },
      { etiqueta: 'Productores', to: '/dashboard/productores', permiso: 'gestionar_productores' },
      { etiqueta: 'Parcelas', to: '/dashboard/parcelas', permiso: 'gestionar_parcelas' },
      { etiqueta: 'Cultivos', to: '/dashboard/cultivos', permiso: 'gestionar_parcelas' },
      { etiqueta: 'Cosechas', to: '/dashboard/cosechas', permiso: 'registrar_cosechas' },
      { etiqueta: 'Historial fitosanitario', to: '/dashboard/historial-fitosanitario', permiso: 'gestionar_parcelas' },
    ],
  },
  { id: 'visitas', etiqueta: 'Visitas', icono: 'ti-calendar-event', to: '/dashboard/inspecciones', permiso: 'ver_inspecciones' },
  {
    id: 'diagnostico',
    etiqueta: 'Diagnóstico',
    icono: 'ti-microscope',
    hijos: [
      { etiqueta: 'Diagnósticos', to: '/dashboard/diagnosticos', permiso: 'ver_diagnosticos' },
      { etiqueta: 'Enfermedades', to: '/dashboard/enfermedades', permiso: 'gestionar_enfermedades' },
      { etiqueta: 'Desempeño del modelo', to: '/dashboard/desempeno-modelo', permiso: { todos: ['ver_diagnosticos', 'ver_reportes'] } },
    ],
  },
  { id: 'alertas', etiqueta: 'Alertas', icono: 'ti-bell-ringing', to: '/dashboard/alertas', permiso: 'ver_alertas', contador: true },
  {
    id: 'inventario',
    etiqueta: 'Inventario',
    icono: 'ti-package',
    hijos: [
      { etiqueta: 'Resumen', to: '/dashboard/inventario', permiso: 'ver_inventario', exacto: true },
      { etiqueta: 'Productos', to: '/dashboard/inventario/productos', permiso: 'gestionar_productos' },
      { etiqueta: 'Almacenes', to: '/dashboard/inventario/almacenes', permiso: 'gestionar_almacenes' },
      { etiqueta: 'Movimientos', to: '/dashboard/inventario/movimientos', permiso: 'gestionar_movimientos' },
      { etiqueta: 'Alertas de stock', to: '/dashboard/inventario/alertas-stock', permiso: 'ver_alertas_stock' },
    ],
  },
  {
    id: 'ventas',
    etiqueta: 'Ventas',
    icono: 'ti-receipt-2',
    hijos: [
      { etiqueta: 'Ventas', to: '/dashboard/ventas', permiso: 'gestionar_ventas' },
      { etiqueta: 'Cotizaciones', to: '/dashboard/cotizaciones', permiso: 'gestionar_cotizaciones' },
    ],
  },
  {
    id: 'reportes',
    etiqueta: 'Reportes',
    icono: 'ti-chart-bar',
    hijos: [
      {
        etiqueta: 'Fitosanitarios',
        to: '/dashboard/reportes-fitosanitarios',
        permiso: { todos: ['ver_reportes', 'ver_diagnosticos'] },
      },
      { etiqueta: 'Comerciales', to: '/dashboard/reportes', permiso: 'ver_reportes' },
    ],
  },
  { id: 'usuarios', etiqueta: 'Usuarios', icono: 'ti-users', to: '/dashboard/usuarios', permiso: 'gestionar_usuarios' },
];

// Devuelve solo lo que el usuario puede ver; un grupo sin hijos visibles desaparece.
export function menuVisible(tienePermiso) {
  const resultado = [];

  for (const item of MENU) {
    if (item.hijos) {
      const hijos = item.hijos.filter((h) => tienePermiso(h.permiso));

      if (hijos.length) resultado.push({ ...item, hijos });
    } else if (tienePermiso(item.permiso)) {
      resultado.push(item);
    }
  }

  return resultado;
}

export function rutaActiva(ruta, to, exacto = false) {
  return ruta === to || (!exacto && ruta.startsWith(`${to}/`));
}
