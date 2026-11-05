// Lógica de roles y permisos compartida por el router, el layout y las vistas.

export function obtenerRolNombre(user) {
  const rol = user?.rol ?? user?.role;

  return (typeof rol === 'string' ? rol : rol?.nombre) || '';
}

export function obtenerPermisos(user) {
  const permisos = user?.permisos || user?.rol?.permisos || [];

  if (Array.isArray(permisos)) {
    return permisos;
  }

  if (typeof permisos === 'string') {
    try {
      const lista = JSON.parse(permisos);
      return Array.isArray(lista) ? lista : [];
    } catch {
      return [];
    }
  }

  return [];
}

// Acepta un permiso o un arreglo (basta con tener uno de ellos).
export function tienePermiso(user, permiso) {
  if (!permiso) {
    return true;
  }

  if (obtenerRolNombre(user) === 'Superadmin') {
    return true;
  }

  const permisos = obtenerPermisos(user);

  return Array.isArray(permiso)
    ? permiso.some((p) => permisos.includes(p))
    : permisos.includes(permiso);
}

export function usuarioGuardado() {
  try {
    return JSON.parse(localStorage.getItem('user_data') || '{}');
  } catch {
    return {};
  }
}
