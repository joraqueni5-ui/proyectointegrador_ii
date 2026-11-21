// Campaña agrícola de una fecha. Espejo de App\Support\Campana (backend): ambos leen el mismo mes
// de inicio (config/agricola.php -> GET /api/configuracion/agricola). Por defecto 7: la campaña
// 2025–2026 va del 1-jul-2025 al 30-jun-2026.

import { partesFecha } from '@/utils/fechas';

let mesInicio = 7;

export function fijarMesInicioCampana(mes) {
  const n = Number(mes);

  if (Number.isInteger(n) && n >= 1 && n <= 12) mesInicio = n;
}

export function obtenerMesInicioCampana() {
  return mesInicio;
}

// Se llama una vez al cargar el layout; si falla se conserva el valor por defecto.
export async function cargarConfigAgricola() {
  try {
    const { default: api } = await import('@/services/api');
    const { data } = await api.get('/configuracion/agricola');

    fijarMesInicioCampana(data?.mes_inicio_campana);
  } catch {
    /* se usa el valor por defecto */
  }
}

const dos = (n) => String(n).padStart(2, '0');

function hoyIso() {
  const d = new Date();

  return `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;
}

/**
 * @param {string|null} fecha AAAA-MM-DD (o ISO); sin valor, hoy.
 * @returns {{ etiqueta: string, desde: string, hasta: string, anioInicio: number }}
 */
export function campanaDe(fecha = null) {
  const p = partesFecha(fecha || hoyIso());

  if (!p) return null;

  const anioInicio = p.mes >= mesInicio ? p.anio : p.anio - 1;
  const fin = new Date(Date.UTC(anioInicio + 1, mesInicio - 1, 1) - 86400000);

  return {
    anioInicio,
    etiqueta: mesInicio === 1 ? String(anioInicio) : `${anioInicio}–${anioInicio + 1}`,
    desde: `${anioInicio}-${dos(mesInicio)}-01`,
    hasta: `${fin.getUTCFullYear()}-${dos(fin.getUTCMonth() + 1)}-${dos(fin.getUTCDate())}`,
  };
}

export function etiquetaCampana(fecha = null) {
  return campanaDe(fecha)?.etiqueta ?? '';
}

// ¿La fecha cae dentro de la campaña de referencia (por defecto la actual)?
export function enCampana(fecha, referencia = null) {
  const c = campanaDe(referencia);
  const p = partesFecha(fecha);

  if (!c || !p) return false;

  const iso = `${p.anio}-${dos(p.mes)}-${dos(p.dia)}`;

  return iso >= c.desde && iso <= c.hasta;
}
