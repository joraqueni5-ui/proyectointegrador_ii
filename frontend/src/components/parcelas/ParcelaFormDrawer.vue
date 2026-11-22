<template>
  <FormDrawer
    :model-value="modelValue"
    v-model:paso="paso"
    :titulo="parcela ? 'Editar parcela' : 'Nueva parcela'"
    :subtitulo="parcela ? parcela.nombre_visible : 'En 3 pasos: productor y nombre, terreno y ubicación.'"
    :pasos="['Productor y nombre', 'Terreno', 'Ubicación']"
    :ancho="940"
    :guardando="guardando"
    :texto-guardar="parcela ? 'Guardar cambios' : 'Crear parcela'"
    :validar-paso="validarPaso"
    @update:model-value="(v) => $emit('update:modelValue', v)"
    @guardar="guardar"
  >
    <p v-if="errorGeneral" class="pf-error" role="alert">{{ errorGeneral }}</p>

    <!-- Paso 1 -->
    <template v-if="paso === 1">
      <FormSection titulo="Productor">
        <BaseSelect
          v-model="form.id_productor"
          etiqueta="Productor"
          obligatorio
          :opciones="opcionesProductor"
          :disabled="Boolean(parcela)"
          placeholder="Escribe el nombre o el carnet"
          :ayuda="parcela ? 'El productor no se cambia al editar.' : ''"
          :error="mensaje('id_productor')"
          :mostrar-errores="intento"
        />
        <p v-if="productor" class="pf-nota">
          <i class="ti ti-ruler-2" aria-hidden="true"></i>
          {{ productor.superficie_total_ha ? `Declara ${hectareas(productor.superficie_total_ha)} y ya usa ${hectareas(usadaPorOtras)} en otras parcelas.` : 'No declaró superficie total: no hay límite de superficie.' }}
        </p>
      </FormSection>

      <FormSection titulo="Nombre y código">
        <BaseInput
          v-model="form.nombre_referencial"
          etiqueta="Nombre de la parcela"
          :obligatorio="!parcela || !parcela.sin_nombre"
          maxlength="150"
          placeholder="Ej.: Lote Cruz Pata"
          :ayuda="parcela?.sin_nombre ? 'Esta parcela no tiene nombre: ponle uno para reconocerla fácilmente.' : 'Así se verá en listas, mapas y reportes.'"
          :error="mensaje('nombre_referencial')"
          :mostrar-errores="intento"
          @blur="validarCampo('nombre_referencial')"
        />
        <div v-if="sugerencias.length" class="pf-pildoras" role="group" aria-label="Sugerencias de nombre">
          <button v-for="s in sugerencias" :key="s" type="button" @click="form.nombre_referencial = s">{{ s }}</button>
        </div>

        <div class="pf-codigo">
          <div v-if="!editandoCodigo" class="pf-codigo__auto">
            <span class="pf-codigo__etiqueta">Código de la parcela</span>
            <span class="codigo">{{ generandoCodigo ? 'Generando código…' : form.codigo_parcela || (parcela ? '—' : 'Se genera al elegir el productor') }}</span>
            <BaseButton variante="fantasma" icono="ti-pencil" :disabled="!form.codigo_parcela && !parcela" @click="editandoCodigo = true">Editar</BaseButton>
          </div>
          <BaseInput
            v-else
            v-model="form.codigo_parcela"
            etiqueta="Código de la parcela"
            obligatorio
            maxlength="30"
            ayuda="Solo letras, números y guiones; debe ser único."
            :error="mensaje('codigo_parcela')"
            :mostrar-errores="intento"
            @blur="normalizarCodigo"
          />
          <p v-if="mensaje('codigo_parcela') && !editandoCodigo" class="pf-error-linea">{{ mensaje('codigo_parcela') }}</p>
        </div>
      </FormSection>
    </template>

    <!-- Paso 2 -->
    <template v-else-if="paso === 2">
      <FormSection titulo="Terreno" :columnas="2">
        <BaseInput
          v-model="form.superficie_ha"
          etiqueta="Superficie"
          obligatorio
          unidad="ha"
          inputmode="decimal"
          placeholder="1,5"
          :ayuda="libre !== null ? `Libre del productor: ${hectareas(libre)}.` : 'Entre 0,01 y 500 ha, máximo 2 decimales.'"
          :error="mensaje('superficie_ha')"
          :mostrar-errores="intento"
          @blur="validarCampo('superficie_ha')"
        />
        <BaseInput
          v-model="form.altitud_msnm"
          etiqueta="Altitud"
          unidad="m s. n. m."
          inputmode="numeric"
          placeholder="3860"
          :ayuda="altitudComunidad ? `La comunidad está a ${altitud(altitudComunidad)}.` : 'Entre 0 y 6.600. Si usas el GPS en el paso 3, se llena sola.'"
          :error="mensaje('altitud_msnm')"
          :mostrar-errores="intento"
          @blur="validarCampo('altitud_msnm')"
        />
      </FormSection>

      <FormSection>
        <BaseRadioCards v-model="form.sistema_riego" etiqueta="Sistema de riego" obligatorio :opciones="RIEGOS" :columnas="3" />
      </FormSection>

      <FormSection titulo="Detalles opcionales" :columnas="2">
        <div>
          <span class="pf-etiqueta">Pendiente</span>
          <div class="pf-pildoras pf-pildoras--grandes" role="group" aria-label="Pendiente del terreno">
            <button v-for="p in PENDIENTES" :key="p" type="button" :class="{ 'is-activa': form.pendiente === p }" :aria-pressed="form.pendiente === p" @click="form.pendiente = form.pendiente === p ? null : p">{{ p }}</button>
          </div>
        </div>
        <BaseSelect v-model="form.tipo_suelo" etiqueta="Tipo de suelo" limpiable :opciones="SUELOS" placeholder="Sin especificar" />
      </FormSection>
    </template>

    <!-- Paso 3 -->
    <template v-else>
      <FormSection titulo="Ubicación de la parcela">
        <MapaPicker
          v-model:lat="form.latitud"
          v-model:lng="form.longitud"
          :centro="centroComunidad"
          @altitud="alAltitudGps"
        />
      </FormSection>

      <FormSection :columnas="2">
        <BaseInput
          v-model="form.latitud"
          etiqueta="Latitud"
          obligatorio
          inputmode="decimal"
          placeholder="-16,5"
          ayuda="Dentro de Bolivia: entre -23 y -9."
          :error="mensaje('latitud')"
          :mostrar-errores="intento"
          @blur="validarCampo('latitud')"
        />
        <BaseInput
          v-model="form.longitud"
          etiqueta="Longitud"
          obligatorio
          inputmode="decimal"
          placeholder="-68,15"
          ayuda="Dentro de Bolivia: entre -70 y -57."
          :error="mensaje('longitud')"
          :mostrar-errores="intento"
          @blur="validarCampo('longitud')"
        />
      </FormSection>

      <FormSection>
        <BaseInput v-model="form.observaciones" etiqueta="Observaciones" multilinea :filas="2" />
      </FormSection>
    </template>

    <template #lateral>
      <p class="pf-previa-titulo">Así se verá en el sistema</p>
      <div class="pf-previa">
        <span class="pf-previa__foto"><i class="ti ti-leaf" aria-hidden="true"></i><i class="pf-previa__punto"></i></span>
        <div class="pf-previa__texto">
          <div class="pf-previa__fila">
            <strong :class="{ 'es-vacio': !form.nombre_referencial.trim() }">{{ form.nombre_referencial.trim() || 'Nombre de la parcela' }}</strong>
            <span>{{ supNumero ? hectareas(supNumero) : '— ha' }}</span>
          </div>
          <span class="pf-previa__sec">{{ productorCorto || 'Productor' }} <span class="codigo">{{ form.codigo_parcela || 'CÓDIGO' }}</span></span>
          <span class="pf-previa__sec">{{ form.sistema_riego }}{{ form.pendiente ? ` · pendiente ${form.pendiente.toLowerCase()}` : '' }}</span>
        </div>
      </div>

      <p class="pf-previa-titulo">Etiqueta completa</p>
      <p class="pf-previa-etiqueta">{{ etiquetaCompleta }}</p>
      <p class="pf-previa-ayuda">Así aparece en listas, alertas, reportes y PDF.</p>
    </template>
  </FormDrawer>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import FormDrawer from '@/components/ui/FormDrawer.vue';
