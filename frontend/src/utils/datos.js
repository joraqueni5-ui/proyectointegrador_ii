// Ayudantes para normalizar respuestas del backend.

// Acepta un arreglo o una respuesta paginada de Laravel ({ data: [...] }).
export function normalizeList(data) {
  if (Array.isArray(data)) {
    return data;
  }

  if (Array.isArray(data?.data)) {
    return data.data;
  }

  return [];
}

// Stock total de un producto: usa stock_total del backend o suma sus inventarios.
export function stockProducto(producto) {
  if (!producto) {
    return 0;
  }

  if (producto.stock_total !== undefined && producto.stock_total !== null) {
    return Number(producto.stock_total) || 0;
  }

  if (Array.isArray(producto.inventarios)) {
    return producto.inventarios.reduce(
      (sum, inventario) => sum + (Number(inventario.stock_actual) || 0),
      0
    );
  }

  return 0;
}

// Datos de paginación de una respuesta de Laravel, o null si no está paginada.
export function paginacionDe(data) {
  return data?.current_page
    ? {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    : null;
}
