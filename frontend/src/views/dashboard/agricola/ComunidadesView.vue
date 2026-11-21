<template>
  <div class="pagina">
    <PageHeader
      titulo="Comunidades"
      :resumen="resumen"
      :migas="[{ texto: 'Inicio', to: '/dashboard' }, { texto: 'Campo' }, { texto: 'Comunidades' }]"
    >
      <div class="pagina-acciones">
        <BaseButton variante="secundario" icono="ti-file-spreadsheet" :disabled="!filtradas.length" @click="exportar('excel')">Excel</BaseButton>
        <BaseButton variante="secundario" icono="ti-file-type-pdf" :disabled="!filtradas.length" @click="exportar('pdf')">PDF</BaseButton>
        <BaseButton icono="ti-plus" @click="abrirCrear">Nueva comunidad</BaseButton>
      </div>
    </PageHeader>

    <p v-if="errorPagina" class="aviso-error" role="alert">{{ errorPagina }}</p>

    <div class="rejilla-kpi">
      <KpiCard etiqueta="Comunidades activas" :valor="numero(activas, { decimales: 0 })" :contexto="`de ${comunidades.length} registradas`" icono="ti-building-community" />
      <KpiCard etiqueta="Productores" :valor="numero(totalProductores, { decimales: 0 })" contexto="en comunidades activas" icono="ti-users" />
      <KpiCard
        etiqueta="Parcelas"
        :valor="numero(totalParcelas, { decimales: 0 })"
        :contexto="totalConAlerta > 0 ? `${totalConAlerta} con alerta activa` : 'ninguna con alerta activa'"
        :alerta="totalConAlerta > 0"
        icono="ti-map-2"
      />
      <KpiCard etiqueta="Superficie total" :valor="hectareas(totalSuperficie)" contexto="de parcelas no inactivas" icono="ti-ruler-2" />
    </div>

    <FilterBar
      v-model:busqueda="busqueda"
      v-model:activo="filtro"
      placeholder="Comunidad, municipio, provincia o departamento"
      :filtros="pildoras"
      :filtros-extra="departamento ? 1 : 0"
    >
      <template #mas>
        <BaseSelect v-model="departamento" etiqueta="Departamento" limpiable :opciones="opcionesDepartamento" placeholder="Todos los departamentos" />
      </template>
    </FilterBar>

    <DataTable
      :columnas="columnas"
      :filas="filtradas"
      :total="comunidades.length"
      :por-pagina="1000"
      :cargando="cargando"
      :acciones="acciones"
      :resaltar-id="resaltado"
      vacio-icono="ti-building-community"
      :vacio-titulo="comunidades.length ? 'Ninguna comunidad coincide con la búsqueda' : 'Todavía no hay comunidades'"
      :vacio-texto="comunidades.length ? 'Prueba con otro nombre o quita los filtros.' : 'Registra la primera comunidad para poder asociar productores y parcelas.'"
      @ver="abrirDetalle"
      @accion="alAccion"
    >
      <template #celda-nombre_comunidad="{ fila }">
        <strong class="vc-nombre">{{ fila.nombre_comunidad }}</strong>
        <span class="vc-secundario">{{ [fila.municipio, fila.provincia].filter(Boolean).join(' · ') || '—' }}</span>
        <button v-if="!tieneUbicacion(fila)" type="button" class="vc-sin-ubicacion" @click="abrirEditar(fila)">Sin ubicación · completar</button>
      </template>
      <template #celda-productores_count="{ fila }">{{ numero(fila.productores_count ?? 0, { decimales: 0 }) }}</template>
      <template #celda-parcelas_count="{ fila }">{{ numero(fila.parcelas_count ?? 0, { decimales: 0 }) }}</template>
      <template #celda-superficie_total_ha="{ fila }">{{ hectareas(fila.superficie_total_ha ?? 0) }}</template>
      <template #celda-altitud_msnm="{ fila }">{{ fila.altitud_msnm ? altitud(fila.altitud_msnm) : '—' }}</template>
      <template #celda-parcelas_con_alerta="{ fila }">
        <span :class="['vc-alerta', { 'hay-alerta': fila.parcelas_con_alerta > 0 }]">{{ numero(fila.parcelas_con_alerta ?? 0, { decimales: 0 }) }}</span>
      </template>
      <template #celda-activo="{ fila }"><StatusBadge :estado="fila.activo ? 'Activa' : 'Inactiva'" /></template>
    </DataTable>

    <ComunidadFormDrawer
      v-model="formAbierto"
      :comunidad="comunidadEditar"
      :guardando="guardando"
      :errores-servidor="erroresServidor"
      :error-general="errorGeneral"
      @guardar="guardar"
    />

    <ComunidadDetailDrawer v-model="detalleAbierto" :comunidad="comunidadDetalle" @editar="editarDesdeDetalle" @cambiar-estado="pedirCambioEstado" />

    <ConfirmDialog
      v-model="confirmarAbierto"
      :titulo="`¿Desactivar la comunidad ${comunidadConfirmar?.nombre_comunidad || ''}?`"
      texto-confirmar="Sí, desactivar"
      texto-cargando="Desactivando…"
      :cargando="cambiandoEstado"
      @confirmar="desactivar"
    >
      Dejará de aparecer en los formularios nuevos. No se borra ningún dato y se puede volver a activar cuando quieras. Si todavía tiene
      productores activos, el sistema no lo permitirá.
    </ConfirmDialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHeader from '@/components/ui/PageHeader.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import KpiCard from '@/components/ui/KpiCard.vue';