import FormSection from '@/components/ui/FormSection.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseRadioCards from '@/components/ui/BaseRadioCards.vue';
import MapaPicker from '@/components/mapa/MapaPicker.vue';
import parcelaService from '@/services/modules/parcelaService';
import { longitud, numeroEntre, requerido, useValidacion } from '@/utils/validaciones';
import { altitud, hectareas } from '@/utils/formato';

// Crear y editar parcelas en 3 pasos con vista previa. Reglas = StoreParcelaRequest / UpdateParcelaRequest
// (nombre obligatorio solo al crear; superficie contra la libre del productor; coordenadas dentro de Bolivia).
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  parcela: { type: Object, default: null },
  productores: { type: Array, default: () => [] },
  guardando: { type: Boolean, default: false },
  erroresServidor: { type: Object, default: () => ({}) },
  errorGeneral: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'guardar']);

const RIEGOS = [
  { valor: 'Secano', titulo: 'Secano', descripcion: 'Solo agua de lluvia', icono: 'ti-cloud-rain' },
  { valor: 'Riego', titulo: 'Riego', descripcion: 'Canal, pozo o aspersión', icono: 'ti-droplet' },
  { valor: 'Mixto', titulo: 'Mixto', descripcion: 'Lluvia y riego de apoyo', icono: 'ti-cloud-storm' },
];
const PENDIENTES = ['Plano', 'Suave', 'Moderado', 'Fuerte'];
const SUELOS = ['Arcilloso', 'Limoso', 'Arenoso', 'Franco', 'Franco-arcilloso', 'Otro'].map((s) => ({ valor: s, etiqueta: s }));

