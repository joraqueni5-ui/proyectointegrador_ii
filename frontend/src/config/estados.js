// Un color fijo por estado en TODO el sistema (StatusBadge).
//   verde: terminado o sano · gris: sin actividad · ámbar: a la espera · azul: en marcha
//   naranja: requiere atención · rojo: problema o rechazo

export const TONOS = {
  verde: { fondo: '#edfaf4', texto: '#166534', punto: '#2e7d5a' },
  gris: { fondo: '#f1f5f9', texto: '#475569', punto: '#8ba3b5' },
  ambar: { fondo: '#fffbeb', texto: '#92400e', punto: '#d97706' },
  azul: { fondo: '#eff6ff', texto: '#1d4ed8', punto: '#2563eb' },
  naranja: { fondo: '#fff3e8', texto: '#9a3412', punto: '#f07a2a' },
  rojo: { fondo: '#fef2f2', texto: '#b91c1c', punto: '#dc2626' },
};

const POR_TONO = {
  verde: [
    'activo', 'activa', 'sano', 'sana', 'confirmado', 'confirmada', 'completada', 'completado', 'resuelta', 'resuelto',
    'aprobada', 'aprobado', 'pagada', 'pagado', 'validada', 'validado', 'atendida', 'cosechado', 'convertida', 'disponible',
  ],
  gris: ['inactivo', 'inactiva', 'en descanso', 'cancelada', 'cancelado', 'anulada', 'anulado', 'ignorada', 'abandonado', 'descartada', 'archivada'],
  ambar: ['pendiente', 'programada', 'programado', 'por vencer', 'borrador', 'parcial', 'nueva', 'en mantenimiento', 'por validar'],
  azul: ['en curso', 'en proceso', 'en tratamiento', 'planificada', 'planificado', 'en revision', 'en seguimiento'],
  naranja: ['con alerta', 'corregido', 'corregida', 'escalada', 'reincidente'],
  rojo: ['rechazado', 'rechazada', 'vencido', 'vencida', 'sin stock', 'perdido', 'no realizada', 'critico', 'crítico'],
};

const INDICE = {};

for (const [tono, estados] of Object.entries(POR_TONO)) {
  for (const e of estados) INDICE[e] = tono;
}

export function normalizarEstado(valor) {
  return String(valor ?? '')
    .replace(/_/g, ' ')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .trim()
    .toLowerCase();
}

export function tonoDeEstado(valor) {
  return INDICE[normalizarEstado(valor)] || 'gris';
}

// "En_proceso" -> "En proceso"
export function etiquetaDeEstado(valor) {
  const texto = String(valor ?? '').replace(/_/g, ' ').trim();

  return texto ? texto.charAt(0).toUpperCase() + texto.slice(1) : '';
}

// Prioridad de alertas por puntaje.
export function nivelPrioridad(puntaje) {
  const n = Number(puntaje);

  if (!Number.isFinite(n)) return null;
  if (n >= 80) return { nivel: 'Urgente', fondo: '#b91c1c', texto: '#ffffff' };
  if (n >= 60) return { nivel: 'Alta', fondo: '#f07a2a', texto: '#111827' };
  if (n >= 40) return { nivel: 'Media', fondo: '#fde68a', texto: '#111827' };

  return { nivel: 'Baja', fondo: '#dce6ed', texto: '#111827' };
}
