<template>
  <FormDrawer
    :model-value="modelValue"
    v-model:paso="paso"
    :titulo="productor ? 'Editar productor' : 'Nuevo productor'"
    :subtitulo="productor ? productor.nombre_completo : 'Primero sus datos personales y luego los agrícolas.'"
    :pasos="['Datos personales', 'Datos agrícolas']"
    :guardando="guardando"
    :texto-guardar="productor ? 'Guardar cambios' : 'Crear productor'"
    :validar-paso="validarPaso"
    @update:model-value="(v) => $emit('update:modelValue', v)"
    @guardar="guardar"
  >
    <p v-if="errorGeneral" class="pf-error" role="alert">{{ errorGeneral }}</p>

    <template v-if="paso === 1">
      <FormSection titulo="Nombre" :columnas="2">
        <div class="pf-ancho">
          <BaseInput v-model="form.nombres" etiqueta="Nombres" obligatorio maxlength="100" placeholder="Ej.: Humberto" :error="mensaje('nombres')" :mostrar-errores="intento" @blur="validarCampo('nombres')" />
        </div>
        <BaseInput v-model="form.apellido_paterno" etiqueta="Apellido paterno" obligatorio maxlength="100" :error="mensaje('apellido_paterno')" :mostrar-errores="intento" @blur="validarCampo('apellido_paterno')" />
        <BaseInput v-model="form.apellido_materno" etiqueta="Apellido materno" maxlength="100" :error="mensaje('apellido_materno')" :mostrar-errores="intento" @blur="validarCampo('apellido_materno')" />
      </FormSection>

      <FormSection titulo="Carnet de identidad" :columnas="3">
        <BaseInput v-model="form.carnet_identidad" etiqueta="Número" obligatorio inputmode="numeric" maxlength="10" placeholder="4876512" :error="mensaje('carnet_identidad')" :mostrar-errores="intento" @blur="soloDigitosCi" />
        <BaseSelect v-model="form.ci_extension" etiqueta="Extensión" obligatorio :opciones="EXTENSIONES" :error="mensaje('ci_extension')" :mostrar-errores="intento" />
        <BaseInput v-model="form.complemento_ci" etiqueta="Complemento" maxlength="10" placeholder="1A" ayuda="Solo si lo tiene." :error="mensaje('complemento_ci')" :mostrar-errores="intento" @blur="mayusculasComplemento" />
      </FormSection>

      <FormSection titulo="Contacto y datos personales" :columnas="2">
        <BaseInput v-model="form.telefono" etiqueta="Teléfono" inputmode="numeric" maxlength="8" placeholder="71234567" ayuda="8 dígitos, sin espacios." :error="mensaje('telefono')" :mostrar-errores="intento" @blur="validarCampo('telefono')" />
        <BaseInput v-model="form.fecha_nacimiento" type="date" etiqueta="Fecha de nacimiento" :min="'1900-01-01'" :max="maxNacimiento" ayuda="Debe ser mayor de 18 años." :error="mensaje('fecha_nacimiento')" :mostrar-errores="intento" @blur="validarCampo('fecha_nacimiento')" />
        <div class="pf-ancho">
          <BaseRadioCards v-model="form.sexo" etiqueta="Sexo" :opciones="SEXOS" :columnas="3" />
        </div>
      </FormSection>
    </template>

    <template v-else>
      <FormSection titulo="Comunidad y terreno" :columnas="2">
        <div class="pf-ancho">
          <BaseSelect
            v-model="form.id_comunidad"
            etiqueta="Comunidad"
            obligatorio
            :opciones="opcionesComunidad"
            placeholder="Escribe para buscar la comunidad"
            :error="mensaje('id_comunidad')"
            :mostrar-errores="intento"
          />
        </div>
        <BaseInput v-model="form.superficie_total_ha" etiqueta="Superficie total declarada" unidad="ha" inputmode="decimal" placeholder="3,5" ayuda="Entre 0 y 5.000 ha. Limita la suma de sus parcelas." :error="mensaje('superficie_total_ha')" :mostrar-errores="intento" @blur="validarCampo('superficie_total_ha')" />
        <BaseInput v-model="form.direccion" etiqueta="Dirección" maxlength="255" placeholder="Zona, calle o referencia" :error="mensaje('direccion')" :mostrar-errores="intento" @blur="validarCampo('direccion')" />
      </FormSection>

      <FormSection titulo="Notas">
        <BaseInput v-model="form.observaciones" etiqueta="Observaciones" multilinea :filas="3" />
      </FormSection>
    </template>
  </FormDrawer>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import FormDrawer from '@/components/ui/FormDrawer.vue';
