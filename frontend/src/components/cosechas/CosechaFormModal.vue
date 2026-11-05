<template>
  <div class="modal-overlay cosecha-modal-overlay" @click.self="close">
    <div class="modal-card cosecha-form-modal">
      <div class="modal-header cosecha-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Registrar cosecha' : 'Editar cosecha' }}
          </div>
          <p class="cosecha-modal-subtitle">
            Registra la producción obtenida por parcela y variedad sembrada.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">✕</button>
      </div>

      <div class="modal-body">
        <div class="cosecha-form-section">
          <div class="cosecha-section-title">
            <i class="ti ti-map-2"></i>
            Parcela y cultivo
          </div>

          <div class="cosecha-form-grid">
            <div class="form-group span-2">
  <label>Parcela *</label>
  <select v-model="form.id_parcela" @change="onParcelaChange">
    <option value="">Selecciona una parcela</option>
    <option v-for="p in parcelasConCultivoActivo" :key="p.id" :value="p.id">
      {{ p.codigo_parcela }}
      {{ p.nombre_referencial ? `— ${p.nombre_referencial}` : '' }}
      {{ p.productor ? ` | ${nombreProductor(p.productor)}` : '' }}
      {{ p.superficie_ha ? ` (${p.superficie_ha} ha)` : '' }}
    </option>
  </select>
  <small v-if="!parcelasConCultivoActivo.length" class="field-help">
    No hay parcelas con cultivos activos disponibles para cosechar.
  </small>
</div>

            <div class="form-group">
              <label>Variedad / cultivo</label>
              <select v-model="form.id_cultivo">
                <option value="">Sin especificar</option>
                <option v-for="c in cultivosFiltrados" :key="c.id" :value="c.id">
                  {{ c.variedad_papa }} — {{ limpiarEstado(c.estado) }}
                </option>
              </select>
              <small class="field-help">
                Solo se muestran cultivos activos de la parcela seleccionada.
              </small>
            </div>

            <div class="form-group">
              <label>Fecha de cosecha *</label>
              <input v-model="form.fecha_cosecha" type="date" :max="hoy" />
            </div>

            <div class="form-group">
              <label>Técnico responsable</label>
              <input :value="userName" readonly class="readonly-input" />
            </div>

            <div class="form-group">
              <label>Superficie parcela</label>
              <input :value="superficieTexto" readonly class="readonly-input" />
            </div>
          </div>
        </div>

        <div class="cosecha-form-section">
          <div class="cosecha-section-title">
            <i class="ti ti-chart-histogram"></i>
            Producción
          </div>

          <div class="cosecha-form-grid">
            <div class="form-group">
              <label>Toneladas totales *</label>
              <input
                v-model="form.cantidad_toneladas"
                type="number"
                step="0.001"
                min="0.001"
                placeholder="Ej. 26.5"
              />
            </div>

            <div class="form-group">
              <label>Rendimiento t/ha</label>
              <input :value="rendimientoCalculado || 'Auto-calculado'" readonly class="readonly-green" />
            </div>

            <div class="form-group">
              <label>Calidad *</label>
              <select v-model="form.calidad">
                <option value="Primera">Primera</option>
                <option value="Segunda">Segunda</option>
                <option value="Tercera">Tercera</option>
                <option value="Descarte">Descarte</option>
              </select>
            </div>

            <div class="form-group">
              <label>Destino *</label>
              <select v-model="form.destino">
                <option value="Venta">Venta</option>
                <option value="Semilla">Semilla</option>
                <option value="Autoconsumo">Autoconsumo</option>
                <option value="Mixto">Mixto</option>
              </select>
            </div>

            <div class="form-group">
              <label>Precio unitario (Bs/qq)</label>
              <input
                v-model="form.precio_unitario_bs"
                type="number"
                step="0.01"
                min="0"
                placeholder="Ej. 120"
                @input="calcularIngreso"
              />
              <small class="field-help">Referencia: 1 tonelada ≈ 10 quintales.</small>
            </div>

            <div class="form-group">
              <label>Ingreso estimado (Bs.)</label>
              <input
                v-model="form.ingreso_estimado_bs"
                type="number"
                step="0.01"
                min="0"
                placeholder="Auto-calculado o manual"
              />
            </div>

            <div class="form-group span-3">
              <label>Observaciones</label>
              <textarea
                v-model.trim="form.observaciones"
                placeholder="Condiciones de la cosecha, incidencias, destino específico..."
              ></textarea>
            </div>
          </div>

          <div v-if="rendimientoCalculado" class="cosecha-rendimiento-box">
            <span>
              <i class="ti ti-calculator"></i>
              Rendimiento calculado automáticamente
            </span>
            <strong>{{ rendimientoCalculado }} t/ha</strong>
          </div>
        </div>

        <div v-if="localError || error" class="cosecha-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">Cancelar</button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Registrar cosecha' : 'Guardar cambios' }}
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
  cosecha: {
    type: Object,
    default: null,
  },
  parcelas: {
    type: Array,
    default: () => [],
  },
  cultivos: {
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

const userName = computed(() => {
  const data = JSON.parse(localStorage.getItem('user_data') || '{}');
  return [data.name, data.paterno].filter(Boolean).join(' ') || 'Usuario';
});

const parcelaSeleccionada = computed(() => {
  return props.parcelas.find((p) => Number(p.id) === Number(form.id_parcela)) || null;
});

const cultivoSeleccionado = computed(() => {
  return props.cultivos.find((c) => Number(c.id) === Number(form.id_cultivo)) || null;
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
      return true;
    }

    return c.estado === 'Activo';
  });
});

