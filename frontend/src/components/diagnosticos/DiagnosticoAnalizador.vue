<template>
  <section class="diagnostico-cnn-card diagnostico-analizador-card">
    <div class="diagnostico-cnn-card-head">
      <div>
        <h2>
          <i class="ti ti-photo-scan"></i>
          Análisis CNN de imagen
        </h2>
        <p>
          La imagen será enviada al modelo ResNet50. La enfermedad y la confianza se calculan automáticamente.
        </p>
      </div>
    </div>

    <div class="diagnostico-analizador-body">
      <div
        class="diagnostico-service-state"
        :class="cnnDisponible ? 'is-online' : 'is-offline'"
      >
        <i :class="cnnDisponible ? 'ti ti-circle-check' : 'ti ti-alert-triangle'"></i>
        <span v-if="cnnDisponible">
          Modelo listo<span v-if="cnnVersion">: {{ cnnVersion }}</span>
        </span>
        <span v-else>
          El servicio CNN no está disponible. No se puede analizar una imagen por el momento.
        </span>
      </div>
      <div
        class="diagnostico-dropzone"
        :class="{ dragging: dragging, hasFile: fotoPreview }"
        @click="openFile"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <input
          ref="fileInput"
          type="file"
          accept="image/jpg,image/jpeg,image/png,image/webp"
          hidden
          @change="onFotoChange"
        />

        <template v-if="fotoPreview">
          <img :src="fotoPreview" alt="Vista previa de hoja" />

          <button
            class="diagnostico-remove-img"
            type="button"
            @click.stop="removeFoto"
          >
            <i class="ti ti-x"></i>
          </button>
        </template>

        <template v-else>
          <div class="diagnostico-drop-icon">
            <i class="ti ti-camera-spark"></i>
          </div>

          <strong>Cargar imagen de hoja</strong>
          <span>Arrastra o haz clic para seleccionar</span>
          <small>JPG, PNG o WEBP — Máx. 5 MB</small>
        </template>
      </div>

      <div class="diagnostico-form-grid">
        <div class="form-group span-2">
          <label>Inspección *</label>
          <select v-model="form.id_inspeccion">
            <option value="">Selecciona inspección</option>

            <option
              v-for="inspeccion in inspecciones"
              :key="inspeccion.id"
              :value="inspeccion.id"
            >
              #{{ inspeccion.id }}
              —
              {{ inspeccion.parcela?.codigo_parcela || 'Sin parcela' }}
              ({{ formatDate(inspeccion.fecha_visita) }})
            </option>
          </select>
        </div>
      </div>

      <div class="diagnostico-model-help">
        <strong>Proceso automático:</strong>
        imagen → preprocesamiento 224×224 → ResNet50 → clase predicha → porcentaje de confianza.
        El resultado queda pendiente de validación por el técnico o agrónomo.
      </div>

      <div v-if="rechazo" class="diagnostico-rechazo" role="alert">
        <div class="diagnostico-rechazo-icono">
          <i :class="infoRechazo.icono"></i>
        </div>

        <div class="diagnostico-rechazo-texto">
          <strong>{{ infoRechazo.titulo }}</strong>
          <p>{{ rechazo.mensaje }}</p>

          <div v-if="rechazo.consejo" class="diagnostico-rechazo-consejo">
            <i class="ti ti-bulb"></i>
            <span><b>Qué hacer:</b> {{ rechazo.consejo }}</span>
          </div>
        </div>
      </div>

      <div v-if="localError" class="diagnostico-form-error">
        {{ localError }}
      </div>

      <button
        class="diagnostico-analizar-btn"
        type="button"
        :disabled="saving || !cnnDisponible"
        @click="submit"
      >
        <i class="ti ti-microscope"></i>
        {{ saving ? 'Analizando imagen con CNN...' : (!cnnDisponible ? 'CNN no disponible' : 'Analizar con CNN') }}
      </button>

      <p class="diagnostico-model-help">
        El modelo apoya el diagnóstico; la decisión final permanece bajo validación agronómica.
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

// Mismos límites que valida Laravel y el servicio CNN.
const MAX_BYTES = 5 * 1024 * 1024;
const LADO_MINIMO_PX = 160;

// Título e ícono según el motivo que devuelve la CNN.
const MOTIVOS = {
  borrosa: { titulo: 'La foto está borrosa', icono: 'ti ti-focus-2' },
  muy_oscura: { titulo: 'La foto tiene poca luz', icono: 'ti ti-moon' },
  sobreexpuesta: { titulo: 'La foto tiene demasiada luz', icono: 'ti ti-sun' },
  poco_contraste: { titulo: 'La foto tiene poco contraste', icono: 'ti ti-contrast-2' },
  no_vegetal: { titulo: 'No se ve una hoja en la foto', icono: 'ti ti-leaf-off' },
  no_es_papa: { titulo: 'No parece una hoja de papa', icono: 'ti ti-plant-off' },
  baja_certeza: { titulo: 'La IA no está segura del resultado', icono: 'ti ti-help-hexagon' },
  clases_similares: { titulo: 'El resultado es ambiguo', icono: 'ti ti-arrows-diff' },
  resolucion_baja: { titulo: 'La imagen es muy pequeña', icono: 'ti ti-arrows-minimize' },
  resolucion_excesiva: { titulo: 'La imagen es demasiado grande', icono: 'ti ti-arrows-maximize' },
  formato_invalido: { titulo: 'Formato de imagen no válido', icono: 'ti ti-file-alert' },
  peso_excesivo: { titulo: 'La imagen pesa demasiado', icono: 'ti ti-weight' },
  imagen_vacia: { titulo: 'El archivo está vacío', icono: 'ti ti-file-off' },
  archivo_danado: { titulo: 'No se pudo leer la imagen', icono: 'ti ti-file-alert' },
  imagen_animada: { titulo: 'La imagen es animada', icono: 'ti ti-file-alert' },
};

