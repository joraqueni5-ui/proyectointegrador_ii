// Ayudantes de fechas que llegan del backend (YYYY-MM-DD o ISO).
// Se usa solo la parte de fecha para evitar desfases por zona horaria.

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

export function partesFecha(valor) {
  const coincide = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(valor ?? ''));

  if (!coincide) {
    return null;
  }

  return { anio: Number(coincide[1]), mes: Number(coincide[2]), dia: Number(coincide[3]) };
}

export function mesCorto(valor) {
  const partes = partesFecha(valor);

  return partes ? MESES[partes.mes - 1] : '';
}

// Días desde hoy: 0 = hoy, 1 = mañana, -1 = ayer. null si la fecha no es válida.
export function diasDesdeHoy(valor) {
  const partes = partesFecha(valor);

  if (!partes) {
    return null;
  }

  const hoy = new Date();
  const fecha = Date.UTC(partes.anio, partes.mes - 1, partes.dia);
  const actual = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());

  return Math.round((fecha - actual) / 86400000);
}

export function fechaRelativa(valor) {
  const dias = diasDesdeHoy(valor);

  if (dias === null) return '';
  if (dias === 0) return 'Hoy';
  if (dias === 1) return 'Mañana';
  if (dias === -1) return 'Ayer';

  return dias > 0 ? `En ${dias} días` : `Hace ${-dias} días`;
}
