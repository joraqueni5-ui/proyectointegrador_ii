<template>
  <div class="modal-overlay inspeccion-modal-overlay" @click.self="close">
    <div class="modal-card inspeccion-view-modal">
      <div class="inspeccion-view-header">
        <div class="inspeccion-view-icon">
          <i class="ti ti-clipboard-check"></i>
        </div>

        <div class="inspeccion-view-main">
          <h3>Inspección #{{ inspeccion?.id }}</h3>
          <p>
            Parcela:
            <strong>{{ inspeccion?.parcela?.codigo_parcela || '—' }}</strong>
          </p>

          <div class="inspeccion-view-badges">
            <span class="b" :class="badgeEstado(inspeccion?.estado)">
              {{ limpiarEstado(inspeccion?.estado) }}
            </span>

            <span class="b" :class="inspeccion?.tipo_inspeccion === 'Pre_siembra' ? 'ba' : 'bb'">
              {{ limpiarEstado(inspeccion?.tipo_inspeccion || 'Cultivo') }}
            </span>

            <span v-if="inspeccion?.condicion_climatica" class="b bb">
              {{ inspeccion.condicion_climatica }}
            </span>
          </div>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="inspeccion-view-body">
        <div class="inspeccion-view-section-title">Datos principales</div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-calendar"></i>
          </div>
          <div>
            <label>Fecha de visita</label>
            <p>{{ formatDate(inspeccion?.fecha_visita) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-clock"></i>
          </div>
          <div>
            <label>Hora de visita</label>
            <p>{{ inspeccion?.hora_visita || '—' }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-clipboard-list"></i>
          </div>
          <div>
            <label>Tipo de inspección</label>
            <p>{{ limpiarEstado(inspeccion?.tipo_inspeccion || 'Cultivo') }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-map-2"></i>
          </div>
          <div>
            <label>Parcela</label>
            <p>{{ nombreParcela(inspeccion?.parcela) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-user"></i>
          </div>
          <div>
            <label>Productor</label>
            <p>{{ nombreProductor(inspeccion?.parcela?.productor) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-user-check"></i>
          </div>
          <div>
            <label>Técnico asignado</label>
            <p>{{ nombreUsuario(inspeccion?.usuario) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-user-plus"></i>
          </div>
          <div>
            <label>Programado por</label>
            <p>{{ nombreUsuario(inspeccion?.programado_por || inspeccion?.programadoPor) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-shield-check"></i>
          </div>
          <div>
            <label>Validado por</label>
            <p>{{ nombreUsuario(inspeccion?.validado_por || inspeccion?.validadoPor) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-seeding"></i>
          </div>
          <div>
            <label>Cultivo / variedad</label>
            <p>
              {{
                inspeccion?.tipo_inspeccion === 'Pre_siembra'
                  ? 'No aplica'
                  : inspeccion?.cultivo?.variedad_papa || '—'
              }}
            </p>
          </div>
        </div>

        <div class="inspeccion-view-section-title">Condiciones de campo</div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-plant-2"></i>
          </div>
          <div>
            <label>Etapa fenológica</label>
            <p>
              {{
                inspeccion?.tipo_inspeccion === 'Pre_siembra'
                  ? 'No aplica'
                  : inspeccion?.etapa_fenologica?.nombre ||
                    inspeccion?.etapaFenologica?.nombre ||
                    '—'
              }}
            </p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-cloud"></i>
          </div>
          <div>
            <label>Clima</label>
            <p>{{ inspeccion?.condicion_climatica || '—' }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-temperature"></i>
          </div>
          <div>
            <label>Temperatura</label>
            <p>{{ inspeccion?.temperatura_c != null ? inspeccion.temperatura_c + ' °C' : '—' }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-droplet"></i>
          </div>
          <div>
            <label>Humedad relativa</label>
            <p>{{ inspeccion?.humedad_relativa_pct != null ? inspeccion.humedad_relativa_pct + '%' : '—' }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-alert-triangle"></i>
          </div>
          <div>
            <label>% plantas afectadas</label>
            <p>{{ inspeccion?.pct_plantas_afectadas != null ? inspeccion.pct_plantas_afectadas + '%' : '—' }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card span-2">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-notes"></i>
          </div>
          <div>
            <label>Observaciones</label>
            <p>{{ inspeccion?.observaciones || '—' }}</p>
          </div>
        </div>

        <template v-if="inspeccion?.tipo_inspeccion === 'Pre_siembra'">
          <div class="inspeccion-view-section-title">Datos de pre siembra</div>

          <div class="inspeccion-detail-card">
            <div class="inspeccion-detail-icon">
              <i class="ti ti-shovel"></i>
            </div>
            <div>
              <label>Condición del terreno</label>
              <p>{{ inspeccion?.condicion_terreno || '—' }}</p>
            </div>
          </div>

          <div class="inspeccion-detail-card span-2">
            <div class="inspeccion-detail-icon">
              <i class="ti ti-list-check"></i>
            </div>
            <div>
              <label>Recomendación pre siembra</label>
              <p>{{ inspeccion?.recomendacion_pre_siembra || '—' }}</p>
            </div>
          </div>
        </template>

        <div class="inspeccion-view-section-title">Seguimiento del estado</div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-player-play"></i>
          </div>
          <div>
            <label>Fecha de inicio</label>
            <p>{{ formatDateTime(inspeccion?.fecha_inicio) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-flag-check"></i>
          </div>
          <div>
            <label>Fecha de finalización</label>
            <p>{{ formatDateTime(inspeccion?.fecha_finalizacion) }}</p>
          </div>
        </div>

        <div class="inspeccion-detail-card">
          <div class="inspeccion-detail-icon">
            <i class="ti ti-calendar-check"></i>
          </div>
          <div>
            <label>Fecha de validación</label>
            <p>{{ formatDateTime(inspeccion?.fecha_validacion) }}</p>
          </div>
        </div>

        <template v-if="inspeccion?.estado === 'No_realizada'">
          <div class="inspeccion-detail-card span-2">
            <div class="inspeccion-detail-icon">
              <i class="ti ti-alert-circle"></i>
            </div>
            <div>
              <label>Motivo no realizada</label>
              <p>{{ inspeccion?.motivo_no_realizada || '—' }}</p>
            </div>
          </div>

          <div class="inspeccion-detail-card">
            <div class="inspeccion-detail-icon">
              <i class="ti ti-calendar-time"></i>
            </div>
            <div>
              <label>Fecha reprogramada</label>
              <p>{{ formatDate(inspeccion?.fecha_reprogramada) }}</p>
            </div>
          </div>

          <div class="inspeccion-detail-card span-2">
            <div class="inspeccion-detail-icon">
              <i class="ti ti-notes"></i>
            </div>
            <div>
              <label>Recomendación de reprogramación</label>
              <p>{{ inspeccion?.recomendacion_reprogramacion || '—' }}</p>
            </div>
          </div>
        </template>

        <div class="inspeccion-view-section-title">Diagnósticos CNN</div>

        <div v-if="!diagnosticos.length" class="inspeccion-empty-block span-2">
          <i class="ti ti-microscope-off"></i>
          <span>Sin diagnósticos registrados aún</span>
        </div>

        <div v-else class="inspeccion-mini-table span-2">
          <table class="tbl">
            <thead>
              <tr>
                <th>Foto</th>
                <th>Enfermedad</th>
                <th>Confianza</th>
                <th>Severidad</th>
                <th>Validación</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="d in diagnosticos" :key="d.id">
                <td>
                  <img
                    v-if="d.ruta_fotografia"
                    :src="urlArchivo(d.ruta_fotografia)"
                    class="inspeccion-diagnostico-img"
                    @error="hideImage"
                  />
                  <span v-else class="muted">—</span>
                </td>
                <td>
                  <strong>{{ d.enfermedad?.nombre_comun || 'Sin clasificar' }}</strong>
                </td>
                <td>
                  <span class="b" :class="Number(d.porcentaje_confianza) >= 80 ? 'bg' : 'ba'">
                    {{ d.porcentaje_confianza || 0 }}%
                  </span>
                </td>
                <td>{{ d.nivel_severidad || '—' }}</td>
                <td>
                  <span class="b" :class="badgeValidacion(d.estado_validacion)">
                    {{ d.estado_validacion || 'Pendiente' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
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
import { urlArchivo } from '@/services/api';

const props = defineProps({
  inspeccion: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['close']);

const diagnosticos = computed(() => props.inspeccion?.diagnosticos || []);

function close() {
  emit('close');
}

function hideImage(event) {
  event.target.style.display = 'none';
}

function nombreUsuario(user) {
  if (!user) return '—';
  return [user.name, user.paterno].filter(Boolean).join(' ') || '—';
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

function nombreParcela(parcela) {
  if (!parcela) return '—';

  return parcela.nombre_referencial
    ? `${parcela.codigo_parcela} — ${parcela.nombre_referencial}`
    : parcela.codigo_parcela;
}

function badgeEstado(estado) {
  return {
    Pendiente: 'bgr',
    En_proceso: 'ba',
    Completada: 'bg',
    Validada: 'bb',
    No_realizada: 'br',
  }[estado] || 'badge-gray';
}

function badgeValidacion(estado) {
  return {
    Pendiente: 'bgr',
    Confirmado: 'bg',
    Corregido: 'ba',
    Rechazado: 'br',
  }[estado] || 'bgr';
}

function limpiarEstado(estado) {
  if (!estado) return '—';
  return String(estado).replaceAll('_', ' ');
}

function formatDate(value) {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value).slice(0, 10);
  }

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatDateTime(value) {
  if (!value) return '—';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return String(value).replace('T', ' ').slice(0, 16);
  }

  return date.toLocaleString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>