import FilterBar from '@/components/ui/FilterBar.vue';
import DataTable from '@/components/ui/DataTable.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import ComunidadFormDrawer from '@/components/comunidades/ComunidadFormDrawer.vue';
import ComunidadDetailDrawer from '@/components/comunidades/ComunidadDetailDrawer.vue';
import comunidadService from '@/services/modules/comunidadService';
import { obtenerMensajeError } from '@/services/errorService';
import { useToast } from '@/composables/useToast';
import { normalizeList } from '@/utils/datos';
import { altitud, hectareas, numero } from '@/utils/formato';
import { usuarioGuardado } from '@/utils/permisos';

const route = useRoute();
const toast = useToast();

const comunidades = ref([]);
const cargando = ref(true);
const errorPagina = ref('');
const busqueda = ref(String(route.query.buscar || ''));
const filtro = ref('todas');
const departamento = ref(null);
const resaltado = ref(null);

// ---------- datos
async function cargar() {
  cargando.value = true;
  errorPagina.value = '';

  try {
    const { data } = await comunidadService.listar({ todos: 1 });

    comunidades.value = normalizeList(data);
  } catch (e) {
    errorPagina.value = obtenerMensajeError(e, 'No se pudieron cargar las comunidades. Revisa tu conexión e intenta de nuevo.');
    comunidades.value = [];
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
watch(() => route.query.buscar, (q) => (busqueda.value = String(q || '')));

const tieneUbicacion = (c) => c.latitud !== null && c.latitud !== undefined && c.latitud !== '' && c.longitud !== null && c.longitud !== undefined && c.longitud !== '';

// ---------- indicadores
const activasLista = computed(() => comunidades.value.filter((c) => c.activo));
const activas = computed(() => activasLista.value.length);
const suma = (lista, campo) => lista.reduce((s, c) => s + Number(c[campo] || 0), 0);
const totalProductores = computed(() => suma(activasLista.value, 'productores_count'));
const totalParcelas = computed(() => suma(activasLista.value, 'parcelas_count'));
const totalConAlerta = computed(() => suma(activasLista.value, 'parcelas_con_alerta'));
const totalSuperficie = computed(() => suma(activasLista.value, 'superficie_total_ha'));

const resumen = computed(() =>
  cargando.value
    ? ''
    : `${numero(comunidades.value.length, { decimales: 0 })} comunidades · ${numero(totalProductores.value, { decimales: 0 })} productores · ${numero(totalParcelas.value, { decimales: 0 })} parcelas en ${hectareas(totalSuperficie.value)}`
);

// ---------- filtros
const pildoras = computed(() => [
  { valor: 'todas', etiqueta: 'Todas', conteo: comunidades.value.length },
  { valor: 'activas', etiqueta: 'Activas', conteo: activas.value },
  { valor: 'inactivas', etiqueta: 'Inactivas', conteo: comunidades.value.length - activas.value },
  { valor: 'sin_ubicacion', etiqueta: 'Sin ubicación', conteo: comunidades.value.filter((c) => !tieneUbicacion(c)).length },
]);

const opcionesDepartamento = computed(() =>
  [...new Set(comunidades.value.map((c) => c.departamento).filter(Boolean))].sort().map((d) => ({ valor: d, etiqueta: d }))
);

const sinTildes = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const filtradas = computed(() => {
  const q = sinTildes(busqueda.value).trim();

  return comunidades.value.filter((c) => {
    const texto = sinTildes([c.nombre_comunidad, c.municipio, c.provincia, c.departamento].filter(Boolean).join(' '));

    if (q && !texto.includes(q)) return false;
    if (filtro.value === 'activas' && !c.activo) return false;
    if (filtro.value === 'inactivas' && c.activo) return false;
    if (filtro.value === 'sin_ubicacion' && tieneUbicacion(c)) return false;
    if (departamento.value && c.departamento !== departamento.value) return false;

    return true;
  });
});

const descripcionFiltros = computed(() =>
  [busqueda.value && `búsqueda «${busqueda.value}»`, filtro.value !== 'todas' && pildoras.value.find((p) => p.valor === filtro.value)?.etiqueta, departamento.value]
    .filter(Boolean)
    .join(' · ')
);

// ---------- tabla
const columnas = [
  { clave: 'nombre_comunidad', titulo: 'Comunidad' },
  { clave: 'productores_count', titulo: 'Productores', align: 'der' },
  { clave: 'parcelas_count', titulo: 'Parcelas', align: 'der' },
  { clave: 'superficie_total_ha', titulo: 'Superficie', align: 'der' },
  { clave: 'altitud_msnm', titulo: 'Altitud', align: 'der' },
  { clave: 'parcelas_con_alerta', titulo: 'Con alerta', align: 'der' },
  { clave: 'activo', titulo: 'Estado' },
];

const acciones = (c) => [
  { id: 'editar', etiqueta: 'Editar', icono: 'ti-pencil' },
  c.activo ? { id: 'desactivar', etiqueta: 'Desactivar', icono: 'ti-circle-off', peligro: true } : { id: 'activar', etiqueta: 'Activar', icono: 'ti-circle-check' },
];

function alAccion({ id, fila }) {
  if (id === 'editar') abrirEditar(fila);
  else if (id === 'desactivar') pedirCambioEstado(fila);
  else if (id === 'activar') activar(fila);
}

// ---------- crear / editar
const formAbierto = ref(false);
const comunidadEditar = ref(null);
const guardando = ref(false);
const erroresServidor = ref({});
const errorGeneral = ref('');

function abrirCrear() {
  comunidadEditar.value = null;
  erroresServidor.value = {};
  errorGeneral.value = '';
  formAbierto.value = true;
}

function abrirEditar(c) {
  comunidadEditar.value = c;
  erroresServidor.value = {};
  errorGeneral.value = '';
  detalleAbierto.value = false;
  formAbierto.value = true;
}

function resaltar(id) {
  resaltado.value = id;
  setTimeout(() => (resaltado.value = null), 2200);
}

async function guardar(payload) {
  if (guardando.value) return;

  guardando.value = true;
  erroresServidor.value = {};
  errorGeneral.value = '';

  try {
    const editando = comunidadEditar.value;
    const { data } = editando ? await comunidadService.actualizar(editando.id, payload) : await comunidadService.crear(payload);

    await cargar();
    formAbierto.value = false;
    resaltar(data?.id ?? editando?.id);
    toast.exito(`Comunidad «${payload.nombre_comunidad}» ${editando ? 'actualizada' : 'creada'}`);
  } catch (e) {
    const errores = e?.response?.data?.errors;

    if (errores) {
      erroresServidor.value = Object.fromEntries(Object.entries(errores).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
      errorGeneral.value = 'Revisa los campos marcados en rojo.';
    } else {
      errorGeneral.value = obtenerMensajeError(e, 'No se pudo guardar la comunidad. Intenta de nuevo.');
    }
  } finally {
    guardando.value = false;
  }
}

// ---------- detalle
const detalleAbierto = ref(false);
const comunidadDetalle = ref(null);

async function abrirDetalle(c) {
  comunidadDetalle.value = c;
  detalleAbierto.value = true;

  try {
    const { data } = await comunidadService.obtener(c.id);

    comunidadDetalle.value = { ...c, ...data, productores: data.productores || [] };
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo cargar el detalle de la comunidad.'));
  }
}

function editarDesdeDetalle(c) {
  abrirEditar(c);
}

// ---------- activar / desactivar
const confirmarAbierto = ref(false);
const comunidadConfirmar = ref(null);
const cambiandoEstado = ref(false);

function pedirCambioEstado(c) {
  if (!c.activo) return activar(c);

  comunidadConfirmar.value = c;
  confirmarAbierto.value = true;
}

async function desactivar() {
  if (cambiandoEstado.value) return;

  cambiandoEstado.value = true;

  try {
    await comunidadService.eliminar(comunidadConfirmar.value.id);
    await cargar();
    resaltar(comunidadConfirmar.value.id);
    toast.exito(`Comunidad «${comunidadConfirmar.value.nombre_comunidad}» desactivada`);
    detalleAbierto.value = false;
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo desactivar la comunidad.'));
  } finally {
    cambiandoEstado.value = false;
    confirmarAbierto.value = false;
  }
}

async function activar(c) {
  try {
    await comunidadService.activar(c.id);
    await cargar();
    resaltar(c.id);
    toast.exito(`Comunidad «${c.nombre_comunidad}» activada`);
    detalleAbierto.value = false;
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo activar la comunidad.'));
  }
}

// ---------- exportar
async function exportar(tipo) {
  try {
    const modulo = await import('@/utils/exportarComunidades');

    if (tipo === 'pdf') await modulo.exportarComunidadesPdf(filtradas.value, usuarioGuardado(), descripcionFiltros.value);
    else await modulo.exportarComunidadesExcel(filtradas.value, usuarioGuardado(), descripcionFiltros.value);
  } catch (e) {
    toast.error('No se pudo generar el archivo. Intenta de nuevo.');
  }
}
</script>

<style scoped>
.vc-nombre {
  display: block;
  font-weight: 700;
}

.vc-secundario {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: #4a6070;
}

.vc-sin-ubicacion {
  margin-top: 4px;
  padding: 2px 8px;
  border: 0;
  border-radius: 999px;
  background: #fffbeb;
  color: #92400e;
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
}

.vc-alerta {
  font-weight: 700;
}

.vc-alerta.hay-alerta {
  color: #9a3412;
  background: #fff3e8;
  padding: 2px 10px;
  border-radius: 999px;
}
</style>
