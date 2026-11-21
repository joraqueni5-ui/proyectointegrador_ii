// Formato boliviano (es-BO) para toda la interfaz: números con coma decimal y punto de miles,
// montos en Bs, fechas "9 nov. 2026" y tiempos relativos "hace 3 días".
// Las fechas AAAA-MM-DD se leen sin pasar por Date para evitar desfases de zona horaria.

import { diasDesdeHoy, partesFecha } from '@/utils/fechas';

const LOCALE = 'es-BO';
const MESES = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
const VACIO = '—';

const formateadores = new Map();

function formateador(min, max) {
  const clave = `${min}-${max}`;

  if (!formateadores.has(clave)) {
    formateadores.set(clave, new Intl.NumberFormat(LOCALE, { minimumFractionDigits: min, maximumFractionDigits: max }));
  }

  return formateadores.get(clave);
}

function esNumero(valor) {
  return valor !== null && valor !== undefined && valor !== '' && Number.isFinite(Number(valor));
}

// 1234.5 -> "1.234,5"
export function numero(valor, { decimales = 2, fijos = false } = {}) {
  if (!esNumero(valor)) return VACIO;

  return formateador(fijos ? decimales : 0, decimales).format(Number(valor));
}

// 0.8 -> "0,8 ha"
export function hectareas(valor, decimales = 2) {
  return esNumero(valor) ? `${numero(valor, { decimales })} ha` : VACIO;
}

// 3860 -> "3.860 m s. n. m."
export function altitud(valor) {
  return esNumero(valor) ? `${numero(valor, { decimales: 0 })} m s. n. m.` : VACIO;
}

// 1250.5 -> "Bs 1.250,50"
export function bolivianos(valor) {
  return esNumero(valor) ? `Bs ${numero(valor, { decimales: 2, fijos: true })}` : VACIO;
}

// 87 -> "87 %"
export function porcentaje(valor, decimales = 0) {
  return esNumero(valor) ? `${numero(valor, { decimales })} %` : VACIO;
}

export function toneladas(valor, decimales = 2) {
  return esNumero(valor) ? `${numero(valor, { decimales })} t` : VACIO;
}

// "2026-11-09" o ISO -> "9 nov. 2026"
export function fecha(valor) {
  const partes = partesFecha(valor);

  return partes ? `${partes.dia} ${MESES[partes.mes - 1]} ${partes.anio}` : VACIO;
}

// "2026-11-09T09:42:00" -> "9 nov. 2026, 09:42"
export function fechaHora(valor) {
  const f = fecha(valor);
  const hora = /[T ](\d{2}):(\d{2})/.exec(String(valor ?? ''));

  if (f === VACIO) return VACIO;

  return hora ? `${f}, ${hora[1]}:${hora[2]}` : f;
}

// Menos de 30 días: "hace 3 días", "hoy", "ayer", "mañana", "en 5 días". Más: fecha completa.
export function fechaRelativa(valor) {
  const dias = diasDesdeHoy(valor);

  if (dias === null) return VACIO;
  if (Math.abs(dias) >= 30) return fecha(valor);
  if (dias === 0) return 'hoy';
  if (dias === -1) return 'ayer';
  if (dias === 1) return 'mañana';

  return dias < 0 ? `hace ${-dias} días` : `en ${dias} días`;
}

// Instantes (con hora) más recientes: "hace 5 min", "hace 3 h", y luego días.
export function tiempoTranscurrido(valor) {
  const t = new Date(valor).getTime();

  if (!Number.isFinite(t)) return VACIO;

  const minutos = Math.round((Date.now() - t) / 60000);

  if (minutos < 1) return 'ahora';
  if (minutos < 60) return `hace ${minutos} min`;
  if (minutos < 1440) return `hace ${Math.round(minutos / 60)} h`;

  return fechaRelativa(String(valor));
}