import FormSection from '@/components/ui/FormSection.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import BaseRadioCards from '@/components/ui/BaseRadioCards.vue';
import { alfanumerico, ciDigitos, digitosExactos, fechaNacimientoAdulta, longitud, numeroEntre, requerido, soloLetras, unoDe, useValidacion } from '@/utils/validaciones';

// Crear y editar productores en 2 pasos. Mismas reglas que StoreProductorRequest /
// UpdateProductorRequest; los errores del servidor se muestran bajo cada campo.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  productor: { type: Object, default: null },
  comunidades: { type: Array, default: () => [] },
  guardando: { type: Boolean, default: false },
  erroresServidor: { type: Object, default: () => ({}) },
  errorGeneral: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'guardar']);

const EXT = { LP: 'La Paz', CB: 'Cochabamba', SC: 'Santa Cruz', OR: 'Oruro', PT: 'Potosí', CH: 'Chuquisaca', TJ: 'Tarija', BE: 'Beni', PD: 'Pando' };
const EXTENSIONES = Object.entries(EXT).map(([valor, detalle]) => ({ valor, etiqueta: valor, detalle }));
const SEXOS = [
  { valor: 'M', titulo: 'Masculino' },
  { valor: 'F', titulo: 'Femenino' },
  { valor: 'Otro', titulo: 'Otro' },
];

const PASO1 = ['nombres', 'apellido_paterno', 'apellido_materno', 'carnet_identidad', 'ci_extension', 'complemento_ci', 'telefono', 'fecha_nacimiento'];
const PASO2 = ['id_comunidad', 'superficie_total_ha', 'direccion'];

const vacio = () => ({
  id_comunidad: null, nombres: '', apellido_paterno: '', apellido_materno: '', carnet_identidad: '', ci_extension: '', complemento_ci: '',
  telefono: '', sexo: null, fecha_nacimiento: '', direccion: '', superficie_total_ha: '', observaciones: '',
});

const form = reactive(vacio());
const paso = ref(1);
const intento = ref(false);

const esquema = {
  nombres: [requerido('Escribe los nombres del productor.'), longitud(3, 100, 'Los nombres'), soloLetras('Los nombres')],
  apellido_paterno: [requerido('Escribe el apellido paterno.'), longitud(3, 100, 'El apellido paterno'), soloLetras('El apellido paterno')],
  apellido_materno: [longitud(3, 100, 'El apellido materno'), soloLetras('El apellido materno')],
  carnet_identidad: [requerido('Escribe el número de carnet de identidad.'), ciDigitos()],
  ci_extension: [requerido('Elige la extensión del carnet (por ejemplo LP).'), unoDe(Object.keys(EXT), 'La extensión del CI no es válida.')],
  complemento_ci: [alfanumerico(10, 'El complemento solo admite letras y números (máximo 10).')],
  telefono: [digitosExactos(8, 'El teléfono debe tener exactamente 8 dígitos, sin espacios (por ejemplo 71234567).')],
  fecha_nacimiento: [fechaNacimientoAdulta()],
  id_comunidad: [(v) => (v ? '' : 'Elige la comunidad donde vive o trabaja el productor.')],
  superficie_total_ha: [numeroEntre(0, 5000, 'La superficie total', 'ha')],
  direccion: [longitud(3, 255, 'La dirección')],
};

const { errores, validarCampo, limpiar } = useValidacion(form, esquema);
const mensaje = (campo) => errores[campo] || props.erroresServidor?.[campo] || '';