const vacio = () => ({
  id_productor: null, nombre_referencial: '', codigo_parcela: '', superficie_ha: '', altitud_msnm: '', sistema_riego: 'Secano',
  pendiente: null, tipo_suelo: null, latitud: '', longitud: '', observaciones: '',
});

const form = reactive(vacio());
const paso = ref(1);
const intento = ref(false);
const editandoCodigo = ref(false);

// ---------- productor seleccionado y superficie libre
const productor = computed(() => props.productores.find((p) => p.id === form.id_productor) || null);
const usadaPorOtras = computed(() => {
  const usada = Number(productor.value?.superficie_parcelas_ha || 0);
  const propia = props.parcela && props.parcela.estado !== 'Inactiva' ? Number(props.parcela.superficie_ha || 0) : 0;

  return Math.max(0, +(usada - propia).toFixed(2));
});
const libre = computed(() => {
  const declarada = Number(productor.value?.superficie_total_ha || 0);

  return declarada > 0 ? Math.max(0, +(declarada - usadaPorOtras.value).toFixed(2)) : null;
});

const opcionesProductor = computed(() =>
  props.productores
    .filter((p) => p.activo || p.id === form.id_productor)
    .map((p) => ({
      valor: p.id,
      etiqueta: p.nombre_completo,
      detalle: [
        `CI ${p.carnet_identidad ?? ''} ${p.ci_extension ?? ''}`.trim(),
        p.comunidad?.nombre_comunidad,
        `${p.parcelas_count ?? 0} ${(p.parcelas_count ?? 0) === 1 ? 'parcela' : 'parcelas'}`,
        p.superficie_total_ha ? `${String(+Number(p.superficie_parcelas_ha || 0).toFixed(2)).replace('.', ',')} de ${String(+Number(p.superficie_total_ha)).replace('.', ',')} ha usadas` : null,
      ].filter(Boolean).join(' · '),
    }))
);

const productorCorto = computed(() => (productor.value ? [String(productor.value.nombres || '').split(' ')[0], productor.value.apellido_paterno].filter(Boolean).join(' ') || productor.value.nombre_completo : ''));
const nombreComunidad = computed(() => productor.value?.comunidad?.nombre_comunidad || '');
const altitudComunidad = computed(() => productor.value?.comunidad?.altitud_msnm || null);
const centroComunidad = computed(() => {
  const c = productor.value?.comunidad;

  return c && c.latitud !== null && c.latitud !== undefined && c.longitud !== null && c.longitud !== undefined && c.latitud !== '' ? [Number(c.latitud), Number(c.longitud)] : null;
});

const sugerencias = computed(() => {
  if (!productor.value) return [];

  return [...new Set(['Parcela principal', nombreComunidad.value && `Lote ${nombreComunidad.value}`, `Chacra de ${String(productor.value.nombres || '').split(' ')[0]}`, 'Ladera', 'Pampa'].filter((s) => s && s.trim().length >= 3))].slice(0, 5);
});

const supNumero = computed(() => Number(String(form.superficie_ha).replace(',', '.')) || 0);
const etiquetaCompleta = computed(() => {
  const nombre = form.nombre_referencial.trim() || 'Nombre de la parcela';

  return `${nombre} — ${productorCorto.value || 'Productor'}${nombreComunidad.value ? ` · ${nombreComunidad.value}` : ''}`;
});

