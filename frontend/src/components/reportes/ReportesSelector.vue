<template>
  <div class="reportes-selector">
    <button
      v-for="item in tipos"
      :key="item.value"
      class="reporte-type-card"
      :class="{ active: modelValue === item.value }"
      type="button"
      @click="selectTipo(item.value)"
    >
      <div class="reporte-type-icon" :class="item.color">
        <i :class="item.icon"></i>
      </div>

      <div>
        <strong>{{ item.label }}</strong>
        <span>{{ item.description }}</span>
      </div>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: String,
    default: 'inventario',
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const tipos = [
  {
    value: 'inventario',
    label: 'Inventario actual',
    description: 'Stock por producto y almacén',
    icon: 'ti ti-building-warehouse',
    color: 'green',
  },
  {
    value: 'movimientos',
    label: 'Movimientos',
    description: 'Entradas y salidas de inventario',
    icon: 'ti ti-arrows-transfer-down',
    color: 'blue',
  },
  {
    value: 'ventas',
    label: 'Ventas',
    description: 'Facturación, pagos y totales',
    icon: 'ti ti-receipt',
    color: 'emerald',
  },
  {
    value: 'cotizaciones',
    label: 'Cotizaciones',
    description: 'Propuestas comerciales registradas',
    icon: 'ti ti-file-invoice',
    color: 'amber',
  },
  {
    value: 'alertas_stock',
    label: 'Alertas de stock',
    description: 'Productos bajo mínimo o sin stock',
    icon: 'ti ti-alert-triangle',
    color: 'red',
  },
];

function selectTipo(tipo) {
  emit('update:modelValue', tipo);
  emit('change', tipo);
}
</script>