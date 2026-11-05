<template>
  <article class="insp-card" :class="[`estado-${inspeccion.estado}`, { atrasada }]">
    <header class="insp-head">
      <div class="insp-fecha" :title="fechaLarga">
        <strong>{{ partes ? partes.dia : '—' }}</strong>
        <span>{{ mes }}</span>
      </div>

      <div class="insp-titulo">
        <h4>{{ inspeccion.parcela?.codigo_parcela || 'Sin parcela' }}</h4>
        <p v-if="inspeccion.parcela?.nombre_referencial" class="insp-parcela-nombre">
          {{ inspeccion.parcela.nombre_referencial }}
        </p>

        <p class="insp-cuando" :class="{ atrasada }">
          <i class="ti" :class="atrasada ? 'ti-alert-triangle' : 'ti-clock'"></i>
          {{ cuando }}
          <template v-if="inspeccion.hora_visita"> · {{ hora }}</template>
        </p>
      </div>

      <span class="insp-badge" :class="`badge-${inspeccion.estado}`">{{ estadoTexto }}</span>
    </header>

    <ul class="insp-info">
      <li title="Productor">
        <i class="ti ti-user"></i>
        <span>{{ nombreProductor }}</span>
      </li>

      <li title="Técnico asignado">
        <i class="ti ti-user-check"></i>
        <span>{{ nombreTecnico }}</span>
      </li>

      <li title="Cultivo y etapa">
        <i class="ti ti-plant-2"></i>
        <span v-if="esPreSiembra">Inspección previa a la siembra</span>
        <span v-else>
          {{ inspeccion.cultivo?.variedad_papa || 'Sin cultivo' }}
          <em v-if="etapa"> · {{ etapa }}</em>
        </span>
      </li>
    </ul>

    <div v-if="tieneMedidas" class="insp-medidas">
      <span v-if="inspeccion.condicion_climatica" class="insp-clima">
        <i class="ti ti-cloud"></i>
        {{ inspeccion.condicion_climatica }}
      </span>

      <div v-if="tieneAfectacion" class="insp-afectacion" :title="`${afectadas}% de plantas afectadas`">
        <div class="insp-afectacion-texto">
          <span>Plantas afectadas</span>
          <strong>{{ afectadas }}%</strong>
        </div>
        <div class="insp-barra">
          <div class="insp-barra-relleno" :class="nivelAfectacion" :style="{ width: `${Math.min(afectadas, 100)}%` }"></div>
        </div>
      </div>
    </div>

    <p v-if="inspeccion.estado === 'No_realizada' && inspeccion.motivo_no_realizada" class="insp-nota">
      <i class="ti ti-info-circle"></i>
      <span>
        {{ inspeccion.motivo_no_realizada }}
        <em v-if="inspeccion.fecha_reprogramada"> Reprogramada para el {{ fechaReprogramada }}.</em>
      </span>
    </p>

    <footer class="insp-acciones">
      <button
        v-if="accionPrincipal === 'iniciar'"
        class="btn btn-p insp-principal"
        type="button"
        :disabled="saving"
        @click="$emit('iniciar', inspeccion)"
      >
        <i class="ti ti-player-play"></i>
        Iniciar visita
      </button>

      <router-link
        v-else-if="accionPrincipal === 'diagnosticar'"
        class="btn btn-p insp-principal"
        :to="{ name: 'diagnosticos' }"
      >
        <i class="ti ti-scan"></i>
        Analizar con IA
      </router-link>

      <button
        v-else-if="accionPrincipal === 'completar'"
        class="btn btn-p insp-principal"
        type="button"
        :disabled="saving"
        @click="$emit('completar', inspeccion)"
      >
        <i class="ti ti-circle-check"></i>
        Marcar completada
      </button>

      <button
        v-if="accionPrincipal"
        class="insp-icono"
        type="button"
        title="Ver detalle"
        aria-label="Ver detalle"
        :disabled="saving"
        @click="$emit('ver', inspeccion)"
      >
        <i class="ti ti-eye"></i>
      </button>

      <button v-else class="btn btn-s insp-ver" type="button" :disabled="saving" @click="$emit('ver', inspeccion)">
        <i class="ti ti-eye"></i>
        Ver detalle
      </button>

      <template v-if="puedeGestionar && inspeccion.estado !== 'Validada'">
        <button
          class="insp-icono"
          type="button"
          title="Editar"
          aria-label="Editar inspección"
          :disabled="saving"
          @click="$emit('editar', inspeccion)"
        >
          <i class="ti ti-pencil"></i>
        </button>

        <button
          class="insp-icono"
          type="button"
          title="Cambiar estado"
          aria-label="Cambiar estado"
          :disabled="saving"
          @click="$emit('estado', inspeccion)"
        >
          <i class="ti ti-refresh"></i>
        </button>
      </template>
    </footer>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { diasDesdeHoy, fechaRelativa, mesCorto, partesFecha } from '@/utils/fechas';

