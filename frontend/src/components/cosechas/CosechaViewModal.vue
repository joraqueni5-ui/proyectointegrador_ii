<template>
  <div class="modal-overlay cosecha-modal-overlay" @click.self="close">
    <div class="modal-card cosecha-view-modal">
      <div class="cosecha-view-header">
        <div class="cosecha-view-icon">
          <i class="ti ti-chart-histogram"></i>
        </div>

        <div class="cosecha-view-main">
          <h3>{{ cosecha?.parcela?.codigo_parcela || 'Cosecha' }}</h3>
          <p>
            {{ cosecha?.cultivo?.variedad_papa || 'Sin variedad especificada' }}
          </p>

          <div class="cosecha-view-badges">
            <span class="b" :class="calidadClass(cosecha?.calidad)">
              {{ cosecha?.calidad || '—' }}
            </span>
            <span class="b bb">
              {{ cosecha?.destino || '—' }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="cosecha-view-body">
        <div class="cosecha-view-section-title">Datos principales</div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-map-2"></i>
          </div>
          <div>
            <label>Parcela</label>
            <p>{{ cosecha?.parcela?.codigo_parcela || '—' }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-user"></i>
          </div>
          <div>
            <label>Productor</label>
            <p>{{ nombreProductor(cosecha?.parcela?.productor) }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-seeding"></i>
          </div>
          <div>
            <label>Variedad / cultivo</label>
            <p>{{ cosecha?.cultivo?.variedad_papa || '—' }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-calendar"></i>
          </div>
          <div>
            <label>Fecha de cosecha</label>
            <p>{{ formatDate(cosecha?.fecha_cosecha) }}</p>
          </div>
        </div>

        <div class="cosecha-view-section-title">Producción</div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-weight"></i>
          </div>
          <div>
            <label>Cantidad</label>
            <p>{{ formatNumber(cosecha?.cantidad_toneladas, 3) }} t</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-trending-up"></i>
          </div>
          <div>
            <label>Rendimiento</label>
            <p>{{ cosecha?.rendimiento_t_ha ? formatNumber(cosecha.rendimiento_t_ha, 3) + ' t/ha' : '—' }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-cash"></i>
          </div>
          <div>
            <label>Precio unitario</label>
            <p>{{ cosecha?.precio_unitario_bs ? 'Bs. ' + formatNumber(cosecha.precio_unitario_bs, 2) : '—' }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-report-money"></i>
          </div>
          <div>
            <label>Ingreso estimado</label>
            <p>{{ cosecha?.ingreso_estimado_bs ? 'Bs. ' + formatNumber(cosecha.ingreso_estimado_bs, 2) : '—' }}</p>
          </div>
        </div>

        <div class="cosecha-view-section-title">Registro</div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-user-check"></i>
          </div>
          <div>
            <label>Registrado por</label>
            <p>{{ usuarioNombre }}</p>
          </div>
        </div>

        <div class="cosecha-detail-card">
          <div class="cosecha-detail-icon">
            <i class="ti ti-notes"></i>
          </div>
          <div>
            <label>Observaciones</label>
            <p>{{ cosecha?.observaciones || '—' }}</p>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  cosecha: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const usuarioNombre = computed(() => {
  const user = props.cosecha?.usuario;
  if (!user) return '—';
  return [user.name, user.paterno].filter(Boolean).join(' ') || '—';
});

function close() {
  emit('close');
}

function nombreProductor(productor) {
  if (!productor) return '—';

  return productor.nombre_completo || [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function calidadClass(calidad) {
  return {
    Primera: 'bg',
    Segunda: 'bb',
    Tercera: 'ba',
    Descarte: 'bgr',
  }[calidad] || 'badge-gray';
}

function formatDate(value) {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatNumber(value, decimals = 2) {
  if (value === null || value === undefined || value === '') return '—';

  return Number(value).toLocaleString('es-BO', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
}
</script>