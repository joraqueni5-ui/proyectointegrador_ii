<template>
  <div class="modal-overlay venta-modal-overlay" @click.self="close">
    <div class="modal-card venta-comprobante-modal">
      <div class="venta-comprobante-toolbar no-print">
        <div>
          <h3>Comprobante de venta</h3>
          <p>Vista previa lista para impresión.</p>
        </div>

        <div class="venta-comprobante-actions">
          <button class="btn btn-s" type="button" @click="close">
            Cerrar
          </button>

          <button class="btn btn-p" type="button" @click="imprimir">
            <i class="ti ti-printer"></i>
            Imprimir
          </button>
        </div>
      </div>

      <div class="venta-comprobante-print-area">
        <section ref="comprobanteRef" class="venta-comprobante">
          <!-- CABECERA -->
          <header class="venta-comprobante-head">
            <div>
              <h1>Sistema Agrícola</h1>
              <p>Comprobante interno de venta</p>
              <small>No válido como factura fiscal oficial</small>
            </div>

            <div class="venta-comprobante-numero">
              <span>Nro. Venta</span>
              <strong>#{{ venta?.nro_factura || venta?.id || '—' }}</strong>
            </div>
          </header>

          <!-- DATOS -->
          <div class="venta-comprobante-info">
            <div>
              <label>Productor</label>
              <p>{{ venta?.productor?.nombre_completo || '—' }}</p>
            </div>

            <div>
              <label>Teléfono</label>
              <p>{{ venta?.productor?.telefono || '—' }}</p>
            </div>

            <div>
              <label>Fecha emisión</label>
              <p>{{ formatDate(venta?.fecha_emision) }}</p>
            </div>

            <div>
              <label>Método de pago</label>
              <p>{{ venta?.metodo_pago || '—' }}</p>
            </div>

            <div>
              <label>Estado</label>
              <p>{{ venta?.estado || '—' }}</p>
            </div>

            <div>
              <label>Registrado por</label>
              <p>{{ nombreUsuario(venta?.usuario) }}</p>
            </div>
          </div>

          <!-- PRODUCTOS -->
          <div class="venta-comprobante-section">
            <h2>Detalle de productos</h2>

            <table class="venta-comprobante-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Precio Unit.</th>
                  <th>Subtotal</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="detalle in venta?.detalles || []"
                  :key="detalle.id"
                >
                  <td>
                    <strong>{{ detalle.producto?.nombre_producto || '—' }}</strong>
                    <small>{{ detalle.producto?.codigo_producto || 'Sin código' }}</small>
                  </td>

                  <td>
                    {{ numero(detalle.cantidad) }}
                    {{ detalle.producto?.unidad_medida || '' }}
                  </td>

                  <td>Bs {{ numero(detalle.precio_unitario) }}</td>

                  <td>
                    <strong>Bs {{ numero(detalle.subtotal) }}</strong>
                  </td>
                </tr>

                <tr v-if="!venta?.detalles?.length">
                  <td colspan="4" class="venta-comprobante-empty">
                    Sin productos registrados.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- TOTALES -->
          <div class="venta-comprobante-totales">
            <div>
              <span>Subtotal</span>
              <strong>Bs {{ numero(venta?.subtotal) }}</strong>
            </div>

            <div>
              <span>Descuento</span>
              <strong>Bs {{ numero(venta?.descuento) }}</strong>
            </div>

            <div class="total">
              <span>Total</span>
              <strong>Bs {{ numero(venta?.monto_total) }}</strong>
            </div>
          </div>

          <!-- OBSERVACIONES -->
          <div
            v-if="venta?.observaciones"
            class="venta-comprobante-observaciones"
          >
            <h2>Observaciones</h2>
            <p>{{ venta.observaciones }}</p>
          </div>

          <!-- FIRMAS -->
          <div class="venta-comprobante-firmas">
            <div>
              <span></span>
              <p>Entregado por</p>
            </div>

            <div>
              <span></span>
              <p>Recibido por</p>
            </div>
          </div>

          <footer class="venta-comprobante-footer">
            <p>
              Este comprobante respalda una venta interna registrada en el sistema.
            </p>
            <small>
              Generado el {{ fechaActual }}
            </small>
          </footer>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { nextTick, ref } from 'vue';

