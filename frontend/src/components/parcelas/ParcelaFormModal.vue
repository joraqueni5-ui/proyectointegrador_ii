<template>
  <div class="modal-overlay parcela-modal-overlay" @click.self="close">
    <div class="modal-card parcela-form-modal">
      <div class="modal-header parcela-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nueva parcela' : 'Editar parcela' }}
          </div>
          <p class="parcela-modal-subtitle">
            Registra los datos físicos, productivos y la ubicación GPS de la parcela.
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <!-- Datos principales -->
        <div class="parcela-form-section">
          <div class="parcela-section-title">
            <i class="ti ti-map-2"></i>
            Datos principales
          </div>

          <div class="parcela-form-grid">
            <div class="form-group span-2">
              <label>Productor *</label>
              <select v-model="form.id_productor">
                <option value="">Selecciona un productor</option>
                <option
                  v-for="p in productores"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ nombreProductor(p) }} — CI: {{ p.carnet_identidad || 'S/N' }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>Código de parcela *</label>
              <input
                v-model="form.codigo_parcela"
                maxlength="30"
                placeholder="Ej. P-SUR-01"
                @input="form.codigo_parcela = limpiarCodigo(form.codigo_parcela)"
              />
              <small class="field-help">Solo letras, números y guiones.</small>
            </div>

            <div class="form-group span-3">
              <label>Nombre referencial</label>
              <input
                v-model.trim="form.nombre_referencial"
                maxlength="150"
                placeholder="Ej. Parcela del cerro norte"
              />
            </div>
          </div>
        </div>

        <!-- Características del terreno -->
        <div class="parcela-form-section">
          <div class="parcela-section-title">
            <i class="ti ti-ruler-measure"></i>
            Características del terreno
          </div>

          <div class="parcela-form-grid">
            <div class="form-group">
              <label>Superficie (ha)</label>
              <input
                v-model="form.superficie_ha"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="Ej. 2.50"
              />
            </div>

            <div class="form-group">
              <label>Altitud (m.s.n.m.)</label>
              <input
                v-model="form.altitud_msnm"
                inputmode="numeric"
                maxlength="5"
                placeholder="Ej. 3800"
                @input="form.altitud_msnm = soloNumeros(form.altitud_msnm, 5)"
              />
            </div>

            <div class="form-group">
              <label>Sistema de riego *</label>
              <select v-model="form.sistema_riego">
                <option value="Secano">Secano</option>
                <option value="Riego">Riego</option>
                <option value="Mixto">Mixto</option>
              </select>
            </div>

            <div class="form-group">
              <label>Tipo de suelo</label>
              <select v-model="form.tipo_suelo">
                <option value="">Selecciona</option>
                <option value="Arcilloso">Arcilloso</option>
                <option value="Limoso">Limoso</option>
                <option value="Arenoso">Arenoso</option>
                <option value="Franco">Franco</option>
                <option value="Franco-arcilloso">Franco-arcilloso</option>
                <option value="Otro">Otro</option>
              </select>
            </div>

            <div class="form-group">
              <label>Pendiente</label>
              <select v-model="form.pendiente">
                <option value="">Selecciona</option>
                <option value="Plano">Plano</option>
                <option value="Suave">Suave</option>
                <option value="Moderado">Moderado</option>
                <option value="Fuerte">Fuerte</option>
              </select>
            </div>

            <div v-if="mode === 'edit'" class="form-group">
              <label>Estado</label>
              <select v-model="form.estado">
                <option value="Activa">Activa</option>
                <option value="En_descanso">En descanso</option>
                <option value="Inactiva">Inactiva</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Ubicación GPS -->
        <div class="parcela-form-section">
          <div class="parcela-section-title between">
            <span>
              <i class="ti ti-map-pin"></i>
              Ubicación GPS de la parcela *
            </span>

            <button
              class="btn btn-s"
              type="button"
              :disabled="gettingLocation"
              @click="usarUbicacionActual"
            >
              <i class="ti ti-current-location"></i>
              {{ gettingLocation ? 'Buscando...' : 'Usar mi ubicación actual' }}
            </button>
          </div>

          <div class="parcela-map-help">
            Puedes hacer click en el mapa para colocar el marcador, usar tu ubicación actual o escribir las coordenadas manualmente.
          </div>

          <div ref="mapContainer" class="parcela-form-map"></div>

          <div class="parcela-form-grid mt14">
            <div class="form-group">
              <label>Latitud *</label>
              <input
                v-model="form.latitud"
                type="number"
                step="0.0000001"
                placeholder="-16.5000000"
              />
              <small class="field-help">Debe estar entre -90 y 90.</small>
            </div>

            <div class="form-group">
              <label>Longitud *</label>
              <input
                v-model="form.longitud"
                type="number"
                step="0.0000001"
                placeholder="-68.1500000"
              />
              <small class="field-help">Debe estar entre -180 y 180.</small>
            </div>

            <div class="form-group">
              <label>Acción</label>
              <button class="btn btn-s w-full" type="button" @click="centrarEnCoordenadas">
                <i class="ti ti-map-pin"></i>
                Centrar en coordenadas
              </button>
            </div>

            <div class="form-group span-3">
              <label>Coordenadas en texto</label>
              <input
                v-model.trim="form.coordenadas_texto"
                maxlength="100"
                placeholder="Ej. 16°30'S 68°10'O"
              />
              <small class="field-help">Opcional. Sirve como referencia adicional.</small>
            </div>
          </div>
        </div>

        <!-- Observaciones -->
        <div class="parcela-form-section">
          <div class="parcela-section-title">
            <i class="ti ti-notes"></i>
            Observaciones
          </div>

          <div class="form-group">
            <textarea
              v-model.trim="form.observaciones"
              placeholder="Notas adicionales sobre acceso, límites, referencias del terreno, etc."
            ></textarea>
          </div>
        </div>

        <div v-if="localError || error" class="parcela-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear parcela' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

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
  parcela: {
    type: Object,
    default: null,
  },
  productores: {
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
const gettingLocation = ref(false);
const mapContainer = ref(null);

const form = reactive(defaultForm());

let mapInstance = null;
let markerInstance = null;

watch(
  () => [props.mode, props.parcela],
  async () => {
    resetForm();
    await nextTick();
    refreshMapFromForm();
  },
  { immediate: true }
);

watch(
  () => [form.latitud, form.longitud],
  () => {
    updateMarkerFromInputs();
  }
);

onMounted(async () => {
  await nextTick();
  initMap();
});

onBeforeUnmount(() => {
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});

function defaultForm() {
  return {
    id_productor: '',
    codigo_parcela: '',
    nombre_referencial: '',
    superficie_ha: '',
    altitud_msnm: '',
    tipo_suelo: '',
    pendiente: '',
    sistema_riego: 'Secano',
    latitud: '',
    longitud: '',
    coordenadas_texto: '',
    observaciones: '',
    estado: 'Activa',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.parcela) {
    form.id_productor = props.parcela.id_productor || '';
    form.codigo_parcela = limpiarCodigo(props.parcela.codigo_parcela || '');
    form.nombre_referencial = props.parcela.nombre_referencial || '';
    form.superficie_ha = props.parcela.superficie_ha ?? '';
    form.altitud_msnm = props.parcela.altitud_msnm ?? '';
    form.tipo_suelo = props.parcela.tipo_suelo || '';
    form.pendiente = props.parcela.pendiente || '';
    form.sistema_riego = props.parcela.sistema_riego || 'Secano';
    form.latitud = props.parcela.latitud ?? '';
    form.longitud = props.parcela.longitud ?? '';
    form.coordenadas_texto = props.parcela.coordenadas_texto || '';
    form.observaciones = props.parcela.observaciones || '';
    form.estado = props.parcela.estado || 'Activa';
    return;
  }

  Object.assign(form, defaultForm());
}

function initMap() {
  if (!mapContainer.value || mapInstance) {
    return;
  }

  const initialLat = Number(form.latitud);
  const initialLng = Number(form.longitud);

  const hasCoords = !Number.isNaN(initialLat) && !Number.isNaN(initialLng);

  mapInstance = L.map(mapContainer.value, {
    zoomControl: true,
    scrollWheelZoom: true,
  }).setView(
    hasCoords ? [initialLat, initialLng] : [-16.5, -68.15],
    hasCoords ? 15 : 8
  );

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
  }).addTo(mapInstance);

  mapInstance.on('click', (event) => {
    setCoordinates(event.latlng.lat, event.latlng.lng, true);
  });

  if (hasCoords) {
    setMarker(initialLat, initialLng);
  }

  setTimeout(() => {
    mapInstance?.invalidateSize();
  }, 200);
}

