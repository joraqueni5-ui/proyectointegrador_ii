<template>
  <div class="alertas-filtros-card">
    <!-- CABECERA -->
    <div class="alertas-filtros-head">
      <div>
        <h2>Filtros</h2>

        <p>Filtra las alertas por prioridad, estado general y etapa de seguimiento.</p>
      </div>

      <button type="button" class="btn btn-s" :disabled="loading" @click="$emit('actualizar')">
        <i class="ti ti-refresh"></i>

        Actualizar
      </button>
    </div>

    <!-- CUERPO -->
    <div class="alertas-filtros-body">
      <!-- NIVEL DE RIESGO -->
      <div class="alertas-filter-group">
        <label for="nivelRiesgo"> Nivel de riesgo </label>

        <select
          id="nivelRiesgo"
          :value="nivel"
          :disabled="loading"
          @change="$emit('update:nivel', $event.target.value)"
        >
          <option value="">Todos</option>

          <option value="Critico">Crítico</option>

          <option value="Alto">Alto</option>

          <option value="Medio">Medio</option>

          <option value="Bajo">Bajo</option>
        </select>
      </div>

      <!-- ESTADO GENERAL -->
      <div class="alertas-filter-group">
        <label for="estadoGeneral"> Estado general </label>

        <select
          id="estadoGeneral"
          :value="estado"
          :disabled="loading"
          @change="$emit('update:estado', $event.target.value)"
        >
          <option value="">Todos</option>

          <option value="Activa">Activa</option>

          <option value="Atendida">Atendida</option>

          <option value="Ignorada">Ignorada</option>

          <option value="Vencida">Vencida</option>
        </select>
      </div>

      <!-- ESTADO DE SEGUIMIENTO -->
      <div class="alertas-filter-group">
        <label for="estadoSeguimiento"> Estado de seguimiento </label>

        <select
          id="estadoSeguimiento"
          :value="estadoSeguimiento"
          :disabled="loading"
          @change="$emit('update:estadoSeguimiento', $event.target.value)"
        >
          <option value="">Todos</option>

          <option value="Nueva">Nueva</option>

          <option value="En_revision">En revisión</option>

          <option value="Planificada">Planificada</option>

          <option value="En_tratamiento">En tratamiento</option>

          <option value="En_seguimiento">En seguimiento</option>

          <option value="Resuelta">Resuelta</option>

          <option value="Escalada">Escalada</option>

          <option value="Reincidente">Reincidente</option>

          <option value="Descartada">Descartada</option>
        </select>
      </div>

      <!-- SOLO MIS ALERTAS -->
      <label class="alertas-check-filter">
        <input
          type="checkbox"
          :checked="soloMias"
          :disabled="loading"
          @change="$emit('update:soloMias', $event.target.checked)"
        />

        <span>
          <i class="ti ti-user"></i>

          Solo mis alertas
        </span>
      </label>

      <!-- BOTONES -->
      <button type="button" class="btn btn-p" :disabled="loading" @click="$emit('buscar')">
        <i class="ti ti-search"></i>

        Buscar
      </button>

      <button type="button" class="btn btn-s" :disabled="loading" @click="$emit('limpiar')">
        <i class="ti ti-eraser"></i>

        Limpiar
      </button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  nivel: {
    type: String,
    default: '',
  },
  estado: {
    type: String,
    default: '',
  },
  estadoSeguimiento: {
    type: String,
    default: '',
  },
  soloMias: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits([
  'update:nivel',
  'update:estado',
  'update:estadoSeguimiento',
  'update:soloMias',
  'buscar',
  'limpiar',
  'actualizar',
]);
</script>
