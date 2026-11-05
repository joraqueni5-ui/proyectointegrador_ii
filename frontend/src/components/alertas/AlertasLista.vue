<template>
  <div class="alertas-lista-wrap">
    <!-- CARGANDO -->
    <div v-if="loading" class="alertas-loading-card">
      <div class="spinner"></div>

      <span> Cargando alertas diagnósticas... </span>
    </div>

    <!-- SIN ALERTAS -->
    <div v-else-if="alertas.length === 0" class="alertas-empty-card">
      <i class="ti ti-bell-off"></i>

      <strong> No hay alertas registradas </strong>

      <span> No existen alertas con los filtros seleccionados. </span>
    </div>

    <!-- LISTADO -->
    <div v-else class="alertas-lista">
      <article
        v-for="alerta in alertas"
        :key="alerta.id"
        class="alerta-item"
        :class="[
          nivelClass(alerta.nivel_riesgo),
          {
            unread: !alerta.leida,
          },
        ]"
        @click="$emit('select', alerta)"
      >
        <!-- ICONO -->
        <div class="alerta-item-icon">
          <i :class="nivelIcon(alerta.nivel_riesgo)"></i>
        </div>

        <!-- CONTENIDO -->
        <div class="alerta-item-content">
          <!-- CABECERA -->
          <div class="alerta-item-head">
            <strong>
              {{ alerta.titulo || 'Alerta diagnóstica' }}
            </strong>

            <div class="alerta-item-badges">
              <span class="b" :class="nivelBadge(alerta.nivel_riesgo)">
                {{ textoNivel(alerta.nivel_riesgo) }}
              </span>

              <span v-if="!alerta.leida" class="alerta-unread-dot" title="Sin leer"></span>
            </div>
          </div>

          <!-- MENSAJE -->
          <p>
            {{ alerta.mensaje || 'Sin mensaje registrado.' }}
          </p>

          <!-- INFORMACIÓN -->
          <div class="alerta-item-meta">
            <span>
              <i class="ti ti-calendar"></i>

              {{ formatFecha(alerta.fecha_emision) }}
            </span>

            <span
              v-if="alerta.fecha_limite_accion"
              :class="{
                danger: vencida(alerta),
              }"
            >
              <i class="ti ti-alarm"></i>

              Límite:
              {{ formatFecha(alerta.fecha_limite_accion) }}
            </span>

            <span class="b" :class="estadoBadge(alerta.estado)">
              {{ alerta.estado || 'Sin estado' }}
            </span>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup>
defineProps({
  alertas: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['select']);

/* ============================================================
   FECHAS
============================================================ */

function formatFecha(fecha) {
  if (!fecha) {
    return '—';
  }

  const s = String(fecha).substring(0, 10);

  const [y, m, d] = s.split('-');

  if (!y || !m || !d) {
    return fecha;
  }

  return `${d}/${m}/${y}`;
}

/* ============================================================
   ALERTA VENCIDA
============================================================ */

function vencida(alerta) {
  if (!alerta?.fecha_limite_accion) {
    return false;
  }

  return new Date(alerta.fecha_limite_accion) < new Date() && alerta.estado === 'Activa';
}

/* ============================================================
   NIVEL
============================================================ */

function textoNivel(nivel) {
  return (
    {
      Critico: 'Crítico',

      Crítico: 'Crítico',
      Alto: 'Alto',
      Medio: 'Medio',
      Bajo: 'Bajo',
    }[nivel] ||
    nivel ||
    'Sin nivel'
  );
}

function nivelClass(nivel) {
  return (
    {
      Critico: 'critico',

      Crítico: 'critico',
      Alto: 'alto',
      Medio: 'medio',
      Bajo: 'bajo',
    }[nivel] || 'bajo'
  );
}

function nivelIcon(nivel) {
  return (
    {
      Critico: 'ti ti-alert-octagon',

      Crítico: 'ti ti-alert-octagon',
      Alto: 'ti ti-alert-triangle',
      Medio: 'ti ti-alert-circle',
      Bajo: 'ti ti-info-circle',
    }[nivel] || 'ti ti-bell'
  );
}

function nivelBadge(nivel) {
  return (
    {
      Critico: 'br',

      Crítico: 'br',
      Alto: 'br',
      Medio: 'ba',
      Bajo: 'bb',
    }[nivel] || 'bgr'
  );
}

/* ============================================================
   ESTADO GENERAL
============================================================ */

function estadoBadge(estado) {
  return (
    {
      Activa: 'br',
      Atendida: 'bg',
      Ignorada: 'bgr',
      Vencida: 'ba',
    }[estado] || 'bgr'
  );
}
</script>