function refreshMapFromForm() {
  if (!mapInstance) {
    initMap();
    return;
  }

  const lat = Number(form.latitud);
  const lng = Number(form.longitud);

  if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
    setMarker(lat, lng);
    mapInstance.setView([lat, lng], 15);
  }

  setTimeout(() => {
    mapInstance?.invalidateSize();
  }, 150);
}

function updateMarkerFromInputs() {
  if (!mapInstance) {
    return;
  }

  const lat = Number(form.latitud);
  const lng = Number(form.longitud);

  if (
    Number.isNaN(lat) ||
    Number.isNaN(lng) ||
    lat < -90 ||
    lat > 90 ||
    lng < -180 ||
    lng > 180
  ) {
    return;
  }

  setMarker(lat, lng);
}

function setCoordinates(lat, lng, center = false) {
  form.latitud = Number(lat).toFixed(7);
  form.longitud = Number(lng).toFixed(7);

  setMarker(Number(form.latitud), Number(form.longitud));

  if (center && mapInstance) {
    mapInstance.setView([Number(form.latitud), Number(form.longitud)], 16);
  }
}

function setMarker(lat, lng) {
  if (!mapInstance) {
    return;
  }

  if (!markerInstance) {
    markerInstance = L.marker([lat, lng], {
      draggable: true,
    }).addTo(mapInstance);

    markerInstance.on('dragend', () => {
      const position = markerInstance.getLatLng();
      setCoordinates(position.lat, position.lng, false);
    });

    return;
  }

  markerInstance.setLatLng([lat, lng]);
}

