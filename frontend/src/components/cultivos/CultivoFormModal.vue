<template>
  <div class="modal-overlay cultivo-modal-overlay" @click.self="close">
    <div class="modal-card cultivo-form-modal">
      <div class="modal-header cultivo-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nuevo cultivo' : 'Editar cultivo' }}
          </div>
          <p class="cultivo-modal-subtitle">
            Registra la variedad sembrada en una parcela y su información productiva.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div class="cultivo-form-section">
          <div class="cultivo-section-title">
            <i class="ti ti-map-2"></i>
            Parcela y variedad
          </div>

          <div class="cultivo-form-grid">
            <div class="form-group span-2">
              <label>Parcela *</label>
              <select v-model="form.id_parcela">
                <option value="">Selecciona una parcela</option>
                <option v-for="p in parcelas" :key="p.id" :value="p.id">
                  {{ p.codigo_parcela }}
                  {{ p.nombre_referencial ? `— ${p.nombre_referencial}` : '' }}
                  {{ p.productor ? ` | ${nombreProductor(p.productor)}` : '' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>Variedad de papa *</label>
              <input
                v-model.trim="form.variedad_papa"
                maxlength="120"
                placeholder="Ej. Huaycha, Imilla, Desiree"
              />
            </div>

            <div class="form-group">
              <label>Fecha de siembra *</label>
              <input
                v-model="form.fecha_siembra"
                type="date"
                :max="hoy"
              />
              <small class="field-help">No puede ser futura.</small>
            </div>

            <div class="form-group">
              <label>Fecha estimada de cosecha</label>
              <input
                v-model="form.fecha_estimada_cosecha"
                type="date"
                :min="form.fecha_siembra || ''"
              />
            </div>

          </div>
        </div>

        <div class="cultivo-form-section">
          <div class="cultivo-section-title">
            <i class="ti ti-seeding"></i>
            Semilla y etapa fenológica
          </div>

          <div class="cultivo-form-grid">
            <div class="form-group">
              <label>Cantidad de semilla (kg)</label>
              <input
                v-model="form.cantidad_semilla_kg"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="Ej. 50"
              />
            </div>

            <div class="form-group">
              <label>Procedencia de semilla</label>
              <input
                v-model.trim="form.procedencia_semilla"
                maxlength="150"
                placeholder="Ej. Propia, certificada, comunidad"
              />
            </div>

            <div class="form-group">
              <label>Etapa fenológica</label>
              <select v-model="form.id_etapa_fenologica">
                <option value="">Sin etapa asignada</option>
                <option v-for="e in etapas" :key="e.id" :value="e.id">
                  {{ e.nombre }}{{ e.codigo ? ` (${e.codigo})` : '' }}
                </option>
              </select>
              <small v-if="!etapas.length" class="field-help">
                Puedes dejarlo vacío por ahora.
              </small>
            </div>

            <div class="form-group span-3">
              <label>Observaciones</label>
              <textarea
                v-model.trim="form.observaciones"
                placeholder="Notas sobre semilla, siembra, condiciones del cultivo, etc."
              ></textarea>
            </div>
          </div>
        </div>

        <div v-if="mensajeError" class="cultivo-form-error">
          <i class="ti ti-alert-circle"></i>
          <span>{{ mensajeError }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear cultivo' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue';

const props = defineProps({
  mode: {
    type: String,
    default: 'create',
  },
  cultivo: {
    type: Object,
    default: null,
  },
  parcelas: {
    type: Array,
    default: () => [],
  },
  etapas: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
  error: {
  type: String,
  default: '',
},
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const form = reactive(defaultForm());

const hoy = computed(() => new Date().toISOString().split('T')[0]);

const mensajeError = computed(() => {
  return localError.value || props.error || '';
});

watch(
  () => [props.mode, props.cultivo, props.parcelas],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    id_parcela: '',
    variedad_papa: '',
    fecha_siembra: '',
    cantidad_semilla_kg: '',
    procedencia_semilla: '',
    id_etapa_fenologica: '',
    fecha_estimada_cosecha: '',
    observaciones: '',
    estado: 'Activo',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.cultivo) {
    form.id_parcela = props.cultivo.id_parcela || props.cultivo.parcela?.id || '';
    form.variedad_papa = props.cultivo.variedad_papa || '';
    form.fecha_siembra = formatInputDate(props.cultivo.fecha_siembra);
    form.cantidad_semilla_kg = props.cultivo.cantidad_semilla_kg ?? '';
    form.procedencia_semilla = props.cultivo.procedencia_semilla || '';
    form.id_etapa_fenologica = props.cultivo.id_etapa_fenologica || props.cultivo.etapa_fenologica?.id || '';
    form.fecha_estimada_cosecha = formatInputDate(props.cultivo.fecha_estimada_cosecha);
    form.observaciones = props.cultivo.observaciones || '';
    form.estado = props.cultivo.estado || 'Activo';
    return;
  }

  Object.assign(form, defaultForm());
}

function submit() {
  localError.value = '';

  if (!form.id_parcela) {
    return showError('Debe seleccionar una parcela.');
  }

  if (!form.variedad_papa.trim()) {
    return showError('La variedad de papa es obligatoria.');
  }

  if (!form.fecha_siembra) {
    return showError('La fecha de siembra es obligatoria.');
  }

  if (form.fecha_siembra > hoy.value) {
    return showError('La fecha de siembra no puede ser futura.');
  }

  if (
    form.fecha_estimada_cosecha &&
    form.fecha_siembra &&
    form.fecha_estimada_cosecha < form.fecha_siembra
  ) {
    return showError('La fecha estimada de cosecha debe ser igual o posterior a la fecha de siembra.');
  }

  if (
    form.cantidad_semilla_kg !== '' &&
    form.cantidad_semilla_kg !== null &&
    Number(form.cantidad_semilla_kg) <= 0
  ) {
    return showError('La cantidad de semilla debe ser mayor a 0.');
  }

  const payload = {
    id_parcela: form.id_parcela,
    variedad_papa: form.variedad_papa.trim(),
    fecha_siembra: form.fecha_siembra,
    cantidad_semilla_kg:
      form.cantidad_semilla_kg !== '' && form.cantidad_semilla_kg !== null
        ? Number(form.cantidad_semilla_kg)
        : null,
    procedencia_semilla: form.procedencia_semilla || null,
    id_etapa_fenologica: form.id_etapa_fenologica || null,
    fecha_estimada_cosecha: form.fecha_estimada_cosecha || null,
    observaciones: form.observaciones || null,
    // El estado de un cultivo existente se cambia desde "Cambiar estado".
    ...(props.mode === 'create' ? { estado: 'Activo' } : {}),
  };

  emit('save', payload);
}

function formatInputDate(value) {
  if (!value) return '';
  return String(value).split('T')[0];
}

function nombreProductor(productor) {
  if (!productor) return 'Sin productor';

  return productor.nombre_completo || [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Sin productor';
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>