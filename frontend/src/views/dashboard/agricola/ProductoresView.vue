<template>
  <div class="pagina">
    <PageHeader
      titulo="Productores"
      :resumen="resumen"
      :migas="[{ texto: 'Inicio', to: '/dashboard' }, { texto: 'Campo' }, { texto: 'Productores' }]"
    >
      <div class="pagina-acciones">
        <BaseButton variante="secundario" icono="ti-file-spreadsheet" :disabled="!filtrados.length" @click="exportar('excel')">Excel</BaseButton>
        <BaseButton variante="secundario" icono="ti-file-type-pdf" :disabled="!filtrados.length" @click="exportar('pdf')">PDF</BaseButton>
        <BaseButton v-if="puedeGestionar" icono="ti-plus" @click="abrirCrear">Nuevo productor</BaseButton>
      </div>
    </PageHeader>

    <p v-if="errorPagina" class="aviso-error" role="alert">{{ errorPagina }}</p>

    <div class="rejilla-kpi">
      <KpiCard etiqueta="Productores activos" :valor="numero(activos.length, { decimales: 0 })" :contexto="`de ${productores.length} registrados`" icono="ti-users" />
      <KpiCard etiqueta="Sin parcelas registradas" :valor="numero(sinParcelas, { decimales: 0 })" contexto="activos todavía sin parcela" icono="ti-map-off" />
      <KpiCard
        etiqueta="Con parcelas en alerta"
        :valor="numero(conAlerta, { decimales: 0 })"
        :contexto="conAlerta > 0 ? 'requieren seguimiento' : 'ninguno con alerta activa'"
        :alerta="conAlerta > 0"
        icono="ti-alert-triangle"
      />
      <KpiCard etiqueta="Superficie declarada" :valor="hectareas(superficieDeclarada)" :contexto="`${hectareas(superficieUsada)} ya en parcelas`" icono="ti-ruler-2" />
    </div>

    <FilterBar
      v-model:busqueda="busqueda"
      v-model:activo="filtro"
      placeholder="Nombre, carnet, teléfono o comunidad"
      :filtros="pildoras"
      :filtros-extra="(comunidad ? 1 : 0) + (sexo ? 1 : 0)"
    >
      <template #mas>
        <BaseSelect v-model="comunidad" etiqueta="Comunidad" limpiable :opciones="opcionesComunidadFiltro" placeholder="Todas las comunidades" />
        <BaseSelect v-model="sexo" etiqueta="Sexo" limpiable :opciones="[{ valor: 'M', etiqueta: 'Masculino' }, { valor: 'F', etiqueta: 'Femenino' }, { valor: 'Otro', etiqueta: 'Otro' }]" placeholder="Todos" />
      </template>
    </FilterBar>

    <DataTable
      :columnas="columnas"
      :filas="filtrados"
      :total="productores.length"
      :por-pagina="1000"
      :cargando="cargando"
      :acciones="puedeGestionar ? acciones : null"
      :resaltar-id="resaltado"
      vacio-icono="ti-users"
      :vacio-titulo="productores.length ? 'Ningún productor coincide con la búsqueda' : 'Todavía no hay productores'"
      :vacio-texto="productores.length ? 'Prueba con otro nombre o quita los filtros.' : 'Registra al primer productor para poder asociarle parcelas.'"
      @ver="abrirDetalle"
      @accion="alAccion"
    >
      <template #celda-nombre_completo="{ fila }">
        <strong class="vp-nombre">{{ nombreCorto(fila) }}</strong>
        <span class="vp-secundario">{{ ciYTelefono(fila) }}</span>
      </template>
      <template #celda-comunidad="{ fila }">{{ fila.comunidad?.nombre_comunidad || '—' }}</template>
      <template #celda-parcelas_count="{ fila }">{{ numero(fila.parcelas_count ?? 0, { decimales: 0 }) }}</template>
      <template #celda-superficie="{ fila }">
        <div class="vp-sup" :title="textoSuperficie(fila)">
          <span>{{ textoSuperficie(fila) }}</span>
          <span v-if="tieneDeclarada(fila)" class="vp-barra" :class="{ 'excede': excede(fila) }" role="img" :aria-label="excede(fila) ? 'Supera la superficie declarada' : 'Dentro de la superficie declarada'">
            <i :style="{ width: porcentajeUso(fila) + '%' }"></i>
          </span>
        </div>
      </template>
      <template #celda-activo="{ fila }">
        <StatusBadge :estado="fila.activo ? 'Activo' : 'Inactivo'" />
        <StatusBadge v-if="fila.parcelas_con_alerta > 0" tono="naranja" :texto="`${fila.parcelas_con_alerta} con alerta`" class="vp-alerta" />
      </template>
    </DataTable>

    <ProductorFormDrawer
      v-model="formAbierto"
      :productor="productorEditar"
      :comunidades="comunidades"
      :guardando="guardando"
      :errores-servidor="erroresServidor"
      :error-general="errorGeneral"
      @guardar="guardar"
    />

    <ProductorDetailDrawer v-model="detalleAbierto" :productor="productorDetalle" @editar="abrirEditar" @cambiar-estado="pedirCambioEstado" />

    <ConfirmDialog
      v-model="confirmarAbierto"
      :titulo="`¿Desactivar a ${productorConfirmar?.nombre_completo || ''}?`"
      texto-confirmar="Sí, desactivar"
      texto-cargando="Desactivando…"
      :cargando="cambiandoEstado"
      @confirmar="desactivar"
    >
      Dejará de aparecer en los formularios nuevos. No se borra ningún dato y se puede volver a activar. Si todavía tiene parcelas activas, el
      sistema no lo permitirá.
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
import ProductorFormDrawer from '@/components/productores/ProductorFormDrawer.vue';
import ProductorDetailDrawer from '@/components/productores/ProductorDetailDrawer.vue';
import productorService from '@/services/modules/productorService';
import comunidadService from '@/services/modules/comunidadService';
import { obtenerMensajeError } from '@/services/errorService';
import { useToast } from '@/composables/useToast';
import { normalizeList } from '@/utils/datos';
import { hectareas, numero } from '@/utils/formato';
import { tienePermiso, usuarioGuardado } from '@/utils/permisos';