const props = defineProps({
  inspecciones: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
  cnnDisponible: {
    type: Boolean,
    default: false,
  },
  cnnVersion: {
    type: String,
    default: null,
  },
  // Motivo por el que la CNN rechazó la última foto: { mensaje, consejo, codigo }
  rechazo: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['save', 'limpiar-rechazo']);

const infoRechazo = computed(() => {
  return MOTIVOS[props.rechazo?.codigo] || {
    titulo: 'No se pudo analizar la foto',
    icono: 'ti ti-alert-triangle',
  };
});

const fileInput = ref(null);
const fotoPreview = ref(null);
const fotoArchivo = ref(null);
const dragging = ref(false);
const localError = ref('');

const form = reactive(defaultForm());

function defaultForm() {
  return {
    id_inspeccion: '',
  };
}

function openFile() {
  if (!props.cnnDisponible || props.saving) return;
  fileInput.value?.click();
}

function onDrop(event) {
  dragging.value = false;
  const archivo = event.dataTransfer?.files?.[0];
  if (archivo) procesarArchivo(archivo);
}

function onFotoChange(event) {
  const archivo = event.target.files?.[0];
  if (archivo) procesarArchivo(archivo);
}

// Lee el ancho y alto reales de la imagen (null si el navegador no puede leerla).
function leerDimensiones(archivo) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(archivo);
    const imagen = new Image();

    imagen.onload = () => {
      URL.revokeObjectURL(url);
      resolve({ ancho: imagen.naturalWidth, alto: imagen.naturalHeight });
    };

    imagen.onerror = () => {
      URL.revokeObjectURL(url);
      resolve(null);
    };

    imagen.src = url;
  });
}

async function procesarArchivo(archivo) {
  emit('limpiar-rechazo');

  if (!archivo || archivo.size <= 0) {
    removeFoto();
    localError.value = 'La fotografía seleccionada está vacía o no es válida.';
    return;
  }

  const tiposPermitidos = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp',
  ];

  if (!tiposPermitidos.includes(archivo.type)) {
    removeFoto();
    localError.value = 'La fotografía debe ser JPG, JPEG, PNG o WEBP.';
    return;
  }

  if (archivo.size > MAX_BYTES) {
    removeFoto();
    localError.value = 'La fotografía no debe superar los 5 MB. Reduzca su tamaño o tome otra foto.';
    return;
  }

  const dimensiones = await leerDimensiones(archivo);

  if (!dimensiones) {
    removeFoto();
    localError.value = 'No se pudo leer la imagen: el archivo puede estar dañado. Seleccione otra fotografía.';
    return;
  }

  if (dimensiones.ancho < LADO_MINIMO_PX || dimensiones.alto < LADO_MINIMO_PX) {
    removeFoto();
    localError.value = `La imagen es muy pequeña (${dimensiones.ancho} × ${dimensiones.alto} px): debe medir al menos ${LADO_MINIMO_PX} × ${LADO_MINIMO_PX} píxeles. Acérquese a la hoja o use una foto de mayor resolución.`;
    return;
  }

  localError.value = '';
  fotoArchivo.value = archivo;

  if (fotoPreview.value) URL.revokeObjectURL(fotoPreview.value);
  fotoPreview.value = URL.createObjectURL(archivo);
}

function removeFoto() {
  emit('limpiar-rechazo');
  fotoArchivo.value = null;

  if (fotoPreview.value) URL.revokeObjectURL(fotoPreview.value);
  fotoPreview.value = null;

  if (fileInput.value) fileInput.value.value = '';
}

function validar() {
  if (!props.cnnDisponible) {
    localError.value = 'El servicio CNN no está disponible. Intente nuevamente cuando el modelo esté listo.';
    return false;
  }

  if (!form.id_inspeccion) {
    localError.value = 'Debe seleccionar una inspección.';
    return false;
  }

  const inspeccion = props.inspecciones.find(
    (item) => String(item.id) === String(form.id_inspeccion)
  );

  if (!inspeccion) {
    localError.value = 'La inspección seleccionada ya no está disponible para diagnóstico.';
    return false;
  }

  if (
    (inspeccion.tipo_inspeccion || 'Cultivo') !== 'Cultivo' ||
    !['En_proceso', 'Completada'].includes(inspeccion.estado) ||
    inspeccion.cultivo?.estado !== 'Activo'
  ) {
    localError.value = 'La inspección debe corresponder a un cultivo activo y estar en proceso o completada.';
    return false;
  }

  if (!fotoArchivo.value) {
    localError.value = 'Debe cargar una fotografía de la hoja.';
    return false;
  }

  localError.value = '';
  return true;
}

function submit() {
  if (!validar()) return;

  const formData = new FormData();
  formData.append('id_inspeccion', form.id_inspeccion);
  formData.append('fotografia', fotoArchivo.value);

  emit('save', formData);
}

function reset() {
  Object.assign(form, defaultForm());
  removeFoto();
  localError.value = '';
}

function formatDate(fecha) {
  if (!fecha) return '—';

  return new Date(fecha).toLocaleDateString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

onBeforeUnmount(() => {
  if (fotoPreview.value) URL.revokeObjectURL(fotoPreview.value);
});

defineExpose({ reset });
</script>