defineProps({
  venta: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const comprobanteRef = ref(null);
const fechaActual = new Date().toLocaleString('es-BO');

async function imprimir() {
  await nextTick();

  const contenido = comprobanteRef.value?.outerHTML;

  if (!contenido) {
    window.print();
    return;
  }

  const ventana = window.open('', '_blank', 'width=900,height=700');

  if (!ventana) {
    window.print();
    return;
  }

  ventana.document.open();

  ventana.document.write(`
    <!doctype html>
    <html>
      <head>
        <title>Comprobante de venta</title>
        <style>
          @page {
            size: A4;
            margin: 12mm;
          }

          * {
            box-sizing: border-box;
          }

          body {
            margin: 0;
            padding: 0;
            background: white;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
          }

          .venta-comprobante {
            width: 100%;
            max-width: 186mm;
            margin: 0 auto;
            padding: 0;
            background: white;
            color: #111827;
          }

          .venta-comprobante::before {
            content: '';
            display: block;
            height: 7px;
            margin-bottom: 16px;
            background: linear-gradient(90deg, #064e3b, #16a34a, #86efac);
            border-radius: 999px;
          }

          .venta-comprobante::after {
            display: none !important;
          }

          .venta-comprobante-head {
            display: grid;
            grid-template-columns: 1fr 180px;
            gap: 18px;
            align-items: start;
            padding-bottom: 14px;
            border-bottom: 2px solid #14532d;
          }

          .venta-comprobante-head h1 {
            margin: 0;
            color: #064e3b;
            font-size: 24px;
            font-weight: 900;
          }

          .venta-comprobante-head p {
            margin: 5px 0 4px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
          }

          .venta-comprobante-head small {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
          }

          .venta-comprobante-numero {
            padding: 13px 15px;
            border: 1.5px solid #bbf7d0;
            border-radius: 14px;
            background: #ecfdf5;
            color: #166534;
            text-align: right;
          }

          .venta-comprobante-numero span {
            display: block;
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
          }

          .venta-comprobante-numero strong {
            display: block;
            margin-top: 5px;
            color: #064e3b;
            font-size: 22px;
            font-weight: 900;
          }

          .venta-comprobante-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 9px;
            margin-top: 16px;
          }

          .venta-comprobante-info div {
            padding: 10px 11px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f8fafc;
          }

          .venta-comprobante-info label {
            display: block;
            color: #64748b;
            font-size: 8.5px;
            font-weight: 900;
            text-transform: uppercase;
          }

          .venta-comprobante-info p {
            margin: 4px 0 0;
            color: #111827;
            font-size: 11.5px;
            font-weight: 800;
          }

          .venta-comprobante-section {
            margin-top: 18px;
          }

          .venta-comprobante-section h2,
          .venta-comprobante-observaciones h2 {
            margin: 0 0 9px;
            color: #064e3b;
            font-size: 14px;
            font-weight: 900;
          }

          .venta-comprobante-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
          }

          .venta-comprobante-table th {
            padding: 9px 10px;
            background: #064e3b;
            color: white;
            font-size: 9.5px;
            font-weight: 900;
            text-align: left;
            text-transform: uppercase;
          }

          .venta-comprobante-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e5e7eb;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            vertical-align: top;
          }

          .venta-comprobante-table tbody tr:nth-child(even) td {
            background: #f8fafc;
          }

          .venta-comprobante-table td strong {
            display: block;
            color: #111827;
            font-weight: 900;
          }

          .venta-comprobante-table td small {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: 9.5px;
          }

          .venta-comprobante-totales {
            width: 300px;
            margin: 16px 0 0 auto;
            display: grid;
            gap: 7px;
          }

          .venta-comprobante-totales div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 9px 11px;
            border-radius: 9px;
            background: #f8fafc;
            color: #334155;
            font-size: 11.5px;
            font-weight: 800;
          }

          .venta-comprobante-totales .total {
            padding: 11px;
            background: #047857;
            color: white;
            font-size: 14px;
          }

          .venta-comprobante-observaciones {
            margin-top: 16px;
            padding: 12px;
            border-left: 4px solid #16a34a;
            border-radius: 10px;
            background: #f8fafc;
          }

          .venta-comprobante-observaciones p {
            margin: 0;
            color: #334155;
            font-size: 11.5px;
            font-weight: 700;
          }

          .venta-comprobante-firmas {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 60px;
            margin-top: 42px;
          }

          .venta-comprobante-firmas span {
            display: block;
            border-top: 1.5px solid #111827;
          }

          .venta-comprobante-firmas p {
            margin: 7px 0 0;
            color: #334155;
            font-size: 11px;
            font-weight: 800;
            text-align: center;
          }

          .venta-comprobante-footer {
            margin-top: 22px;
            padding-top: 11px;
            border-top: 1px dashed #cbd5e1;
            color: #64748b;
            text-align: center;
          }

          .venta-comprobante-footer p {
            margin: 0;
            font-size: 10.5px;
            font-weight: 700;
          }

          .venta-comprobante-footer small {
            display: block;
            margin-top: 3px;
            font-size: 9.5px;
          }
        </style>
      </head>

      <body>
        ${contenido}
      </body>
    </html>
  `);

  ventana.document.close();

  setTimeout(() => {
    ventana.focus();
    ventana.print();
    ventana.close();
  }, 500);
}

function close() {
  emit('close');
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(fecha) {
  if (!fecha) {
    return '—';
  }

  return new Date(fecha).toLocaleDateString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

function nombreUsuario(usuario) {
  if (!usuario) {
    return '—';
  }

  return [
    usuario.name,
    usuario.paterno,
    usuario.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Usuario';
}
</script>