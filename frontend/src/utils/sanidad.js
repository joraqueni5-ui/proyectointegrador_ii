// Estado sanitario de las parcelas: colores del mapa, etiquetas y textos de la lista.
// El estado lo calcula el backend (Parcela::estado_sanitario); aquí solo se presenta.

import { diasDesdeHoy } from '@/utils/fechas';
import { fechaRelativa } from '@/utils/formato';

export const ESTADOS_SANIDAD = {
  alerta: { clave: 'alerta', etiqueta: 'Con alerta activa', color: '#f07a2a', tono: 'naranja' },
  sana: { clave: 'sana', etiqueta: 'Sana', color: '#2e7d5a', tono: 'verde' },
  sin_visita: { clave: 'sin_visita', etiqueta: 'Sin visita hace más de 30 días', color: '#d97706', tono: 'ambar' },
  descanso: { clave: 'descanso', etiqueta: 'En descanso o inactiva', color: '#8ba3b5', tono: 'gris' },
};

export const sanidadDe = (parcela) => ESTADOS_SANIDAD[parcela?.estado_sanitario] || ESTADOS_SANIDAD.sin_visita;

// Radio del círculo en el mapa: proporcional a la superficie, mínimo 8 px.
export function radioParcela(hectareas) {
  const ha = Number(hectareas) || 0;

  return Math.min(34, Math.max(8, Math.round(Math.sqrt(ha) * 14)));
}

const sinEtapa = (c) => c?.etapa_fenologica?.nombre || null;

// Día del cultivo desde la siembra (día 1 = fecha de siembra).
export function diaDeCultivo(cultivo) {
  const dias = cultivo?.fecha_siembra ? diasDesdeHoy(cultivo.fecha_siembra) : null;

  return dias === null ? null : Math.max(1, -dias + 1);
}

export function resumenCultivo(parcela) {
  const c = parcela?.cultivo_activo;

  if (!c) return '';

  const partes = [c.variedad_papa, sinEtapa(c)].filter(Boolean);

  return partes.join(', ');
}

// Línea de estado de la tarjeta: "Tizón tardío · 85 · Waych'a, floración" o "Sana · visita hace 5 días".
export function lineaEstado(parcela) {
  switch (parcela?.estado_sanitario) {
    case 'alerta':
      return [parcela.alerta_enfermedad || 'Alerta activa', parcela.alerta_puntaje, resumenCultivo(parcela)].filter(Boolean).join(' · ');
    case 'sana':
      return `Sana · visita ${fechaRelativa(parcela.ultima_visita)}`;
    case 'descanso':
      return parcela.estado === 'Inactiva' ? 'Inactiva' : 'En descanso';
    default:
      return parcela?.ultima_visita ? `Sin visita desde ${fechaRelativa(parcela.ultima_visita)}` : 'Aún sin visitas registradas';
  }
}
