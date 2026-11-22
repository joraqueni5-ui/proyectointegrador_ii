<template>
  <div class="pv" :class="{ 'mapa-abierto': mapaAbierto }">
    <!-- Columna izquierda: lista -->
    <section class="pv__lista" aria-label="Lista de parcelas">
      <header class="pv__cabeza">
        <div>
          <h1 class="pv__titulo">Parcelas</h1>
          <p class="pv__resumen">{{ resumen }}</p>
        </div>
        <div class="pv__acciones">
          <BaseButton class="pv__ver-mapa" variante="secundario" icono="ti-map-2" @click="mapaAbierto = true">Ver mapa</BaseButton>
          <BaseButton icono="ti-plus" @click="abrirCrear">Nueva</BaseButton>
        </div>
      </header>

      <div class="pv__filtros">
        <div class="pv__buscador">
          <i class="ti ti-search" aria-hidden="true"></i>
          <label class="pv__sr" for="pv-buscar">Buscar parcelas</label>
          <input id="pv-buscar" v-model="busqueda" type="search" autocomplete="off" maxlength="80" placeholder="Nombre, productor, comunidad o código" />
        </div>
        <div class="pv__pildoras" role="group" aria-label="Filtros rápidos">
          <button v-for="p in pildoras" :key="p.valor" type="button" :class="{ 'is-activa': filtro === p.valor }" :aria-pressed="filtro === p.valor" @click="filtro = p.valor">
            {{ p.etiqueta }} · {{ p.conteo }}
          </button>
        </div>
      </div>

      <p v-if="errorPagina" class="aviso-error" role="alert">{{ errorPagina }}</p>

      <div ref="contenedorLista" class="pv__scroll">
        <template v-if="cargando">
          <div v-for="n in 5" :key="n" class="pv__esqueleto"></div>
        </template>

        <EmptyState
          v-else-if="!grupos.length"
          icono="ti-map-off"
          :titulo="parcelas.length ? 'Ninguna parcela coincide con la búsqueda' : 'Todavía no hay parcelas'"
          :texto="parcelas.length ? 'Prueba con otro nombre o quita los filtros.' : 'Registra la primera parcela para ver su estado sanitario en el mapa.'"
        >
          <BaseButton v-if="!parcelas.length" icono="ti-plus" @click="abrirCrear">Nueva parcela</BaseButton>
        </EmptyState>

        <template v-else>
          <section v-for="g in grupos" :key="g.clave" class="pv__grupo">
            <h2>{{ g.titulo }}</h2>
            <div v-for="p in g.parcelas" :key="p.id" :data-id="p.id" :class="{ 'resaltada': resaltada === p.id }">
              <ParcelaCard :parcela="p" :seleccionada="seleccionadaId === p.id" @seleccionar="alSeleccionar" />
            </div>
          </section>
        </template>
      </div>
    </section>

    <!-- Derecha: mapa -->
    <section class="pv__mapa" aria-label="Mapa de sanidad">
      <button class="pv__cerrar-mapa" type="button" @click="mapaAbierto = false"><i class="ti ti-list" aria-hidden="true"></i> Ver lista</button>
      <ParcelasMapa
        ref="mapa"
        :parcelas="visibles"
        :seleccionada-id="seleccionadaId"
        @seleccionar="alSeleccionarEnMapa"
        @ver-ficha="abrirFicha"
        @programar-visita="programarVisita"
      />
    </section>

    <ParcelaFormDrawer
      v-model="formAbierto"
      :parcela="parcelaEditar"
      :productores="productores"
      :guardando="guardando"
      :errores-servidor="erroresServidor"
      :error-general="errorGeneral"
      @guardar="guardar"
    />

    <ParcelaFicha
      v-model="fichaAbierta"
      :parcela="fichaParcela"
      :eventos="eventos"
      :cargando-eventos="cargandoEventos"
      :etapas="etapas"
      @editar="editarDesdeFicha"
      @cambiar-estado="abrirEstado"
      @programar-visita="programarVisita"
    />

    <ParcelaEstadoDialog v-model="estadoAbierto" :parcela="parcelaEstado" :guardando="cambiandoEstado" :error="errorEstado" @confirmar="cambiarEstado" />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ParcelaCard from '@/components/parcelas/ParcelaCard.vue';