// ---------- validaciones
const reglaSuperficie = (v) => {
  const t = String(v ?? '').trim();

  if (t === '') return 'Escribe la superficie en hectáreas (por ejemplo 1,5).';

  const n = Number(t.replace(',', '.'));

  if (!Number.isFinite(n)) return 'La superficie debe ser un número.';
  if (n < 0.01 || n > 500) return 'La superficie debe estar entre 0,01 y 500 ha.';
  if (!/^\d+([.,]\d{1,2})?$/.test(t)) return 'La superficie admite como máximo 2 decimales.';

  if (libre.value !== null && n > libre.value + 1e-9) {
    return `A este productor le quedan ${hectareas(libre.value)} libres (declara ${hectareas(productor.value.superficie_total_ha)} y ya usa ${hectareas(usadaPorOtras.value)} en otras parcelas). Reduce la superficie o actualiza la declarada.`;
  }

  return '';
};

const esquema = {
  id_productor: [(v) => (v ? '' : 'Elige al productor dueño de la parcela.')],
  nombre_referencial: [
    (v, f) => (!props.parcela && String(v ?? '').trim() === '' ? 'Escribe un nombre para reconocer la parcela (por ejemplo «Lote Cruz Pata»).' : ''),
    longitud(3, 150, 'El nombre'),
  ],
  codigo_parcela: [],
  superficie_ha: [reglaSuperficie],
  altitud_msnm: [
    numeroEntre(0, 6600, 'La altitud', 'm s. n. m.'),
    (v) => (String(v ?? '').trim() !== '' && !/^\d+$/.test(String(v).trim()) ? 'La altitud debe ser un número entero, sin decimales.' : ''),
  ],
  latitud: [(v) => (String(v ?? '').trim() === '' ? 'Marca el punto en el mapa o escribe la latitud.' : ''), numeroEntre(-23, -9, 'La latitud (dentro de Bolivia)')],
  longitud: [(v) => (String(v ?? '').trim() === '' ? 'Marca el punto en el mapa o escribe la longitud.' : ''), numeroEntre(-70, -57, 'La longitud (dentro de Bolivia)')],
};

// El código admite guiones: regla propia.
esquema.codigo_parcela = [
  requerido('Escribe el código de la parcela.'),
  (v) => (/^[A-Za-z0-9-]+$/.test(String(v ?? '').trim()) && String(v).trim().length <= 30 ? '' : 'El código solo admite letras, números y guiones (máximo 30).'),
];

const PASOS = { 1: ['id_productor', 'nombre_referencial', 'codigo_parcela'], 2: ['superficie_ha', 'altitud_msnm'], 3: ['latitud', 'longitud'] };

const { errores, validarCampo, limpiar } = useValidacion(form, esquema);
const mensaje = (campo) => errores[campo] || props.erroresServidor?.[campo] || '';

// ---------- apertura y valores iniciales
watch(
  () => [props.modelValue, props.parcela],
  ([abierto]) => {
    if (!abierto) return;

    paso.value = 1;
    intento.value = false;
    editandoCodigo.value = false;
    limpiar();

    const p = props.parcela;

    Object.assign(form, p
      ? {
          id_productor: p.id_productor, nombre_referencial: p.nombre_referencial || '', codigo_parcela: p.codigo_parcela || '',
          superficie_ha: p.superficie_ha ?? '', altitud_msnm: p.altitud_msnm ?? '', sistema_riego: p.sistema_riego || 'Secano',
          pendiente: p.pendiente || null, tipo_suelo: p.tipo_suelo || null, latitud: p.latitud ?? '', longitud: p.longitud ?? '',
          observaciones: p.observaciones || '',
        }
      : vacio());
  },
  { immediate: true }
);

// Código automático: solo para parcelas nuevas, cada vez que cambia el productor (si no se editó a mano).
const generandoCodigo = ref(false);
let codigoPendiente = null;

watch(
  () => form.id_productor,
  (id) => {
    if (props.parcela || !id || editandoCodigo.value) return;

    form.codigo_parcela = ''; // el código del productor anterior ya no sirve
    generandoCodigo.value = true;
    codigoPendiente = parcelaService
      .siguienteCodigo(id)
      .then(({ data }) => {
        if (form.id_productor === id && !editandoCodigo.value) form.codigo_parcela = data.codigo; // ignora respuestas viejas
      })
      .catch(() => {
        form.codigo_parcela = '';
        editandoCodigo.value = true; // si no se pudo generar, se escribe a mano
      })
      .finally(() => {
        generandoCodigo.value = false;
        codigoPendiente = null;
      });
  }
);

watch(
  form,
  () => {
    for (const campo of Object.keys(esquema)) {
      if (errores[campo]) validarCampo(campo);
    }
  },
  { deep: true }
);

