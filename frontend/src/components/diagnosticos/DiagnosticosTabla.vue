<template>
  <div class="diagnosticos-table-wrap">
    <table class="diagnosticos-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Fotografía</th>
          <th>Inspección</th>
          <th>Parcela</th>
          <th>Enfermedad</th>
          <th>Confianza</th>
          <th>Severidad</th>
          <th>Validación</th>
          <th class="diagnosticos-right">Acciones</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="diagnostico in diagnosticos"
          :key="diagnostico.id"
          @click="$emit('select', diagnostico)"
        >
          <td>
            <span class="diagnostico-row-id">#{{ diagnostico.id }}</span>
          </td>

          <td>
            <div class="diagnostico-photo-cell">
              <img
                :src="fotoUrl(diagnostico.ruta_fotografia)"
                alt="Fotografía diagnóstico"
                @error="onImageError"
              />
            </div>
          </td>

          <td>
            <div class="diagnostico-inspeccion-cell">
              <strong>#{{ diagnostico.inspeccion?.id || '—' }}</strong>
              <small>{{ formatDate(diagnostico.inspeccion?.fecha_visita) }}</small>
            </div>
          </td>

          <td>
            <div class="diagnostico-parcela-cell">
              <strong>
                {{ diagnostico.inspeccion?.parcela?.codigo_parcela || '—' }}
              </strong>
              <small>
                {{ diagnostico.inspeccion?.parcela?.nombre_referencial || 'Sin referencia' }}
              </small>
            </div>
          </td>

          <td>
            <div class="diagnostico-enfermedad-cell">
              <strong v-if="diagnostico.enfermedad">
                {{ diagnostico.enfermedad.nombre_comun }}
              </strong>

              <span v-else class="b bgr">
                Sin clasificar
              </span>

              <small v-if="diagnostico.enfermedad?.nivel_riesgo_base">
                Riesgo {{ diagnostico.enfermedad.nivel_riesgo_base }}
              </small>
            </div>
          </td>

          <td>
            <div class="diagnostico-confidence-mini">
              <span
                class="b"
                :class="badgeConfianza(diagnostico.porcentaje_confianza)"
              >
                {{ numero(diagnostico.porcentaje_confianza) }}%
              </span>

              <div class="diagnostico-confidence-mini-bar">
                <div
                  :class="confianzaClass(diagnostico.porcentaje_confianza)"
                  :style="{ width: `${Math.min(Number(diagnostico.porcentaje_confianza || 0), 100)}%` }"
                ></div>
              </div>
            </div>
          </td>

          <td>
            <span
              v-if="diagnostico.nivel_severidad"
              class="b"
              :class="badgeSeveridad(diagnostico.nivel_severidad)"
            >
              {{ textoSeveridad(diagnostico.nivel_severidad) }}
            </span>

            <span v-else class="diagnostico-muted">—</span>
          </td>

          <td>
            <span
              class="b"
              :class="badgeValidacion(diagnostico.estado_validacion)"
            >
              {{ diagnostico.estado_validacion || 'Pendiente' }}
            </span>
          </td>

          <td>
            <div class="diagnosticos-actions" @click.stop>
              <button
                class="diagnostico-action view"
                type="button"
                title="Ver detalle"
                @click="$emit('view', diagnostico)"
              >
                <i class="ti ti-eye"></i>
              </button>

              <button
                v-if="diagnostico.estado_validacion === 'Pendiente' && puedeValidar"
                class="diagnostico-action validate"
                type="button"
                title="Validar diagnóstico"
                @click="$emit('validar', diagnostico)"
              >
                <i class="ti ti-shield-check"></i>
              </button>
            </div>
          </td>
        </tr>

        <tr v-if="diagnosticos.length === 0">
          <td colspan="9" class="diagnosticos-empty">
            <i class="ti ti-microscope"></i>
            <span>No se encontraron diagnósticos registrados.</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { urlArchivo } from '@/services/api';

defineProps({
  diagnosticos: {
    type: Array,
    default: () => [],
  },
  puedeValidar: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['view', 'validar', 'select']);

function fotoUrl(ruta) {
  return ruta ? urlArchivo(ruta) : placeholderImage();
}

function onImageError(event) {
  event.target.src = placeholderImage();
}

function placeholderImage() {
  return 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="80" height="80" fill="%23f3f4f6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%239ca3af" font-size="10">Sin foto</text></svg>';
}

function numero(valor) {
  return Number(valor || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
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

function confianzaClass(valor) {
  const numeroValor = Number(valor || 0);

  if (numeroValor >= 85) return 'alta';
  if (numeroValor >= 70) return 'media';

  return 'baja';
}

function badgeConfianza(valor) {
  const numeroValor = Number(valor || 0);

  if (numeroValor >= 85) return 'bg';
  if (numeroValor >= 70) return 'ba';

  return 'br';
}

function badgeSeveridad(severidad) {
  return {
    Sin_afeccion: 'bg',
    Leve: 'bb',
    Moderada: 'ba',
    Severa: 'br',
    Muy_severa: 'br',
  }[severidad] || 'bgr';
}

function badgeValidacion(estado) {
  return {
    Pendiente: 'bgr',
    Confirmado: 'bg',
    Corregido: 'ba',
    Rechazado: 'br',
  }[estado] || 'bgr';
}

function textoSeveridad(severidad) {
  if (!severidad) {
    return '—';
  }

  return String(severidad).replaceAll('_', ' ');
}
</script>