const parcelasConCultivoActivo = computed(() => {
  const idsConCultivoActivo = new Set(
    props.cultivos
      .filter((c) => c.estado === 'Activo')
      .map((c) => Number(c.id_parcela || c.parcela?.id))
  );

  return props.parcelas.filter((p) => {
    // en modo edición, si la parcela actual ya no tiene cultivos activos
    // igual la dejamos visible para no "perder" el dato guardado
    if (props.mode === 'edit' && Number(p.id) === Number(form.id_parcela)) {
      return true;
    }

    return idsConCultivoActivo.has(Number(p.id));
  });
});

const superficieTexto = computed(() => {
  return parcelaSeleccionada.value?.superficie_ha
    ? `${parcelaSeleccionada.value.superficie_ha} ha`
    : '—';
});

const rendimientoCalculado = computed(() => {
  if (!form.cantidad_toneladas || !parcelaSeleccionada.value?.superficie_ha) {
    return '';
  }

  const rendimiento = Number(form.cantidad_toneladas) / Number(parcelaSeleccionada.value.superficie_ha);

  if (Number.isNaN(rendimiento)) return '';

  return rendimiento.toFixed(3);
});

watch(
  () => [props.mode, props.cosecha],
  () => resetForm(),
  { immediate: true }
);

watch(
  () => form.cantidad_toneladas,
  () => calcularIngreso()
);

function defaultForm() {
  return {
    id_parcela: '',
    id_cultivo: '',
    fecha_cosecha: new Date().toISOString().split('T')[0],
    cantidad_toneladas: '',
    calidad: 'Primera',
    destino: 'Venta',
    precio_unitario_bs: '',
    ingreso_estimado_bs: '',
    observaciones: '',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.cosecha) {
    form.id_parcela = props.cosecha.id_parcela || props.cosecha.parcela?.id || '';
    form.id_cultivo = props.cosecha.id_cultivo || props.cosecha.cultivo?.id || '';
    form.fecha_cosecha = formatInputDate(props.cosecha.fecha_cosecha);
    form.cantidad_toneladas = props.cosecha.cantidad_toneladas ?? '';
    form.calidad = props.cosecha.calidad || 'Primera';
    form.destino = props.cosecha.destino || 'Venta';
    form.precio_unitario_bs = props.cosecha.precio_unitario_bs ?? '';
    form.ingreso_estimado_bs = props.cosecha.ingreso_estimado_bs ?? '';
    form.observaciones = props.cosecha.observaciones || '';
    return;
  }

  Object.assign(form, defaultForm());
}

function onParcelaChange() {
  if (!form.id_cultivo) return;

  const pertenece = cultivosFiltrados.value.some((c) => Number(c.id) === Number(form.id_cultivo));

  if (!pertenece) {
    form.id_cultivo = '';
  }
}

function calcularIngreso() {
  if (!form.cantidad_toneladas || !form.precio_unitario_bs) {
    return;
  }

  const quintales = Number(form.cantidad_toneladas) * 10;
  const ingreso = quintales * Number(form.precio_unitario_bs);

  if (!Number.isNaN(ingreso)) {
    form.ingreso_estimado_bs = Math.round(ingreso * 100) / 100;
  }
}

function submit() {
  localError.value = '';

  if (!form.id_parcela) {
    return showError('Debe seleccionar una parcela.');
  }

  if (!form.id_cultivo) {
  return showError('Debe seleccionar un cultivo activo para registrar la cosecha.');
}

if (props.mode === 'create' && cultivoSeleccionado.value?.estado !== 'Activo') {
  return showError('Solo se puede registrar cosecha para cultivos activos.');
}

  if (!form.fecha_cosecha) {
    return showError('La fecha de cosecha es obligatoria.');
  }

  if (form.fecha_cosecha > hoy.value) {
    return showError('La fecha de cosecha no puede ser futura.');
  }

  if (!form.cantidad_toneladas || Number(form.cantidad_toneladas) <= 0) {
    return showError('La cantidad cosechada debe ser mayor a 0.');
  }

  if (form.id_cultivo && cultivoSeleccionado.value?.fecha_siembra) {
    const fechaSiembra = formatInputDate(cultivoSeleccionado.value.fecha_siembra);

    if (form.fecha_cosecha < fechaSiembra) {
      return showError('La fecha de cosecha no puede ser anterior a la fecha de siembra del cultivo.');
    }
  }

  if (form.precio_unitario_bs !== '' && Number(form.precio_unitario_bs) < 0) {
    return showError('El precio unitario no puede ser negativo.');
  }

  if (form.ingreso_estimado_bs !== '' && Number(form.ingreso_estimado_bs) < 0) {
    return showError('El ingreso estimado no puede ser negativo.');
  }

  const payload = {
    id_parcela: form.id_parcela,
    id_cultivo: form.id_cultivo || null,
    fecha_cosecha: form.fecha_cosecha,
    cantidad_toneladas: Number(form.cantidad_toneladas),
    calidad: form.calidad,
    destino: form.destino,
    precio_unitario_bs:
      form.precio_unitario_bs !== '' && form.precio_unitario_bs !== null
        ? Number(form.precio_unitario_bs)
        : null,
    ingreso_estimado_bs:
      form.ingreso_estimado_bs !== '' && form.ingreso_estimado_bs !== null
        ? Number(form.ingreso_estimado_bs)
        : null,
    observaciones: form.observaciones || null,
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

function limpiarEstado(estado) {
  if (!estado) return 'Sin estado';
  return String(estado).replaceAll('_', ' ');
}

function formatInputDate(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}
</script>