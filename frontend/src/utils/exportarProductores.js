// Exportación de productores (PDF y Excel). Las librerías se cargan solo al exportar.
import { hectareas, numero } from '@/utils/formato';

const TEXTO_SEXO = { M: 'Masculino', F: 'Femenino', Otro: 'Otro' };

function filas(lista) {
  return lista.map((p) => ({
    Productor: p.nombre_completo || '—',
    CI: `${p.carnet_identidad ?? ''}${p.complemento_ci ? `-${p.complemento_ci}` : ''} ${p.ci_extension ?? ''}`.trim() || '—',
    Teléfono: p.telefono || '—',
    Sexo: TEXTO_SEXO[p.sexo] || '—',
    Comunidad: p.comunidad?.nombre_comunidad || '—',
    Parcelas: p.parcelas_count ?? 0,
    'Superficie en parcelas': hectareas(p.superficie_parcelas_ha ?? 0),
    'Superficie declarada': p.superficie_total_ha !== null && p.superficie_total_ha !== '' && p.superficie_total_ha !== undefined ? hectareas(p.superficie_total_ha) : 'Sin declarar',
    'Parcelas con alerta': p.parcelas_con_alerta ?? 0,
    Estado: p.activo ? 'Activo' : 'Inactivo',
  }));
}

function metadatos(lista, usuario, filtros) {
  const nombre = [usuario?.name, usuario?.paterno, usuario?.materno].filter(Boolean).join(' ') || 'Usuario no identificado';

  return {
    sistema: 'Sistema PROINPA - Gestión Agrícola Inteligente',
    titulo: 'Reporte de Productores',
    generadoPor: nombre,
    rol: usuario?.rol?.nombre || usuario?.rol || '—',
    fecha: new Date().toLocaleString('es-BO'),
    total: lista.length,
    parcelas: lista.reduce((s, p) => s + (p.parcelas_count ?? 0), 0),
    superficie: lista.reduce((s, p) => s + Number(p.superficie_parcelas_ha ?? 0), 0),
    filtros: filtros || 'Sin filtros',
  };
}

const hoy = () => new Date().toISOString().slice(0, 10);

export async function exportarProductoresPdf(lista, usuario, filtros) {
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
  doc.text(`Productores: ${numero(m.total, { decimales: 0 })}   Parcelas: ${numero(m.parcelas, { decimales: 0 })}   Superficie en parcelas: ${hectareas(m.superficie)}`, 14, 50);
  doc.text(`Filtros: ${m.filtros}`, 14, 56);

  autoTable(doc, {
    startY: 62,
    head: [Object.keys(datos[0] || { Productor: '' })],
    body: datos.map((f) => Object.values(f)),
    styles: { fontSize: 7.5, cellPadding: 2 },
    headStyles: { fillColor: [26, 92, 62], textColor: 255, fontStyle: 'bold' },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    didDrawPage: () => {
      const alto = doc.internal.pageSize.getHeight();

      doc.setFontSize(8);
      doc.setTextColor(100, 116, 139);
      doc.text('Documento generado automáticamente por el Sistema PROINPA', 14, alto - 10);
    },
  });

  doc.save(`reporte_productores_${hoy()}.pdf`);
}

export async function exportarProductoresExcel(lista, usuario, filtros) {
  const [XLSX, { saveAs }] = await Promise.all([import('xlsx'), import('file-saver')]);
  const m = metadatos(lista, usuario, filtros);
  const cabecera = [
    ['Sistema', m.sistema],
    ['Reporte', m.titulo],
    ['Generado por', `${m.generadoPor} (${m.rol})`],
    ['Fecha y hora', m.fecha],
    ['Productores', numero(m.total, { decimales: 0 })],
    ['Parcelas', numero(m.parcelas, { decimales: 0 })],
    ['Superficie en parcelas', hectareas(m.superficie)],
    ['Filtros', m.filtros],
    [],
  ];
  const hoja = XLSX.utils.aoa_to_sheet(cabecera);

  XLSX.utils.sheet_add_json(hoja, filas(lista), { origin: `A${cabecera.length + 1}` });
  hoja['!cols'] = [{ wch: 30 }, { wch: 16 }, { wch: 12 }, { wch: 12 }, { wch: 22 }, { wch: 10 }, { wch: 20 }, { wch: 20 }, { wch: 18 }, { wch: 10 }];

  const libro = XLSX.utils.book_new();

  XLSX.utils.book_append_sheet(libro, hoja, 'Productores');
  saveAs(
    new Blob([XLSX.write(libro, { bookType: 'xlsx', type: 'array' })], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
    `reporte_productores_${hoy()}.xlsx`
  );
}