function usarUbicacionActual() {
  localError.value = '';

  if (!navigator.geolocation) {
    localError.value = 'Tu navegador no permite obtener la ubicación actual.';
    return;
  }

  gettingLocation.value = true;

  navigator.geolocation.getCurrentPosition(
    (position) => {
      setCoordinates(position.coords.latitude, position.coords.longitude, true);
      gettingLocation.value = false;
    },
    () => {
      localError.value = 'No se pudo obtener la ubicación. Verifica los permisos del navegador.';
      gettingLocation.value = false;
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
    }
  );
}

function centrarEnCoordenadas() {
  const lat = Number(form.latitud);
  const lng = Number(form.longitud);

  if (Number.isNaN(lat) || Number.isNaN(lng)) {
    localError.value = 'Ingresa latitud y longitud válidas para centrar el mapa.';
    return;
  }

  if (lat < -90 || lat > 90) {
    localError.value = 'La latitud debe estar entre -90 y 90.';
    return;
  }

  if (lng < -180 || lng > 180) {
    localError.value = 'La longitud debe estar entre -180 y 180.';
    return;
  }

  localError.value = '';
  setCoordinates(lat, lng, true);
}

function submit() {
  localError.value = '';

  form.codigo_parcela = limpiarCodigo(form.codigo_parcela).trim();

  if (!form.id_productor) {
    return showError('Debe seleccionar un productor.');
  }

  if (!form.codigo_parcela) {
    return showError('El código de parcela es obligatorio.');
  }

  if (!/^[A-Z0-9-]+$/.test(form.codigo_parcela)) {
    return showError('El código de parcela solo debe contener letras, números y guiones. Ejemplo: P-SUR-01.');
  }

  if (!form.latitud || !form.longitud) {
    return showError('La latitud y longitud son obligatorias para ubicar la parcela en el mapa.');
  }

  const lat = Number(form.latitud);
  const lng = Number(form.longitud);

  if (Number.isNaN(lat) || lat < -90 || lat > 90) {
    return showError('La latitud debe estar entre -90 y 90.');
  }

  if (Number.isNaN(lng) || lng < -180 || lng > 180) {
    return showError('La longitud debe estar entre -180 y 180.');
  }

  if (
    form.superficie_ha !== '' &&
    form.superficie_ha !== null &&
    Number(form.superficie_ha) <= 0
  ) {
    return showError('La superficie debe ser mayor a 0.');
  }

  if (
    form.altitud_msnm !== '' &&
    form.altitud_msnm !== null &&
    Number(form.altitud_msnm) < 0
  ) {
    return showError('La altitud no puede ser negativa.');
  }

  const payload = {
    id_productor: form.id_productor,
    codigo_parcela: form.codigo_parcela,
    nombre_referencial: form.nombre_referencial || null,
    superficie_ha:
      form.superficie_ha !== '' && form.superficie_ha !== null
        ? Number(form.superficie_ha)
        : null,
    altitud_msnm:
      form.altitud_msnm !== '' && form.altitud_msnm !== null
        ? Number(form.altitud_msnm)
        : null,
    tipo_suelo: form.tipo_suelo || null,
    pendiente: form.pendiente || null,
    sistema_riego: form.sistema_riego || 'Secano',
    latitud: lat,
    longitud: lng,
    coordenadas_texto: form.coordenadas_texto || null,
    observaciones: form.observaciones || null,
    estado: form.estado || 'Activa',
  };

  emit('save', payload);
}

function limpiarCodigo(value) {
  return String(value || '')
    .replace(/[^A-Za-z0-9-]/g, '')
    .toUpperCase()
    .slice(0, 30);
}

function soloNumeros(value, max = 5) {
  return String(value || '')
    .replace(/\D/g, '')
    .slice(0, max);
}

function nombreProductor(productor) {
  if (!productor) {
    return '—';
  }

  if (productor.nombre_completo) {
    return productor.nombre_completo;
  }

  return [
    productor.nombres,
    productor.apellido_paterno,
    productor.apellido_materno,
  ]
    .filter(Boolean)
    .join(' ') || '—';
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>