// Los límites de superficie dependen del productor: se reevalúa al cambiarlo.
watch(() => form.id_productor, () => form.superficie_ha !== '' && errores.superficie_ha && validarCampo('superficie_ha'));

watch(
  () => props.erroresServidor,
  (e) => {
    const campos = Object.keys(e || {});

    if (!campos.length) return;

    intento.value = true;

    for (const n of [1, 2, 3]) {
      if (campos.some((c) => PASOS[n].includes(c))) {
        paso.value = n;
        if (campos.includes('codigo_parcela')) editandoCodigo.value = true;
        break;
      }
    }
  }
);

function normalizarCodigo() {
  form.codigo_parcela = String(form.codigo_parcela).trim().toUpperCase();
  validarCampo('codigo_parcela');
}

function alAltitudGps(metros) {
  if (String(form.altitud_msnm).trim() === '') form.altitud_msnm = String(metros);
}

async function validarPaso(actual) {
  if (codigoPendiente) await codigoPendiente; // el código automático puede estar llegando todavía

  intento.value = true;

  let ok = true;

  for (const c of PASOS[actual]) ok = validarCampo(c) && ok;

  if (ok) intento.value = false;

  return ok;
}

const numeroONulo = (v) => {
  const t = String(v ?? '').trim().replace(',', '.');

  return t === '' ? null : Number(t);
};

async function guardar() {
  intento.value = true;

  for (const n of [1, 2, 3]) {
    if (!(await validarPaso(n))) {
      paso.value = n;
      return;
    }
  }

  emit('guardar', {
    id_productor: form.id_productor,
    codigo_parcela: String(form.codigo_parcela).trim().toUpperCase(),
    nombre_referencial: form.nombre_referencial.replace(/\s{2,}/g, ' ').trim() || null,
    latitud: numeroONulo(form.latitud),
    longitud: numeroONulo(form.longitud),
    altitud_msnm: numeroONulo(form.altitud_msnm),
    superficie_ha: numeroONulo(form.superficie_ha),
    tipo_suelo: form.tipo_suelo || null,
    pendiente: form.pendiente || null,
    sistema_riego: form.sistema_riego,
    observaciones: String(form.observaciones ?? '').trim() || null,
    estado: props.parcela ? props.parcela.estado : 'Activa',
  });
}
</script>

<style scoped>
.pf-error {
  margin: 0;
  padding: 12px 14px;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}

.pf-nota {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 13px;
  color: #4a6070;
}

.pf-etiqueta {
  display: block;
  margin-bottom: 6px;
  font-size: 14px;
  font-weight: 700;
}

.pf-pildoras {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pf-pildoras button {
  min-height: 36px;
  padding: 0 14px;
  border: 2px solid #dce6ed;
  border-radius: 999px;
  background: #fff;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.pf-pildoras button:hover {
  border-color: #8ba3b5;
}

.pf-pildoras--grandes button {
  min-height: 44px;
}

.pf-pildoras button.is-activa {
  background: #0d3d2b;
  border-color: #0d3d2b;
  color: #fff;
}

.pf-codigo__auto {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 12px;
}

.pf-codigo__etiqueta {
  font-size: 13px;
  font-weight: 700;
  color: #4a6070;
}

.pf-error-linea {
  margin: 6px 0 0;
  font-size: 13px;
  font-weight: 600;
  color: #b91c1c;
}

.pf-previa-titulo {
  margin: 12px 0 8px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #4a6070;
}

.pf-previa {
  display: flex;
  gap: 10px;
  padding: 10px;
  background: #fff;
  border: 2px solid #0d3d2b;
  border-radius: 16px;
}

.pf-previa__foto {
  position: relative;
  flex: none;
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: #edfaf4;
  display: grid;
  place-items: center;
  color: #2e7d5a;
  font-size: 22px;
}

.pf-previa__punto {
  position: absolute;
  right: -3px;
  bottom: -3px;
  width: 14px;
  height: 14px;
  border-radius: 999px;
  background: #d97706;
  border: 3px solid #fff;
}

.pf-previa__texto {
  display: grid;
  align-content: center;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.pf-previa__fila {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  font-size: 13px;
}

.pf-previa__fila strong.es-vacio {
  color: #8ba3b5;
}

.pf-previa__fila span {
  font-weight: 700;
  color: #4a6070;
  white-space: nowrap;
}

.pf-previa__sec {
  font-size: 11px;
  color: #4a6070;
}

.pf-previa-etiqueta {
  margin: 0;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.pf-previa-ayuda {
  margin: 6px 0 0;
  font-size: 12px;
  color: #4a6070;
}
</style>
