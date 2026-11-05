<template>
  <div class="modal-overlay cultivo-modal-overlay" @click.self="close">
    <div class="modal-card cultivo-view-modal">
      <div class="cultivo-view-header">
        <div class="cultivo-view-icon">
          <i class="ti ti-seeding"></i>
        </div>

        <div class="cultivo-view-main">
          <h3>{{ cultivo?.variedad_papa || 'Cultivo' }}</h3>
          <p>
            Parcela:
            <strong>{{ cultivo?.parcela?.codigo_parcela || '—' }}</strong>
          </p>

          <div class="cultivo-view-badges">
            <span class="b" :class="badgeEstado(cultivo?.estado)">
              {{ limpiarEstado(cultivo?.estado) }}
            </span>

            <span v-if="cultivo?.etapa_fenologica?.nombre" class="b bb">
              {{ cultivo.etapa_fenologica.nombre }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="cultivo-view-body">
        <div class="cultivo-view-section-title">Datos del cultivo</div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-map-2"></i>
          </div>
          <div>
            <label>Parcela</label>
            <p>{{ cultivo?.parcela?.codigo_parcela || '—' }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-user"></i>
          </div>
          <div>
            <label>Productor</label>
            <p>{{ nombreProductor(cultivo?.parcela?.productor) }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-calendar"></i>
          </div>
          <div>
            <label>Fecha de siembra</label>
            <p>{{ formatDate(cultivo?.fecha_siembra) }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-calendar-event"></i>
          </div>
          <div>
            <label>Fecha estimada de cosecha</label>
            <p>{{ formatDate(cultivo?.fecha_estimada_cosecha) }}</p>
          </div>
        </div>

        <div class="cultivo-view-section-title">Semilla y etapa</div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-weight"></i>
          </div>
          <div>
            <label>Cantidad de semilla</label>
            <p>{{ cultivo?.cantidad_semilla_kg ? cultivo.cantidad_semilla_kg + ' kg' : '—' }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-package"></i>
          </div>
          <div>
            <label>Procedencia</label>
            <p>{{ cultivo?.procedencia_semilla || '—' }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-plant-2"></i>
          </div>
          <div>
            <label>Etapa fenológica</label>
            <p>{{ cultivo?.etapa_fenologica?.nombre || '—' }}</p>
          </div>
        </div>

        <div class="cultivo-detail-card">
          <div class="cultivo-detail-icon">
            <i class="ti ti-notes"></i>
          </div>
          <div>
            <label>Observaciones</label>
            <p>{{ cultivo?.observaciones || '—' }}</p>
          </div>
        </div>

        <div class="cultivo-view-section-title">Últimas inspecciones</div>

        <div v-if="!inspecciones.length" class="cultivo-empty-block span-2">
          <i class="ti ti-clipboard-x"></i>
          <span>Sin inspecciones registradas</span>
        </div>

        <div v-else class="cultivo-mini-table span-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="i in inspecciones" :key="i.id">
                <td>{{ formatDate(i.fecha_visita) }}</td>
                <td>
                  <span class="b bgr">{{ limpiarEstado(i.estado) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="cultivo-view-section-title">Cosechas</div>

        <div v-if="!cosechas.length" class="cultivo-empty-block span-2">
          <i class="ti ti-basket-off"></i>
          <span>Sin cosechas registradas</span>
        </div>

        <div v-else class="cultivo-mini-table span-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Cantidad</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in cosechas" :key="c.id">
                <td>{{ formatDate(c.fecha_cosecha) }}</td>
                <td>{{ c.cantidad_kg ? c.cantidad_kg + ' kg' : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  cultivo: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const inspecciones = computed(() => props.cultivo?.inspecciones || []);
const cosechas = computed(() => props.cultivo?.cosechas || []);

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

function badgeEstado(estado) {
  return {
    Activo: 'bg',
    Cosechado: 'bb',
    Perdido: 'bgr',
    Abandonado: 'ba',
  }[estado] || 'badge-gray';
}

function limpiarEstado(estado) {
  if (!estado) return '—';
  return String(estado).replaceAll('_', ' ');
}

function formatDate(dateStr) {
  if (!dateStr) return '—';

  const date = new Date(dateStr);

  if (Number.isNaN(date.getTime())) return dateStr;

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}
</script>