const maxNacimiento = computed(() => {
  const d = new Date();

  d.setFullYear(d.getFullYear() - 18);

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const opcionesComunidad = computed(() =>
  props.comunidades.filter((c) => c.activo !== false).map((c) => ({ valor: c.id, etiqueta: c.nombre_comunidad, detalle: [c.municipio, c.provincia].filter(Boolean).join(' · ') }))
);

watch(
  () => [props.modelValue, props.productor],
  ([abierto]) => {
    if (!abierto) return;

    paso.value = 1;
    intento.value = false;
    limpiar();

    const p = props.productor;

    Object.assign(form, p
      ? {
          id_comunidad: p.id_comunidad ?? null,
          nombres: p.nombres || '',
          apellido_paterno: p.apellido_paterno || '',
          apellido_materno: p.apellido_materno || '',
          carnet_identidad: p.carnet_identidad || '',
          ci_extension: p.ci_extension || '',
          complemento_ci: p.complemento_ci || '',
          telefono: p.telefono || '',
          sexo: p.sexo || null,
          fecha_nacimiento: p.fecha_nacimiento ? String(p.fecha_nacimiento).slice(0, 10) : '',
          direccion: p.direccion || '',
          superficie_total_ha: p.superficie_total_ha ?? '',
          observaciones: p.observaciones || '',
        }
      : vacio());
  },
  { immediate: true }
);

// Si un campo con error cambia, se vuelve a evaluar.
watch(
  form,
  () => {
    for (const campo of Object.keys(esquema)) {
      if (errores[campo]) validarCampo(campo);
    }
  },
  { deep: true }
);

// Si el servidor rechaza un campo del paso 1 estando en el paso 2, se vuelve al paso con el error.
watch(
  () => props.erroresServidor,
  (e) => {
    const campos = Object.keys(e || {});

    if (campos.length) intento.value = true; // que los mensajes del servidor se vean bajo cada campo

    if (campos.some((c) => PASO1.includes(c))) paso.value = 1;
    else if (campos.some((c) => PASO2.includes(c))) paso.value = 2;
  }
);

function soloDigitosCi() {
  form.carnet_identidad = String(form.carnet_identidad).replace(/\D/g, '');
  validarCampo('carnet_identidad');
}

function mayusculasComplemento() {
  form.complemento_ci = String(form.complemento_ci).toUpperCase();
  validarCampo('complemento_ci');
}

function validarPaso(actual) {
  intento.value = true;

  const campos = actual === 1 ? PASO1 : PASO2;
  let ok = true;

  for (const c of campos) ok = validarCampo(c) && ok;

  if (ok) intento.value = false;

  return ok;
}

const numeroONulo = (v) => {
  const t = String(v ?? '').trim().replace(',', '.');

  return t === '' ? null : Number(t);
};

const texto = (v) => String(v ?? '').replace(/\s{2,}/g, ' ').trim();

function guardar() {
  intento.value = true;

  if (!validarPaso(1)) {
    paso.value = 1;
    return;
  }

  if (!validarPaso(2)) return;

  emit('guardar', {
    id_comunidad: form.id_comunidad,
    nombres: texto(form.nombres),
    apellido_paterno: texto(form.apellido_paterno),
    apellido_materno: texto(form.apellido_materno) || null,
    carnet_identidad: String(form.carnet_identidad).replace(/\D/g, ''),
    ci_extension: form.ci_extension,
    complemento_ci: texto(form.complemento_ci).toUpperCase() || null,
    telefono: texto(form.telefono) || null,
    sexo: form.sexo || null,
    fecha_nacimiento: form.fecha_nacimiento || null,
    direccion: texto(form.direccion) || null,
    superficie_total_ha: numeroONulo(form.superficie_total_ha),
    observaciones: String(form.observaciones ?? '').trim() || null,
    activo: props.productor ? Boolean(props.productor.activo) : true,
  });
}
</script>

<style scoped>
.pf-ancho {
  grid-column: 1 / -1;
}

.pf-error {
  margin: 0;
  padding: 12px 14px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}
</style>
