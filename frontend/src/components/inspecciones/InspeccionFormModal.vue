<template>
  <div class="modal-overlay inspeccion-modal-overlay" @click.self="close">
    <div class="modal-card inspeccion-form-modal">
      <div class="modal-header inspeccion-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nueva inspección' : 'Editar inspección' }}
          </div>
          <p class="inspeccion-modal-subtitle">
            Registra datos de campo sobre la parcela, cultivo, clima y afectación observada.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="inspeccion-form-section">
          <div class="inspeccion-section-title">
            <i class="ti ti-map-2"></i>
            Parcela y cultivo
          </div>

          <div class="inspeccion-form-grid">
            <div class="form-group">
              <label>Tipo de inspección *</label>
              <select v-model="form.tipo_inspeccion" @change="onTipoInspeccionChange">
                <option value="Cultivo">Cultivo</option>
                <option value="Pre_siembra">Pre siembra</option>
              </select>
            </div>

            <div class="form-group">
              <label>Técnico asignado *</label>
              <select v-model="form.id_usuario">
                <option value="">Selecciona un técnico</option>
                <option v-for="t in tecnicos" :key="t.id" :value="t.id">
                  {{ nombreUsuario(t) }}
                  {{ t.rol?.nombre ? `— ${t.rol.nombre}` : '' }}
                </option>
              </select>
            </div>

            <div class="form-group span-2">
              <label>Parcela *</label>
              <select v-model="form.id_parcela" @change="onParcelaChange">
                <option value="">Selecciona una parcela</option>
                <option v-for="p in parcelasFiltradas" :key="p.id" :value="p.id">
                  {{ p.codigo_parcela }}
                  {{ p.nombre_referencial ? `— ${p.nombre_referencial}` : '' }}
                  {{ p.productor ? ` | ${nombreProductor(p.productor)}` : '' }}
                </option>
              </select>
              <small class="field-help">
                {{ mensajeFiltroParcelas }}
              </small>
            </div>

            <div v-if="form.tipo_inspeccion === 'Cultivo'" class="form-group">
              <label>Cultivo activo *</label>
              <select v-model="form.id_cultivo">
                <option value="">Selecciona un cultivo activo</option>
                <option v-for="c in cultivosFiltrados" :key="c.id" :value="c.id">
                  {{ c.variedad_papa }} — {{ formatDate(c.fecha_siembra) }}
                  {{ c.estado && c.estado !== 'Activo' ? ` (${c.estado})` : '' }}
                </option>
              </select>
              <small class="field-help">
                Para nuevas inspecciones solo se muestran cultivos activos de la parcela seleccionada.
              </small>
            </div>

            <div class="form-group">
              <label>Fecha de visita *</label>
              <input v-model="form.fecha_visita" type="date" />
            </div>

            <div class="form-group">
              <label>Hora de visita</label>
              <input v-model="form.hora_visita" type="time" />
            </div>

            <div class="form-group">
              <label>Etapa fenológica</label>
              <select v-model="form.id_etapa_fenologica">
                <option value="">Sin etapa asignada</option>
                <option v-for="e in etapas" :key="e.id" :value="e.id">
                  {{ e.nombre }}{{ e.codigo ? ` (${e.codigo})` : '' }}
                </option>
              </select>
            </div>

            <div v-if="form.tipo_inspeccion === 'Pre_siembra'" class="form-group span-2">
              <label>Condición del terreno</label>
              <input
                v-model.trim="form.condicion_terreno"
                type="text"
                maxlength="80"
                placeholder="Ej. Suelo húmedo, terreno preparado, presencia de malezas..."
              />
            </div>

            <div v-if="form.tipo_inspeccion === 'Pre_siembra'" class="form-group span-3">
              <label>Recomendación de pre siembra</label>
              <textarea
                v-model.trim="form.recomendacion_pre_siembra"
                placeholder="Ej. Realizar limpieza del terreno, mejorar drenaje o esperar mejores condiciones..."
              ></textarea>
            </div>
          </div>
        </div>

        <div class="inspeccion-form-section">
          <div class="inspeccion-section-title">
            <i class="ti ti-cloud"></i>
            Condiciones de campo
          </div>

          <div class="inspeccion-form-grid">
            <div class="form-group">
              <label>Condición climática</label>
              <select v-model="form.condicion_climatica">
                <option value="">Sin especificar</option>
                <option value="Soleado">Soleado</option>
                <option value="Nublado">Nublado</option>
                <option value="Lluvioso">Lluvioso</option>
                <option value="Helado">Helado</option>
                <option value="Ventoso">Ventoso</option>
                <option value="Mixto">Mixto</option>
              </select>
            </div>

            <div class="form-group">
              <label>Temperatura (°C)</label>
              <input
                v-model="form.temperatura_c"
                type="number"
                step="0.1"
                min="-30"
                max="50"
                placeholder="Ej. 12.5"
              />
            </div>

            <div class="form-group">
              <label>Humedad relativa (%)</label>
              <input
                v-model="form.humedad_relativa_pct"
                type="number"
                min="0"
                max="100"
                placeholder="Ej. 65"
              />
            </div>

            <div class="form-group">
              <label>% plantas afectadas</label>
              <input
                v-model="form.pct_plantas_afectadas"
                type="number"
                step="0.1"
                min="0"
                max="100"
                placeholder="Ej. 25"
              />
            </div>

            <div class="form-group span-3">
              <label>Observaciones</label>
              <textarea
                v-model.trim="form.observaciones"
                placeholder="Notas de campo, síntomas observados, distribución del daño, recomendaciones preliminares..."
              ></textarea>
            </div>
          </div>
        </div>

        <div v-if="localError || error" class="inspeccion-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">Cancelar</button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Registrar inspección' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  // Error devuelto por el servidor al guardar.
  error: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'create',
  },
  inspeccion: {
    type: Object,
    default: null,
  },

  tecnicos: {
  type: Array,
  default: () => [],
},
  parcelas: {
    type: Array,
    default: () => [],
  },
  cultivos: {
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
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const form = reactive(defaultForm());

const hoy = computed(() => new Date().toISOString().split('T')[0]);

const parcelasFiltradas = computed(() => {
  return props.parcelas.filter((parcela) => {
    const tieneCultivoActivo = props.cultivos.some((cultivo) => {
      const idParcelaCultivo = cultivo.id_parcela || cultivo.parcela?.id;

      return (
        Number(idParcelaCultivo) === Number(parcela.id) &&
        cultivo.estado === 'Activo'
      );
    });

    if (form.tipo_inspeccion === 'Cultivo') {
      return tieneCultivoActivo;
    }

    if (form.tipo_inspeccion === 'Pre_siembra') {
      return !tieneCultivoActivo;
    }

    return true;
  });
});

const mensajeFiltroParcelas = computed(() => {
  if (form.tipo_inspeccion === 'Cultivo') {
    return 'Solo se muestran parcelas que tienen un cultivo activo.';
  }

  return 'Solo se muestran parcelas sin cultivo activo para inspección de pre siembra.';
});

const cultivosFiltrados = computed(() => {
  if (!form.id_parcela) return [];

  return props.cultivos.filter((c) => {
    const idParcela = c.id_parcela || c.parcela?.id;
    const perteneceParcela = Number(idParcela) === Number(form.id_parcela);

    if (!perteneceParcela) {
      return false;
    }

    if (props.mode === 'edit') {
      const cultivoActualId =
        props.inspeccion?.id_cultivo ||
        props.inspeccion?.cultivo?.id;

      return c.estado === 'Activo' || Number(c.id) === Number(cultivoActualId);
    }

    return c.estado === 'Activo';
  });
});

const cultivoSeleccionado = computed(() => {
  return props.cultivos.find((c) => Number(c.id) === Number(form.id_cultivo)) || null;
});

watch(
  () => [props.mode, props.inspeccion],
  () => resetForm(),
  { immediate: true }
);

function defaultForm() {
  return {
    id_usuario: '',
    tipo_inspeccion: 'Cultivo',
    condicion_terreno: '',
    recomendacion_pre_siembra: '',

    id_parcela: '',
    id_cultivo: '',
    fecha_visita: new Date().toISOString().split('T')[0],
    hora_visita: '',
    id_etapa_fenologica: '',
    condicion_climatica: '',
    temperatura_c: '',
    humedad_relativa_pct: '',
    pct_plantas_afectadas: '',
    observaciones: '',
    estado: 'Pendiente',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.inspeccion) {
    form.id_usuario =
      props.inspeccion.id_usuario ||
      props.inspeccion.usuario?.id ||
      getCurrentUserId();

    form.tipo_inspeccion = props.inspeccion.tipo_inspeccion || 'Cultivo';
    form.condicion_terreno = props.inspeccion.condicion_terreno || '';
    form.recomendacion_pre_siembra = props.inspeccion.recomendacion_pre_siembra || '';

    form.id_parcela = props.inspeccion.id_parcela || props.inspeccion.parcela?.id || '';
    form.id_cultivo = props.inspeccion.id_cultivo || props.inspeccion.cultivo?.id || '';
    form.fecha_visita = formatInputDate(props.inspeccion.fecha_visita);
    form.hora_visita = formatInputTime(props.inspeccion.hora_visita);
    form.id_etapa_fenologica =
      props.inspeccion.id_etapa_fenologica ||
      props.inspeccion.etapa_fenologica?.id ||
      props.inspeccion.etapaFenologica?.id ||
      '';

    form.condicion_climatica = props.inspeccion.condicion_climatica || '';
    form.temperatura_c = props.inspeccion.temperatura_c ?? '';
    form.humedad_relativa_pct = props.inspeccion.humedad_relativa_pct ?? '';
    form.pct_plantas_afectadas = props.inspeccion.pct_plantas_afectadas ?? '';
    form.observaciones = props.inspeccion.observaciones || '';
    form.estado = props.inspeccion.estado || 'Pendiente';

    return;
  }

  Object.assign(form, defaultForm());
}

function onTipoInspeccionChange() {
  form.id_parcela = '';
  form.id_cultivo = '';
  form.id_etapa_fenologica = '';

  if (form.tipo_inspeccion === 'Cultivo') {
    form.condicion_terreno = '';
    form.recomendacion_pre_siembra = '';
  }
}

function onParcelaChange() {
  if (!form.id_cultivo) return;

  const pertenece = cultivosFiltrados.value.some(
    (c) => Number(c.id) === Number(form.id_cultivo)
  );

  if (!pertenece) {
    form.id_cultivo = '';
  }
}

function submit() {
  localError.value = '';

 if (!form.id_usuario) {
  return showError('Debe seleccionar el técnico asignado.');
}

  if (!form.tipo_inspeccion) {
    return showError('Debe seleccionar el tipo de inspección.');
  }

  if (!form.id_parcela) {
    return showError('Debe seleccionar una parcela.');
  }

  const parcelaTieneCultivoActivo = props.cultivos.some((cultivo) => {
  const idParcelaCultivo = cultivo.id_parcela || cultivo.parcela?.id;

  return (
    Number(idParcelaCultivo) === Number(form.id_parcela) &&
    cultivo.estado === 'Activo'
  );
});

if (form.tipo_inspeccion === 'Cultivo' && !parcelaTieneCultivoActivo) {
  return showError('Para una inspección de cultivo debe seleccionar una parcela con cultivo activo.');
}

if (form.tipo_inspeccion === 'Pre_siembra' && parcelaTieneCultivoActivo) {
  return showError('Para pre siembra debe seleccionar una parcela sin cultivo activo.');
}

  if (form.tipo_inspeccion === 'Cultivo' && !form.id_cultivo) {
    return showError('Debe seleccionar un cultivo activo para registrar la inspección.');
  }

  if (
    form.tipo_inspeccion === 'Cultivo' &&
    props.mode === 'create' &&
    cultivoSeleccionado.value?.estado !== 'Activo'
  ) {
    return showError('Solo se pueden registrar inspecciones sobre cultivos activos.');
  }

  if (!form.fecha_visita) {
    return showError('La fecha de visita es obligatoria.');
  }

  if (
    ['En_proceso', 'Completada', 'Validada'].includes(form.estado) &&
    form.fecha_visita > hoy.value
  ) {
    return showError(
      'La fecha no puede ser futura cuando la inspección está en proceso, completada o validada.'
    );
  }

  if (
    form.tipo_inspeccion === 'Cultivo' &&
    form.id_cultivo &&
    cultivoSeleccionado.value?.fecha_siembra
  ) {
    const fechaSiembra = formatInputDate(cultivoSeleccionado.value.fecha_siembra);

    if (form.fecha_visita < fechaSiembra) {
      return showError('La fecha de inspección no puede ser anterior a la fecha de siembra del cultivo.');
    }
  }

  if (
    form.temperatura_c !== '' &&
    (Number(form.temperatura_c) < -30 || Number(form.temperatura_c) > 50)
  ) {
    return showError('La temperatura debe estar entre -30 y 50 °C.');
  }

  if (
    form.humedad_relativa_pct !== '' &&
    (Number(form.humedad_relativa_pct) < 0 || Number(form.humedad_relativa_pct) > 100)
  ) {
    return showError('La humedad relativa debe estar entre 0 y 100%.');
  }

  if (
    form.pct_plantas_afectadas !== '' &&
    (Number(form.pct_plantas_afectadas) < 0 || Number(form.pct_plantas_afectadas) > 100)
  ) {
    return showError('El porcentaje de plantas afectadas debe estar entre 0 y 100%.');
  }

  const payload = {
    id_usuario: form.id_usuario || getCurrentUserId(),
    tipo_inspeccion: form.tipo_inspeccion || 'Cultivo',

    id_parcela: form.id_parcela,
    id_cultivo: form.tipo_inspeccion === 'Cultivo' ? form.id_cultivo || null : null,
    fecha_visita: form.fecha_visita,
    hora_visita: form.hora_visita ? String(form.hora_visita).slice(0, 5) : null,
    id_etapa_fenologica:
      form.tipo_inspeccion === 'Cultivo' ? form.id_etapa_fenologica || null : null,

    condicion_climatica: form.condicion_climatica || null,
    temperatura_c: form.temperatura_c !== '' ? Number(form.temperatura_c) : null,
    humedad_relativa_pct:
      form.humedad_relativa_pct !== '' ? Number(form.humedad_relativa_pct) : null,
    pct_plantas_afectadas:
      form.pct_plantas_afectadas !== '' ? Number(form.pct_plantas_afectadas) : null,

    observaciones: form.observaciones || null,
    // El estado de una inspección existente se cambia desde "Cambiar estado".
    ...(props.mode === 'create' ? { estado: form.estado || 'Pendiente' } : {}),

    condicion_terreno:
      form.tipo_inspeccion === 'Pre_siembra' ? form.condicion_terreno || null : null,
    recomendacion_pre_siembra:
      form.tipo_inspeccion === 'Pre_siembra'
        ? form.recomendacion_pre_siembra || null
        : null,
  };

  emit('save', payload);
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
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

function formatInputDate(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}

function formatInputTime(value) {
  if (!value) return '';
  return String(value).slice(0, 5);
}

function formatDate(value) {
  if (!value) return 'Sin fecha';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);

  return date.toLocaleDateString('es-BO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function getCurrentUserId() {
  const keys = ['user_data', 'user', 'auth_user'];

  for (const key of keys) {
    try {
      const raw = localStorage.getItem(key);

      if (!raw) continue;

      const data = JSON.parse(raw);

      return data?.id || data?.user?.id || data?.data?.id || '';
    } catch {
      // Ignorar valores inválidos del localStorage
    }
  }

  return '';
}
function nombreUsuario(user) {
  if (!user) return 'Sin usuario';

  return [
    user.name,
    user.paterno,
    user.materno,
  ]
    .filter(Boolean)
    .join(' ') || 'Sin usuario';
}
</script>