const props = defineProps({
  inspeccion: {
    type: Object,
    required: true,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  // Puede crear/editar inspecciones (permiso crear_inspecciones).
  puedeGestionar: {
    type: Boolean,
    default: false,
  },
  // Puede registrar diagnósticos (permiso crear_diagnosticos).
  puedeDiagnosticar: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['ver', 'editar', 'estado', 'iniciar', 'completar']);

const ESTADOS = {
  Pendiente: 'Pendiente',
  En_proceso: 'En proceso',
  Completada: 'Completada',
  Validada: 'Validada',
  No_realizada: 'No realizada',
};

const partes = computed(() => partesFecha(props.inspeccion.fecha_visita));
const mes = computed(() => mesCorto(props.inspeccion.fecha_visita));
const estadoTexto = computed(() => ESTADOS[props.inspeccion.estado] || props.inspeccion.estado || '—');

const atrasada = computed(() => {
  const dias = diasDesdeHoy(props.inspeccion.fecha_visita);

  return props.inspeccion.estado === 'Pendiente' && dias !== null && dias < 0;
});

const cuando = computed(() => {
  const relativa = fechaRelativa(props.inspeccion.fecha_visita);

  return atrasada.value ? `${relativa} · sin iniciar` : relativa;
});

const fechaLarga = computed(() => {
  const p = partes.value;

  return p ? `${p.dia} de ${mes.value} de ${p.anio}` : '';
});

const fechaReprogramada = computed(() => {
  const p = partesFecha(props.inspeccion.fecha_reprogramada);

  return p ? `${p.dia} ${mesCorto(props.inspeccion.fecha_reprogramada)} ${p.anio}` : '';
});

const hora = computed(() => String(props.inspeccion.hora_visita || '').slice(0, 5));
const esPreSiembra = computed(() => props.inspeccion.tipo_inspeccion === 'Pre_siembra');
const etapa = computed(() => props.inspeccion.etapa_fenologica?.nombre || props.inspeccion.etapaFenologica?.nombre || '');

const nombreProductor = computed(() => {
  const p = props.inspeccion.parcela?.productor;

  if (!p) return 'Sin productor';

  return (
    p.nombre_completo ||
    [p.nombres, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(' ') ||
    'Sin productor'
  );
});

const nombreTecnico = computed(() => {
  const u = props.inspeccion.usuario;

  return [u?.name, u?.paterno].filter(Boolean).join(' ') || 'Sin técnico asignado';
});

const afectadas = computed(() => Number(props.inspeccion.pct_plantas_afectadas));
const tieneAfectacion = computed(
  () => props.inspeccion.pct_plantas_afectadas !== null && props.inspeccion.pct_plantas_afectadas !== undefined && !Number.isNaN(afectadas.value)
);
const tieneMedidas = computed(() => tieneAfectacion.value || Boolean(props.inspeccion.condicion_climatica));

const nivelAfectacion = computed(() => {
  if (afectadas.value > 50) return 'alto';
  if (afectadas.value > 25) return 'medio';

  return 'bajo';
});

// Siguiente paso natural según el estado, solo si el usuario puede hacerlo.
const accionPrincipal = computed(() => {
  if (!props.puedeGestionar) return null;

  if (props.inspeccion.estado === 'Pendiente') return 'iniciar';

  if (props.inspeccion.estado === 'En_proceso') {
    return !esPreSiembra.value && props.puedeDiagnosticar ? 'diagnosticar' : 'completar';
  }

  return null;
});
</script>

<style scoped>
.insp-card {
  --acento: var(--gris-300);

  position: relative;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 18px 16px 22px;
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  background: white;
  box-shadow: var(--shadow-sm);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.insp-card::before {
  content: '';
  position: absolute;
  inset: 12px auto 12px 0;
  width: 4px;
  border-radius: 0 4px 4px 0;
  background: var(--acento);
}

.insp-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}

.insp-card.estado-Pendiente { --acento: var(--amarillo-600); }
.insp-card.estado-En_proceso { --acento: var(--azul-600); }
.insp-card.estado-Completada { --acento: var(--verde-500); }
.insp-card.estado-Validada { --acento: #7c3aed; }
.insp-card.estado-No_realizada { --acento: var(--rojo-600); }
.insp-card.atrasada { --acento: var(--rojo-600); }

.insp-head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.insp-badge {
  flex: none;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--gris-200);
  color: var(--gris-700);
  font-size: 11.5px;
  font-weight: 900;
  white-space: nowrap;
}

.badge-Pendiente { background: var(--amarillo-100); color: #92400e; }
.badge-En_proceso { background: var(--azul-100); color: #1e40af; }
.badge-Completada { background: var(--verde-100); color: var(--verde-700); }
.badge-Validada { background: #f3e8ff; color: #6b21a8; }
.badge-No_realizada { background: var(--rojo-100); color: var(--rojo-600); }

.insp-fecha {
  flex: none;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 56px;
  border-radius: 12px;
  background: var(--gris-100);
  line-height: 1.1;
}

.insp-fecha strong {
  color: var(--gris-950);
  font-size: 20px;
  font-weight: 900;
}

.insp-fecha span {
  color: var(--gris-500);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.insp-titulo {
  flex: 1;
  min-width: 0;
}

.insp-titulo h4 {
  margin: 0;
  overflow: hidden;
  color: var(--verde-700);
  font-family: 'JetBrains Mono', monospace;
  font-size: 15px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.insp-parcela-nombre {
  margin: 2px 0 0;
  overflow: hidden;
  color: var(--gris-700);
  font-size: 12.5px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.insp-cuando {
  margin: 3px 0 0;
  color: var(--gris-500);
  font-size: 12.5px;
  font-weight: 700;
}

.insp-cuando.atrasada {
  color: var(--rojo-600);
}

.insp-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.insp-info li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--gris-700);
  font-size: 13px;
  font-weight: 600;
}

.insp-info i {
  flex: none;
  width: 18px;
  color: var(--gris-400);
  font-size: 16px;
  text-align: center;
}

.insp-info span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.insp-info em {
  color: var(--gris-500);
  font-style: normal;
}

.insp-medidas {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--gris-50);
}

.insp-clima {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--gris-600);
  font-size: 12.5px;
  font-weight: 700;
  white-space: nowrap;
}

.insp-afectacion {
  flex: 1;
  min-width: 0;
}

.insp-afectacion-texto {
  display: flex;
  justify-content: space-between;
  margin-bottom: 5px;
  color: var(--gris-500);
  font-size: 11.5px;
  font-weight: 700;
}

.insp-afectacion-texto strong {
  color: var(--gris-800);
}

.insp-barra {
  height: 6px;
  overflow: hidden;
  border-radius: 99px;
  background: var(--gris-200);
}

.insp-barra-relleno {
  height: 100%;
  border-radius: 99px;
  transition: width 0.3s ease;
}

.insp-barra-relleno.bajo { background: var(--verde-500); }
.insp-barra-relleno.medio { background: var(--naranja-600); }
.insp-barra-relleno.alto { background: var(--rojo-600); }

.insp-nota {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--rojo-100);
  color: var(--gris-700);
  font-size: 12.5px;
  font-weight: 600;
}

.insp-nota i {
  flex: none;
  color: var(--rojo-600);
  font-size: 16px;
}

.insp-nota em {
  font-style: normal;
  font-weight: 800;
}

.insp-acciones {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: auto;
  padding-top: 4px;
}

.insp-principal {
  flex: 1;
  justify-content: center;
  white-space: nowrap;
}

.insp-ver {
  white-space: nowrap;
}

.insp-icono {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: none;
  width: 36px;
  height: 36px;
  border: 1px solid var(--gris-200);
  border-radius: 10px;
  background: white;
  color: var(--gris-600);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.insp-icono:hover:not(:disabled) {
  background: var(--verde-100);
  color: var(--verde-700);
}

.insp-icono:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.insp-icono i {
  font-size: 16px;
}

/* Si no hay acción principal, "Ver detalle" ocupa el ancho disponible. */
.insp-acciones > .insp-ver:first-child {
  flex: 1;
  justify-content: center;
}
</style>
