<template>
  <div class="reportes-filtros-card">
    <div class="reportes-filtros-head">
      <div>
        <h2>Filtros del reporte</h2>
        <p>
          Ajusta los parámetros antes de generar la vista previa, Excel o PDF.
        </p>
      </div>

      <span class="reportes-filter-badge">
        {{ etiquetaTipo(filtros.tipo) }}
      </span>
    </div>

    <div class="reportes-filtros-grid">
      <div v-if="usaFechas" class="form-group">
        <label>Fecha inicio</label>
        <input
          :value="filtros.fecha_inicio"
          type="date"
          @input="updateField('fecha_inicio', $event.target.value)"
        />
      </div>

      <div v-if="usaFechas" class="form-group">
        <label>Fecha fin</label>
        <input
          :value="filtros.fecha_fin"
          type="date"
          @input="updateField('fecha_fin', $event.target.value)"
        />
      </div>

      <div v-if="usaProducto" class="form-group">
        <label>Producto</label>
        <select
          :value="filtros.id_producto"
          @change="updateField('id_producto', $event.target.value)"
        >
          <option value="">Todos los productos</option>
          <option
            v-for="producto in productos"
            :key="producto.id"
            :value="producto.id"
          >
            {{ producto.nombre_producto }}
          </option>
        </select>
      </div>

      <div v-if="usaCategoria" class="form-group">
        <label>Categoría</label>
        <select
          :value="filtros.categoria"
          @change="updateField('categoria', $event.target.value)"
        >
          <option value="">Todas las categorías</option>
          <option value="Semilla">Semilla</option>
          <option value="Bioinsumo">Bioinsumo</option>
          <option value="Agroquimico">Agroquímico</option>
          <option value="Fertilizante">Fertilizante</option>
          <option value="Equipo">Equipo</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div v-if="usaAlmacen" class="form-group">
        <label>Almacén</label>
        <select
          :value="filtros.id_almacen"
          @change="updateField('id_almacen', $event.target.value)"
        >
          <option value="">Todos los almacenes</option>
          <option
            v-for="almacen in almacenes"
            :key="almacen.id"
            :value="almacen.id"
          >
            {{ almacen.nombre_almacen }}
          </option>
        </select>
      </div>

      <div v-if="usaResponsable" class="form-group">
        <label>Responsable del almacén</label>
        <select
          :value="filtros.id_responsable"
          @change="updateField('id_responsable', $event.target.value)"
        >
          <option value="">Todos los responsables</option>
          <option
            v-for="responsable in responsables"
            :key="responsable.id"
            :value="responsable.id"
          >
            {{ responsable.nombre }}
          </option>
        </select>
      </div>

      <div v-if="usaProductor" class="form-group">
        <label>Productor</label>
        <select
          :value="filtros.id_productor"
          @change="updateField('id_productor', $event.target.value)"
        >
          <option value="">Todos los productores</option>
          <option
            v-for="productor in productores"
            :key="productor.id"
            :value="productor.id"
          >
            {{ productor.nombre_completo }}
          </option>
        </select>
      </div>

      <div v-if="usaEstado" class="form-group">
        <label>Estado</label>
        <select
          :value="filtros.estado"
          @change="updateField('estado', $event.target.value)"
        >
          <option value="">Todos los estados</option>
          <option
            v-for="estado in estadosDisponibles"
            :key="estado"
            :value="estado"
          >
            {{ estado }}
          </option>
        </select>
      </div>

      <div v-if="usaMetodoPago" class="form-group">
        <label>Método de pago</label>
        <select
          :value="filtros.metodo_pago"
          @change="updateField('metodo_pago', $event.target.value)"
        >
          <option value="">Todos los métodos</option>
          <option value="Efectivo">Efectivo</option>
          <option value="Transferencia">Transferencia</option>
          <option value="QR">QR</option>
          <option value="Credito">Crédito</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div v-if="usaTipoMovimiento" class="form-group">
        <label>Tipo de movimiento</label>
        <select
          :value="filtros.tipo_movimiento"
          @change="updateField('tipo_movimiento', $event.target.value)"
        >
          <option value="">Todos</option>
          <option value="Entrada">Entrada</option>
          <option value="Salida">Salida</option>
        </select>
      </div>

      <div v-if="usaMotivo" class="form-group">
        <label>Motivo</label>
        <select
          :value="filtros.motivo"
          @change="updateField('motivo', $event.target.value)"
        >
          <option value="">Todos los motivos</option>
          <option value="Compra">Compra</option>
          <option value="Venta">Venta</option>
          <option value="Devolucion">Devolución</option>
          <option value="Ajuste_entrada">Ajuste entrada</option>
          <option value="Ajuste_salida">Ajuste salida</option>
          <option value="Traslado_entrada">Traslado entrada</option>
          <option value="Traslado_salida">Traslado salida</option>
          <option value="Otro">Otro</option>
        </select>
      </div>

      <div v-if="usaMontos" class="form-group">
        <label>Monto mínimo</label>
        <input
          :value="filtros.monto_min"
          type="number"
          min="0"
          step="0.01"
          placeholder="0.00"
          @input="updateField('monto_min', $event.target.value)"
        />
      </div>

      <div v-if="usaMontos" class="form-group">
        <label>Monto máximo</label>
        <input
          :value="filtros.monto_max"
          type="number"
          min="0"
          step="0.01"
          placeholder="0.00"
          @input="updateField('monto_max', $event.target.value)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  filtros: {
    type: Object,
    required: true,
  },
  productos: {
    type: Array,
    default: () => [],
  },
  almacenes: {
    type: Array,
    default: () => [],
  },
  productores: {
    type: Array,
    default: () => [],
  },
  responsables: {
    type: Array,
    default: () => [],
  },
  estadosDisponibles: {
    type: Array,
    default: () => [],
  },
  usaFechas: Boolean,
  usaProducto: Boolean,
  usaCategoria: Boolean,
  usaAlmacen: Boolean,
  usaResponsable: Boolean,
  usaProductor: Boolean,
  usaEstado: Boolean,
  usaMetodoPago: Boolean,
  usaTipoMovimiento: Boolean,
  usaMotivo: Boolean,
  usaMontos: Boolean,
});

const emit = defineEmits(['update:filtros']);

function updateField(field, value) {
  emit('update:filtros', {
    ...props.filtros,
    [field]: value,
  });
}

function etiquetaTipo(tipo) {
  return {
    inventario: 'Inventario',
    movimientos: 'Movimientos',
    ventas: 'Ventas',
    cotizaciones: 'Cotizaciones',
    alertas_stock: 'Alertas de stock',
  }[tipo] || 'Reporte';
}
</script>