import ParcelasMapa from '@/components/parcelas/ParcelasMapa.vue';
import ParcelaFormDrawer from '@/components/parcelas/ParcelaFormDrawer.vue';
import ParcelaFicha from '@/components/parcelas/ParcelaFicha.vue';
import ParcelaEstadoDialog from '@/components/parcelas/ParcelaEstadoDialog.vue';
import parcelaService from '@/services/modules/parcelaService';
import productorService from '@/services/modules/productorService';
import catalogoAgricolaService from '@/services/modules/catalogoAgricolaService';
import { obtenerMensajeError } from '@/services/errorService';
import { useToast } from '@/composables/useToast';
import { normalizeList } from '@/utils/datos';
import { hectareas, numero } from '@/utils/formato';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const parcelas = ref([]);
const productores = ref([]);
const etapas = ref([]);
const cargando = ref(true);
const errorPagina = ref('');
const busqueda = ref(String(route.query.buscar || ''));
const filtro = ref('todas');
const seleccionadaId = ref(null);
const resaltada = ref(null);
const mapaAbierto = ref(false);
const contenedorLista = ref(null);
const mapa = ref(null);

// ---------- carga
async function cargar() {
  cargando.value = true;
  errorPagina.value = '';

  try {
    const { data } = await parcelaService.listar({ resumen: 1 });

    parcelas.value = normalizeList(data);
  } catch (e) {
    errorPagina.value = obtenerMensajeError(e, 'No se pudieron cargar las parcelas. Revisa tu conexión e intenta de nuevo.');
    parcelas.value = [];
  } finally {
    cargando.value = false;
  }
}

async function cargarApoyo() {
  const [p, e] = await Promise.allSettled([productorService.listar({ todos: 1 }), catalogoAgricolaService.etapasFenologicas()]);

  if (p.status === 'fulfilled') productores.value = normalizeList(p.value.data);
  if (e.status === 'fulfilled') etapas.value = normalizeList(e.value.data).sort((a, b) => a.orden - b.orden);
}

onMounted(async () => {
  await Promise.all([cargar(), cargarApoyo()]);

  const abrir = Number(route.query.parcela);

  if (abrir && parcelas.value.some((p) => p.id === abrir)) alSeleccionarEnMapa(parcelas.value.find((p) => p.id === abrir));
});

watch(() => route.query.buscar, (q) => (busqueda.value = String(q || '')));

// ---------- filtros y agrupación
const sinTildes = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const pildoras = computed(() => {
  const cuenta = (k) => parcelas.value.filter((p) => p.estado_sanitario === k).length;

  return [
    { valor: 'todas', etiqueta: 'Todas', conteo: parcelas.value.length },
    { valor: 'alerta', etiqueta: 'Con alerta', conteo: cuenta('alerta') },
    { valor: 'sin_visita', etiqueta: 'Sin visita +30 días', conteo: cuenta('sin_visita') },
    { valor: 'descanso', etiqueta: 'En descanso', conteo: cuenta('descanso') },
  ];
});

const visibles = computed(() => {
  const q = sinTildes(busqueda.value).trim();

  return parcelas.value.filter((p) => {
    if (filtro.value !== 'todas' && p.estado_sanitario !== filtro.value) return false;

    if (!q) return true;

    return sinTildes([p.nombre_visible, p.nombre_referencial, p.codigo_parcela, p.productor?.nombre_completo, p.productor?.comunidad?.nombre_comunidad].filter(Boolean).join(' ')).includes(q);
  });
});

const grupos = computed(() => {
  const mapaGrupos = new Map();

  for (const p of visibles.value) {
    const c = p.productor?.comunidad;
    const clave = c?.id ?? 0;

    if (!mapaGrupos.has(clave)) {
      mapaGrupos.set(clave, {
        clave,
        titulo: c ? [c.nombre_comunidad, c.municipio].filter(Boolean).join(' · ').toUpperCase() : 'SIN COMUNIDAD',
        parcelas: [],
      });
    }

    mapaGrupos.get(clave).parcelas.push(p);
  }

  return [...mapaGrupos.values()]
    .sort((a, b) => a.titulo.localeCompare(b.titulo, 'es'))
    .map((g) => ({ ...g, parcelas: g.parcelas.sort((a, b) => a.nombre_visible.localeCompare(b.nombre_visible, 'es')) }));
});

