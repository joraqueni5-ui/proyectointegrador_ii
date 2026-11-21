<template>
  <FormDrawer
    :model-value="modelValue"
    :titulo="comunidad ? 'Editar comunidad' : 'Nueva comunidad'"
    :subtitulo="comunidad ? comunidad.nombre_comunidad : 'Registra el lugar donde trabajan los productores.'"
    :guardando="guardando"
    :texto-guardar="comunidad ? 'Guardar cambios' : 'Crear comunidad'"
    :validar-paso="validar"
    @update:model-value="(v) => $emit('update:modelValue', v)"
    @guardar="guardar"
  >
    <p v-if="errorGeneral" class="cf-error" role="alert">{{ errorGeneral }}</p>

    <FormSection titulo="Datos de la comunidad" :columnas="2">
      <div class="cf-ancho">
        <BaseInput
          v-model="form.nombre_comunidad"
          etiqueta="Nombre de la comunidad"
          obligatorio
          maxlength="150"
          placeholder="Ej.: Chiarpata"
          :error="mensaje('nombre_comunidad')"
          :mostrar-errores="intento"
          @blur="validarCampo('nombre_comunidad')"
        />
      </div>
      <BaseInput
        v-model="form.municipio"
        etiqueta="Municipio"
        obligatorio
        maxlength="100"
        placeholder="Ej.: Achacachi"
        :error="mensaje('municipio')"
        :mostrar-errores="intento"
        @blur="validarCampo('municipio')"
      />
      <BaseInput
        v-model="form.provincia"
        etiqueta="Provincia"
        maxlength="100"
        placeholder="Ej.: Omasuyos"
        :error="mensaje('provincia')"
        :mostrar-errores="intento"
        @blur="validarCampo('provincia')"
      />
      <div class="cf-ancho">
        <BaseSelect
          v-model="form.departamento"
          etiqueta="Departamento"
          obligatorio
          :opciones="DEPARTAMENTOS"
          :error="mensaje('departamento')"
          :mostrar-errores="intento"
        />
      </div>
    </FormSection>

    <FormSection titulo="Ubicación">
      <MapaPicker
        v-model:lat="form.latitud"
        v-model:lng="form.longitud"
        @altitud="(m) => (form.altitud_msnm = String(m))"
      />
    </FormSection>

    <FormSection :columnas="3">
      <BaseInput
        v-model="form.latitud"
        etiqueta="Latitud"
        inputmode="decimal"
        placeholder="-16,5"
        ayuda="Entre -23 y -9."
        :error="mensaje('latitud')"
        :mostrar-errores="intento"
        @blur="validarCampo('latitud')"
      />
      <BaseInput
        v-model="form.longitud"
        etiqueta="Longitud"
        inputmode="decimal"
        placeholder="-68,15"
        ayuda="Entre -70 y -57."
        :error="mensaje('longitud')"
        :mostrar-errores="intento"
        @blur="validarCampo('longitud')"
      />
      <BaseInput
        v-model="form.altitud_msnm"
        etiqueta="Altitud"
        unidad="m s. n. m."
        inputmode="numeric"
        placeholder="3860"
        ayuda="Entre 0 y 6.600."
        :error="mensaje('altitud_msnm')"
        :mostrar-errores="intento"
        @blur="validarCampo('altitud_msnm')"
      />
    </FormSection>
  </FormDrawer>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import FormDrawer from '@/components/ui/FormDrawer.vue';
import FormSection from '@/components/ui/FormSection.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import MapaPicker from '@/components/mapa/MapaPicker.vue';
import { longitud, numeroEntre, requerido, useValidacion } from '@/utils/validaciones';

// Crear y editar comunidades en un panel lateral. Las reglas son las mismas que las del backend
// (StoreComunidadRequest / UpdateComunidadRequest); si el servidor rechaza algo, el mensaje se
// muestra bajo el campo (`erroresServidor`).
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  comunidad: { type: Object, default: null },
  guardando: { type: Boolean, default: false },
  erroresServidor: { type: Object, default: () => ({}) },
  errorGeneral: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'guardar']);

const DEPARTAMENTOS = ['La Paz', 'Cochabamba', 'Potosí', 'Oruro', 'Santa Cruz', 'Tarija', 'Beni', 'Pando', 'Chuquisaca'].map((d) => ({ valor: d, etiqueta: d }));

const vacio = () => ({ nombre_comunidad: '', municipio: '', provincia: '', departamento: 'La Paz', latitud: '', longitud: '', altitud_msnm: '' });

const form = reactive(vacio());
const intento = ref(false);

const esquema = {
  nombre_comunidad: [requerido('Escribe el nombre de la comunidad.'), longitud(0, 150, 'El nombre')],
  municipio: [requerido('Escribe el municipio.'), longitud(3, 100, 'El municipio')],
  provincia: [longitud(3, 100, 'La provincia')],
  departamento: [requerido('Elige un departamento.')],
  latitud: [numeroEntre(-23, -9, 'La latitud (dentro de Bolivia)')],
  longitud: [numeroEntre(-70, -57, 'La longitud (dentro de Bolivia)')],
  altitud_msnm: [
    numeroEntre(0, 6600, 'La altitud'),
    (v) => (String(v ?? '').trim() !== '' && !/^\d+$/.test(String(v).trim()) ? 'La altitud debe ser un número entero, sin decimales.' : ''),
  ],
};

const { errores, validarCampo, validarTodo, limpiar } = useValidacion(form, esquema);

const mensaje = (campo) => errores[campo] || props.erroresServidor?.[campo] || '';

watch(
  () => [props.modelValue, props.comunidad],
  ([abierto]) => {
    if (!abierto) return;

    intento.value = false;
    limpiar();

    const c = props.comunidad;

    Object.assign(form, c
      ? {
          nombre_comunidad: c.nombre_comunidad || '',
          municipio: c.municipio || '',
          provincia: c.provincia || '',
          departamento: c.departamento || 'La Paz',
          latitud: c.latitud ?? '',
          longitud: c.longitud ?? '',
          altitud_msnm: c.altitud_msnm ?? '',
        }
      : vacio());
  },
  { immediate: true }
);

// Si un campo ya tenía error y cambia (por escribir, por el mapa o por el GPS), se vuelve a evaluar.
watch(
  form,
  () => {
    for (const campo of Object.keys(esquema)) {
      if (errores[campo]) validarCampo(campo);
    }
  },
  { deep: true }
);

function texto(v) {
  return String(v ?? '').replace(/\s{2,}/g, ' ').trim();
}

function validar() {
  intento.value = true;

  return validarTodo();
}

function numeroONulo(v) {
  const t = String(v ?? '').trim().replace(',', '.');

  return t === '' ? null : Number(t);
}

function guardar() {
  if (!validar()) return;

  emit('guardar', {
    nombre_comunidad: texto(form.nombre_comunidad),
    municipio: texto(form.municipio),
    provincia: texto(form.provincia) || null,
    departamento: form.departamento,
    latitud: numeroONulo(form.latitud),
    longitud: numeroONulo(form.longitud),
    altitud_msnm: numeroONulo(form.altitud_msnm),
    activo: props.comunidad ? Boolean(props.comunidad.activo) : true,
  });
}
</script>

<style scoped>
.cf-ancho {
  grid-column: 1 / -1;
}

.cf-error {
  margin: 0;
  padding: 12px 14px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}
</style>
