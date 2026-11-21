// Exportación de la lista de comunidades (PDF y Excel). Las librerías pesadas se cargan solo al
// exportar, para no engordar la carga inicial. Formato boliviano en todas las cifras.

import { altitud, hectareas, numero } from '@/utils/formato';

function filas(lista) {
  return lista.map((c) => ({
    Comunidad: c.nombre_comunidad || '—',
    Municipio: c.municipio || '—',
    Provincia: c.provincia || '—',
    Departamento: c.departamento || '—',
    Altitud: c.altitud_msnm ? altitud(c.altitud_msnm) : '—',
    Productores: c.productores_count ?? 0,
    Parcelas: c.parcelas_count ?? 0,
    Superficie: hectareas(c.superficie_total_ha ?? 0),
    'Parcelas con alerta': c.parcelas_con_alerta ?? 0,
    Ubicación: c.latitud !== null && c.latitud !== '' && c.longitud !== null && c.longitud !== '' ? 'Con GPS' : 'Sin ubicación',
    Estado: c.activo ? 'Activa' : 'Inactiva',
  }));
}

function metadatos(lista, usuario, filtros) {
  const nombre = [usuario?.name, usuario?.paterno, usuario?.materno].filter(Boolean).join(' ') || 'Usuario no identificado';

  return {
    titulo: 'Reporte de Comunidades',
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    generadoPor: nombre,
    rol: usuario?.rol?.nombre || usuario?.rol || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: lista.length,
    productores: lista.reduce((s, c) => s + (c.productores_count ?? 0), 0),
    parcelas: lista.reduce((s, c) => s + (c.parcelas_count ?? 0), 0),
    superficie: lista.reduce((s, c) => s + (c.superficie_total_ha ?? 0), 0),
    filtros: filtros || 'Sin filtros',
  };
}

const hoy = () => new Date().toISOString().slice(0, 10);

export async function exportarComunidadesPdf(lista, usuario, filtros) {
  const [{ default: jsPDF }, { default: autoTable }] = await Promise.all([import('jspdf'), import('jspdf-autotable')]);
  const m = metadatos(lista, usuario, filtros);
  const datos = filas(lista);
  const doc = new jsPDF('landscape');

  doc.setFillColor(13, 61, 43);
  doc.rect(0, 0, 297, 28, 'F');
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(16);
  doc.setFont('helvetica', 'bold');
  doc.text(m.sistema, 14, 10);
  doc.setFontSize(12);
  doc.setFont('helvetica', 'normal');
  doc.text(m.titulo, 14, 19);

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(10);
  doc.text(`Generado por: ${m.generadoPor} (${m.rol})`, 14, 38);
  doc.text(`Fecha y hora: ${m.fecha}`, 14, 44);
  doc.text(`Comunidades: ${numero(m.total, { decimales: 0 })}   Productores: ${numero(m.productores, { decimales: 0 })}   Parcelas: ${numero(m.parcelas, { decimales: 0 })}   Superficie: ${hectareas(m.superficie)}`, 14, 50);
  doc.text(`Filtros: ${m.filtros}`, 14, 56);

  autoTable(doc, {
    startY: 62,
    head: [Object.keys(datos[0] || { Comunidad: '' })],
    body: datos.map((f) => Object.values(f)),
    styles: { fontSize: 8, cellPadding: 2 },
    headStyles: { fillColor: [26, 92, 62], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    didDrawPage: () => {
      const alto = doc.internal.pageSize.getHeight();

      doc.setFontSize(8);
      doc.setTextColor(100, 116, 139);
      doc.text('Documento generado automáticamente por el Sistema PROINPA', 14, alto - 10);
    },
  });

  doc.save(`reporte_comunidades_${hoy()}.pdf`);
}

export async function exportarComunidadesExcel(lista, usuario, filtros) {
  const [XLSX, { saveAs }] = await Promise.all([import('xlsx'), import('file-saver')]);
  const m = metadatos(lista, usuario, filtros);
  const cabecera = [
    ['Sistema', m.sistema],
    ['Reporte', m.titulo],
    ['Generado por', `${m.generadoPor} (${m.rol})`],
    ['Fecha y hora', m.fecha],
    ['Comunidades', numero(m.total, { decimales: 0 })],
    ['Productores', numero(m.productores, { decimales: 0 })],
    ['Parcelas', numero(m.parcelas, { decimales: 0 })],
    ['Superficie', hectareas(m.superficie)],
    ['Filtros', m.filtros],
    [],
  ];
  const hoja = XLSX.utils.aoa_to_sheet(cabecera);

  XLSX.utils.sheet_add_json(hoja, filas(lista), { origin: `A${cabecera.length + 1}` });
  hoja['!cols'] = [{ wch: 28 }, { wch: 20 }, { wch: 20 }, { wch: 16 }, { wch: 18 }, { wch: 12 }, { wch: 10 }, { wch: 14 }, { wch: 18 }, { wch: 14 }, { wch: 10 }];

  const libro = XLSX.utils.book_new();

  XLSX.utils.book_append_sheet(libro, hoja, 'Comunidades');
  saveAs(
    new Blob([XLSX.write(libro, { bookType: 'xlsx', type: 'array' })], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    }),
    `reporte_comunidades_${hoy()}.xlsx`
  );
}