const resumen = computed(() => {
  if (cargando.value) return '';

  const ha = parcelas.value.reduce((s, p) => s + Number(p.superficie_ha || 0), 0);

  return `${numero(parcelas.value.length, { decimales: 0 })} parcelas · ${hectareas(ha)}`;
});

// ---------- selección (lista <-> mapa)
const esMovil = () => window.matchMedia('(max-width: 767px)').matches;

function alSeleccionar(p) {
  seleccionadaId.value = p.id;

  // En celular no hay mapa a la vista: tocar una tarjeta abre su ficha.
  if (esMovil()) abrirFicha(p);
}

async function alSeleccionarEnMapa(p) {
  seleccionadaId.value = p.id;
  await nextTick();
  contenedorLista.value?.querySelector(`[data-id="${p.id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function programarVisita(p) {
  router.push({ path: '/dashboard/inspecciones', query: { parcela: p.id } });
}

// ---------- ficha
const fichaAbierta = ref(false);
const fichaParcela = ref(null);
const eventos = ref([]);
const cargandoEventos = ref(false);

async function abrirFicha(p) {
  fichaParcela.value = p;
  fichaAbierta.value = true;
  eventos.value = [];
  cargandoEventos.value = true;

  const [det, tl] = await Promise.allSettled([parcelaService.obtener(p.id), parcelaService.lineaTiempo(p.id, { limite: 40 })]);

  if (det.status === 'fulfilled') fichaParcela.value = { ...p, ...det.value.data };
  else toast.error(obtenerMensajeError(det.reason, 'No se pudo cargar la ficha de la parcela.'));

  if (tl.status === 'fulfilled') eventos.value = tl.value.data || [];

  cargandoEventos.value = false;
}

function editarDesdeFicha(p) {
  fichaAbierta.value = false;
  abrirEditar(p);
}

// ---------- crear / editar
const formAbierto = ref(false);
const parcelaEditar = ref(null);
const guardando = ref(false);
const erroresServidor = ref({});
const errorGeneral = ref('');

function abrirCrear() {
  parcelaEditar.value = null;
  erroresServidor.value = {};
  errorGeneral.value = '';
  formAbierto.value = true;
}

function abrirEditar(p) {
  parcelaEditar.value = p;
  erroresServidor.value = {};
  errorGeneral.value = '';
  formAbierto.value = true;
}

async function guardar(payload) {
  if (guardando.value) return;

  guardando.value = true;
  erroresServidor.value = {};
  errorGeneral.value = '';

  try {
    const editando = parcelaEditar.value;
    const { data } = editando ? await parcelaService.actualizar(editando.id, payload) : await parcelaService.crear(payload);

    await Promise.all([cargar(), cargarApoyo()]);
    formAbierto.value = false;

    const id = data?.id ?? editando?.id;

    filtro.value = 'todas';
    busqueda.value = '';
    seleccionadaId.value = id;
    resaltada.value = id;
    await nextTick();
    contenedorLista.value?.querySelector(`[data-id="${id}"]`)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    setTimeout(() => (resaltada.value = null), 2200);
    toast.exito(`Parcela «${payload.nombre_referencial || payload.codigo_parcela}» ${editando ? 'actualizada' : 'creada'}`);
  } catch (e) {
    const errores = e?.response?.data?.errors;

    if (errores) {
      erroresServidor.value = Object.fromEntries(Object.entries(errores).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
      errorGeneral.value = 'Revisa los campos marcados en rojo.';
    } else {
      errorGeneral.value = obtenerMensajeError(e, 'No se pudo guardar la parcela. Intenta de nuevo.');
    }
  } finally {
    guardando.value = false;
  }
}

// ---------- estado
const estadoAbierto = ref(false);
const parcelaEstado = ref(null);
const cambiandoEstado = ref(false);
const errorEstado = ref('');

function abrirEstado(p) {
  parcelaEstado.value = p;
  errorEstado.value = '';
  estadoAbierto.value = true;
}

async function cambiarEstado(estado) {
  if (cambiandoEstado.value) return;

  cambiandoEstado.value = true;
  errorEstado.value = '';

  try {
    await parcelaService.cambiarEstado(parcelaEstado.value.id, estado);
    await cargar();
    estadoAbierto.value = false;
    fichaAbierta.value = false;
    resaltada.value = parcelaEstado.value.id;
    setTimeout(() => (resaltada.value = null), 2200);
    toast.exito(`Parcela «${parcelaEstado.value.nombre_visible}» ahora está ${estado === 'En_descanso' ? 'en descanso' : estado.toLowerCase()}`);
  } catch (e) {
    errorEstado.value = obtenerMensajeError(e, 'No se pudo cambiar el estado de la parcela.');
  } finally {
    cambiandoEstado.value = false;
  }
}

watch(mapaAbierto, async (abierto) => {
  if (abierto) {
    await nextTick();
    mapa.value?.invalidar();
  }
});
</script>

<style scoped>
.pv {
  display: flex;
  height: calc(100vh - var(--barra-superior-h));
  background: #fff;
}

.pv__lista {
  flex: 0 0 clamp(440px, 38vw, 560px);
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: #fff;
  border-right: 1px solid #dce6ed;
}

.pv__cabeza {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 20px 10px;
}

.pv__titulo {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.pv__resumen {
  margin: 2px 0 0;
  font-size: 13px;
  color: #4a6070;
}

.pv__acciones {
  display: flex;
  gap: 8px;
}

.pv__ver-mapa {
  display: none;
}

.pv__filtros {
  display: grid;
  gap: 10px;
  padding: 0 20px 12px;
}

.pv__buscador {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 46px;
  padding: 0 14px;
  background: #f4f7f9;
  border: 2px solid transparent;
  border-radius: 12px;
}

.pv__buscador:focus-within {
  background: #fff;
  border-color: #3da87a;
  box-shadow: 0 0 0 4px rgba(61, 168, 122, 0.14);
}

.pv__buscador input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-family: inherit;
  font-size: 14px;
}

.pv__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.pv__pildoras {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding: 2px;
}

.pv__pildoras::-webkit-scrollbar {
  display: none;
}

.pv__pildoras button {
  flex: none;
  min-height: 40px;
  padding: 0 14px;
  background: #fff;
  border: 2px solid #dce6ed;
  border-radius: 999px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.pv__pildoras button.is-activa {
  background: #0d3d2b;
  border-color: #0d3d2b;
  color: #fff;
}

.pv__pildoras button:focus-visible {
  outline: 3px solid #3da87a;
  outline-offset: 2px;
}

.pv__scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 0 20px 24px;
  background: #fff;
}

.pv__grupo {
  display: grid;
  gap: 8px;
  margin-bottom: 18px;
}

.pv__grupo h2 {
  margin: 8px 0 0;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.06em;
  color: #4a6070;
}

.resaltada {
  animation: pv-resalta 2s ease both;
  border-radius: 18px;
}

@keyframes pv-resalta {
  0%,
  70% {
    background: #a8e6c6;
  }

  100% {
    background: transparent;
  }
}

.pv__esqueleto {
  height: 84px;
  margin-bottom: 8px;
  border-radius: 16px;
  background: linear-gradient(90deg, #eef2f6 25%, #f8fafc 50%, #eef2f6 75%);
  background-size: 200% 100%;
  animation: pv-brillo 1.2s linear infinite;
}

@keyframes pv-brillo {
  to {
    background-position: -200% 0;
  }
}

.pv__mapa {
  position: relative;
  flex: 1;
  min-width: 0;
}

.pv__cerrar-mapa {
  display: none;
}

/* Celular: solo la lista; el mapa se abre a pantalla completa con «Ver mapa» */
@media (max-width: 767px) {
  .pv {
    height: calc(100vh - 64px - var(--barra-inferior-h));
  }

  .pv__lista {
    flex: 1;
    border-right: 0;
  }

  .pv__ver-mapa {
    display: inline-flex;
  }

  .pv__mapa {
    display: none;
  }

  .pv.mapa-abierto .pv__mapa {
    display: block;
    position: fixed;
    inset: 64px 0 var(--barra-inferior-h) 0;
    z-index: 70;
    background: #dfe9e3;
  }

  .pv.mapa-abierto .pv__cerrar-mapa {
    position: absolute;
    left: 12px;
    top: 12px;
    z-index: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    padding: 0 16px;
    border: 0;
    border-radius: 12px;
    background: #0d3d2b;
    color: #fff;
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
  }

  .pv__cabeza,
  .pv__filtros,
  .pv__scroll {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
