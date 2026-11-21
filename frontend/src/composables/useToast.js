import { reactive } from 'vue';

// Estado compartido de los avisos (Toast). El contenedor <ToastHost /> vive en el layout.
export const avisos = reactive({ items: [] });

let contador = 0;

function cerrar(id) {
  const i = avisos.items.findIndex((a) => a.id === id);

  if (i >= 0) avisos.items.splice(i, 1);
}

function mostrar({ mensaje, enlace = null, tipo = 'exito', duracion = 4000 }) {
  const id = ++contador;

  avisos.items.push({ id, mensaje, enlace, tipo });
  setTimeout(() => cerrar(id), duracion);

  return id;
}

// useToast().exito('Parcela guardada', { texto: 'Ver', to: '/dashboard/parcelas' })
export function useToast() {
  return {
    mostrar,
    cerrar,
    exito: (mensaje, enlace = null) => mostrar({ mensaje, enlace, tipo: 'exito' }),
    error: (mensaje) => mostrar({ mensaje, tipo: 'error', duracion: 6000 }),
  };
}