const route = useRoute();
const toast = useToast();

const puedeGestionar = computed(() => tienePermiso(usuarioGuardado(), 'gestionar_productores'));

const productores = ref([]);
const comunidades = ref([]);
const cargando = ref(true);
const errorPagina = ref('');
const busqueda = ref(String(route.query.buscar || ''));
const filtro = ref('todos');
const comunidad = ref(null);
const sexo = ref(null);
const resaltado = ref(null);

// ---------- datos
async function cargar() {
  cargando.value = true;
  errorPagina.value = '';

  try {
    const [p, c] = await Promise.all([
      productorService.listar({ todos: 1 }),
      comunidadService.listar({ todos: 1 }).catch(() => ({ data: [] })),
    ]);

    productores.value = normalizeList(p.data);
    comunidades.value = normalizeList(c.data);
  } catch (e) {
    errorPagina.value = obtenerMensajeError(e, 'No se pudieron cargar los productores. Revisa tu conexión e intenta de nuevo.');
    productores.value = [];
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
watch(() => route.query.buscar, (q) => (busqueda.value = String(q || '')));

// ---------- indicadores
const activos = computed(() => productores.value.filter((p) => p.activo));
const sinParcelas = computed(() => activos.value.filter((p) => !p.parcelas_count).length);
const conAlerta = computed(() => activos.value.filter((p) => p.parcelas_con_alerta > 0).length);
const superficieDeclarada = computed(() => activos.value.reduce((s, p) => s + Number(p.superficie_total_ha || 0), 0));
const superficieUsada = computed(() => activos.value.reduce((s, p) => s + Number(p.superficie_parcelas_ha || 0), 0));

const resumen = computed(() => {
  if (cargando.value) return '';

  const comunidadesConProductores = new Set(productores.value.map((p) => p.id_comunidad)).size;
  const parcelas = productores.value.reduce((s, p) => s + Number(p.parcelas_count || 0), 0);

  return `${numero(productores.value.length, { decimales: 0 })} productores en ${numero(comunidadesConProductores, { decimales: 0 })} comunidades · ${numero(parcelas, { decimales: 0 })} parcelas`;
});

// ---------- filtros
const pildoras = computed(() => [
  { valor: 'todos', etiqueta: 'Todos', conteo: productores.value.length },
  { valor: 'activos', etiqueta: 'Activos', conteo: activos.value.length },
  { valor: 'inactivos', etiqueta: 'Inactivos', conteo: productores.value.length - activos.value.length },
  { valor: 'sin_parcelas', etiqueta: 'Sin parcelas', conteo: sinParcelas.value },
  { valor: 'con_alerta', etiqueta: 'Con alerta', conteo: conAlerta.value },
]);

const opcionesComunidadFiltro = computed(() => {
  const vistas = new Map();

  for (const p of productores.value) if (p.comunidad) vistas.set(p.id_comunidad, p.comunidad.nombre_comunidad);

  return [...vistas].map(([valor, etiqueta]) => ({ valor, etiqueta })).sort((a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'));
});

const sinTildes = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

const filtrados = computed(() => {
  const q = sinTildes(busqueda.value).trim();

  return productores.value.filter((p) => {
    const texto = sinTildes([p.nombre_completo, p.carnet_identidad, p.telefono, p.comunidad?.nombre_comunidad].filter(Boolean).join(' '));

    if (q && !texto.includes(q)) return false;
    if (filtro.value === 'activos' && !p.activo) return false;
    if (filtro.value === 'inactivos' && p.activo) return false;
    if (filtro.value === 'sin_parcelas' && (!p.activo || p.parcelas_count)) return false;
    if (filtro.value === 'con_alerta' && !(p.parcelas_con_alerta > 0)) return false;
    if (comunidad.value && p.id_comunidad !== comunidad.value) return false;
    if (sexo.value && p.sexo !== sexo.value) return false;

    return true;
  });
});

const descripcionFiltros = computed(() =>
  [
    busqueda.value && `búsqueda «${busqueda.value}»`,
    filtro.value !== 'todos' && pildoras.value.find((p) => p.valor === filtro.value)?.etiqueta,
    comunidad.value && opcionesComunidadFiltro.value.find((o) => o.valor === comunidad.value)?.etiqueta,
    sexo.value && { M: 'Masculino', F: 'Femenino', Otro: 'Otro' }[sexo.value],
  ]
    .filter(Boolean)
    .join(' · ')
);

// ---------- tabla
const columnas = [
  { clave: 'nombre_completo', titulo: 'Productor' },
  { clave: 'comunidad', titulo: 'Comunidad' },
  { clave: 'parcelas_count', titulo: 'Parcelas', align: 'der' },
  { clave: 'superficie', titulo: 'Superficie (usada / declarada)' },
  { clave: 'activo', titulo: 'Estado' },
];

const nombreCorto = (p) => p.nombre_completo || [p.nombres, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(' ');
const ciYTelefono = (p) =>
  [`CI ${p.carnet_identidad ?? ''}${p.complemento_ci ? `-${p.complemento_ci}` : ''} ${p.ci_extension ?? ''}`.trim(), p.telefono].filter(Boolean).join(' · ');

const tieneDeclarada = (p) => p.superficie_total_ha !== null && p.superficie_total_ha !== '' && p.superficie_total_ha !== undefined && Number(p.superficie_total_ha) > 0;
const excede = (p) => tieneDeclarada(p) && Number(p.superficie_parcelas_ha || 0) > Number(p.superficie_total_ha);
const porcentajeUso = (p) => Math.min(100, Math.round((Number(p.superficie_parcelas_ha || 0) / Number(p.superficie_total_ha)) * 100));
const textoSuperficie = (p) =>
  `${hectareas(p.superficie_parcelas_ha ?? 0)} / ${p.superficie_total_ha !== null && p.superficie_total_ha !== '' && p.superficie_total_ha !== undefined ? hectareas(p.superficie_total_ha) : 'sin declarar'}`;

const acciones = (p) => [
  { id: 'editar', etiqueta: 'Editar', icono: 'ti-pencil' },
  p.activo ? { id: 'desactivar', etiqueta: 'Desactivar', icono: 'ti-circle-off', peligro: true } : { id: 'activar', etiqueta: 'Activar', icono: 'ti-circle-check' },
];

function alAccion({ id, fila }) {
  if (id === 'editar') abrirEditar(fila);
  else if (id === 'desactivar') pedirCambioEstado(fila);
  else if (id === 'activar') activar(fila);
}

function resaltar(id) {
  resaltado.value = id;
  setTimeout(() => (resaltado.value = null), 2200);
}

// ---------- crear / editar
const formAbierto = ref(false);
const productorEditar = ref(null);
const guardando = ref(false);
const erroresServidor = ref({});
const errorGeneral = ref('');

function abrirCrear() {
  productorEditar.value = null;
  erroresServidor.value = {};
  errorGeneral.value = '';
  formAbierto.value = true;
}

function abrirEditar(p) {
  productorEditar.value = p;
  erroresServidor.value = {};
  errorGeneral.value = '';
  detalleAbierto.value = false;
  formAbierto.value = true;
}

async function guardar(payload) {
  if (guardando.value) return;

  guardando.value = true;
  erroresServidor.value = {};
  errorGeneral.value = '';

  try {
    const editando = productorEditar.value;
    const { data } = editando ? await productorService.actualizar(editando.id, payload) : await productorService.crear(payload);

    await cargar();
    formAbierto.value = false;
    resaltar(data?.id ?? editando?.id);
    toast.exito(`Productor «${[payload.nombres, payload.apellido_paterno].join(' ')}» ${editando ? 'actualizado' : 'creado'}`);
  } catch (e) {
    const errores = e?.response?.data?.errors;

    if (errores) {
      erroresServidor.value = Object.fromEntries(Object.entries(errores).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
      errorGeneral.value = 'Revisa los campos marcados en rojo.';
    } else {
      errorGeneral.value = obtenerMensajeError(e, 'No se pudo guardar el productor. Intenta de nuevo.');
    }
  } finally {
    guardando.value = false;
  }
}

// ---------- detalle
const detalleAbierto = ref(false);
const productorDetalle = ref(null);

async function abrirDetalle(p) {
  productorDetalle.value = p;
  detalleAbierto.value = true;

  try {
    const { data } = await productorService.obtener(p.id);

    productorDetalle.value = { ...p, ...data };
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo cargar el detalle del productor.'));
  }
}

// ---------- activar / desactivar
const confirmarAbierto = ref(false);
const productorConfirmar = ref(null);
const cambiandoEstado = ref(false);

function pedirCambioEstado(p) {
  if (!p.activo) return activar(p);

  productorConfirmar.value = p;
  confirmarAbierto.value = true;
}

async function desactivar() {
  if (cambiandoEstado.value) return;

  cambiandoEstado.value = true;

  try {
    await productorService.eliminar(productorConfirmar.value.id);
    await cargar();
    resaltar(productorConfirmar.value.id);
    toast.exito(`Productor «${productorConfirmar.value.nombre_completo}» desactivado`);
    detalleAbierto.value = false;
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo desactivar al productor.'));
  } finally {
    cambiandoEstado.value = false;
    confirmarAbierto.value = false;
  }
}

async function activar(p) {
  try {
    await productorService.activar(p.id);
    await cargar();
    resaltar(p.id);
    toast.exito(`Productor «${p.nombre_completo}» activado`);
    detalleAbierto.value = false;
  } catch (e) {
    toast.error(obtenerMensajeError(e, 'No se pudo activar al productor.'));
  }
}

// ---------- exportar
async function exportar(tipo) {
  try {
    const modulo = await import('@/utils/exportarProductores');

    if (tipo === 'pdf') await modulo.exportarProductoresPdf(filtrados.value, usuarioGuardado(), descripcionFiltros.value);
    else await modulo.exportarProductoresExcel(filtrados.value, usuarioGuardado(), descripcionFiltros.value);
  } catch (e) {
    toast.error('No se pudo generar el archivo. Intenta de nuevo.');
  }
}
</script>

<style scoped>
.vp-nombre {
  display: block;
  font-weight: 700;
}

.vp-secundario {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: #4a6070;
}

.vp-sup {
  display: grid;
  gap: 6px;
  min-width: 170px;
  font-size: 13px;
}

.vp-barra {
  display: block;
  height: 6px;
  border-radius: 999px;
  background: #dce6ed;
  overflow: hidden;
}

.vp-barra i {
  display: block;
  height: 100%;
  background: #2e7d5a;
  border-radius: 999px;
}

.vp-barra.excede i {
  background: #d97706;
}

.vp-alerta {
  margin-left: 6px;
}
</style>
