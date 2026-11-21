<template>
  <div class="guia">
    <PageHeader
      titulo="Guía de componentes"
      resumen="Componentes de la Versión 2 con todas sus variantes. Los datos de esta página son solo de ejemplo."
      :migas="[{ texto: 'Inicio', to: '/dashboard' }, { texto: 'Guía de componentes' }]"
    />

    <!-- Colores -->
    <section class="guia-bloque">
      <h2>Colores</h2>
      <div class="guia-colores">
        <div v-for="c in colores" :key="c.nombre" class="guia-color">
          <span class="guia-muestra" :style="{ background: c.valor, color: c.texto || '#111827' }">Aa</span>
          <strong>{{ c.nombre }}</strong>
          <code class="codigo">{{ c.valor }}</code>
        </div>
      </div>
    </section>

    <!-- Tipografía y formato -->
    <section class="guia-bloque">
      <h2>Tipografía y formato boliviano</h2>
      <p class="titulo-pagina">Título de página (26 a 30 px, peso 800)</p>
      <p class="guia-tarjeta-titulo">Título de tarjeta (17 px, peso 700)</p>
      <p>Cuerpo 14 a 15 px · <span class="texto-secundario">Texto secundario 12 a 13 px</span> · <span class="codigo">CHI-HYC-01</span></p>
      <ul class="guia-formatos">
        <li>{{ hectareas(0.8) }}</li>
        <li>{{ altitud(3860) }}</li>
        <li>{{ bolivianos(1250.5) }}</li>
        <li>{{ porcentaje(87) }}</li>
        <li>{{ fecha('2026-11-09') }}</li>
        <li>{{ fechaRelativa(haceDias(3)) }}</li>
      </ul>
    </section>

    <!-- Botones -->
    <section class="guia-bloque">
      <h2>BaseButton</h2>
      <div class="guia-fila">
        <BaseButton>Primario</BaseButton>
        <BaseButton variante="secundario">Secundario</BaseButton>
        <BaseButton variante="peligro">Peligro</BaseButton>
        <BaseButton variante="fantasma">Fantasma</BaseButton>
        <BaseButton disabled>Deshabilitado</BaseButton>
        <BaseButton icono="ti-plus">Con ícono</BaseButton>
        <BaseButton :cargando="guardando" texto-cargando="Guardando…" @click="simularGuardado">
          Probar «cargando»
        </BaseButton>
      </div>
      <p class="texto-secundario">«Cargando» muestra el spinner y bloquea el doble clic (haz clic dos veces seguidas).</p>
    </section>

    <!-- Campos -->
    <section class="guia-bloque">
      <h2>BaseInput, BaseSelect y BaseRadioCards</h2>
      <div class="guia-rejilla">
        <BaseInput v-model="campos.nombre" etiqueta="Nombre de la parcela" obligatorio placeholder="Ej.: Lote Cruz Pata" ayuda="Así se verá en listas y reportes." />
        <BaseInput v-model="campos.superficie" etiqueta="Superficie" obligatorio unidad="ha" inputmode="decimal" :error="errorSuperficie" ayuda="Entre 0,01 y 500 ha." />
        <BaseInput v-model="campos.altitud" etiqueta="Altitud" unidad="m s. n. m." inputmode="numeric" />
        <BaseInput v-model="campos.nota" etiqueta="Observaciones" multilinea :filas="2" />
        <BaseSelect
          v-model="campos.productor"
          etiqueta="Productor"
          obligatorio
          limpiable
          :opciones="productoresEjemplo"
          :error="campos.productor ? '' : 'Elige un productor de la lista.'"
        />
        <BaseSelect v-model="campos.parcela" etiqueta="Parcela" :opciones="parcelasEjemplo" disabled ayuda="Deshabilitado." />
      </div>
      <BaseRadioCards v-model="campos.riego" etiqueta="Sistema de riego" obligatorio :opciones="riegoEjemplo" />
      <p class="texto-secundario">El error aparece al salir del campo o al enviar, no mientras se escribe por primera vez.</p>
    </section>

    <!-- Insignias -->
    <section class="guia-bloque">
      <h2>StatusBadge y PriorityBadge</h2>
      <div v-for="g in gruposEstado" :key="g.tono" class="guia-fila">
        <span class="guia-etiqueta-fila">{{ g.tono }}</span>
        <StatusBadge v-for="e in g.estados" :key="e" :estado="e" />
      </div>
      <div class="guia-fila">
        <span class="guia-etiqueta-fila">prioridad</span>
        <PriorityBadge :puntaje="92" />
        <PriorityBadge :puntaje="68" />
        <PriorityBadge :puntaje="45" />
        <PriorityBadge :puntaje="22" />
        <PriorityBadge :puntaje="85" solo-numero />
      </div>
    </section>

    <!-- KPI -->
    <section class="guia-bloque">
      <h2>KpiCard</h2>
      <div class="guia-rejilla guia-rejilla--4">
        <KpiCard etiqueta="Parcelas sanas" valor="83 %" contexto="de 30 parcelas" :variacion="4.2" :serie="[60, 64, 70, 72, 78, 83]" />
        <KpiCard etiqueta="Visitas de la semana" valor="9 / 12" contexto="hechas / programadas" :serie="[3, 5, 8, 6, 9]" />
        <KpiCard etiqueta="Stock bajo el mínimo" valor="4" contexto="productos por reponer" alerta :variacion="2" invertir-variacion />
        <KpiCard etiqueta="Ventas del mes" :valor="bolivianos(15840)" contexto="frente al mes anterior" :variacion="-6.5" />
      </div>
    </section>

    <!-- Encabezado, filtros y tabla -->
    <section class="guia-bloque">
      <h2>PageHeader, FilterBar y DataTable</h2>
      <PageHeader titulo="Parcelas" resumen="30 parcelas · 58,4 ha">
        <BaseButton icono="ti-plus">Nueva parcela</BaseButton>
      </PageHeader>
      <FilterBar v-model:busqueda="busqueda" v-model:activo="filtro" placeholder="Nombre, productor, comunidad o código" :filtros="filtros" :filtros-extra="0">
        <template #mas>
          <BaseSelect v-model="campos.productor" etiqueta="Productor" :opciones="productoresEjemplo" limpiable />
          <BaseRadioCards v-model="campos.riego" etiqueta="Riego" :opciones="riegoEjemplo" :columnas="1" />
        </template>
      </FilterBar>

      <DataTable
        :columnas="columnas"
        :filas="filasVisibles"
        :cargando="cargandoTabla"
        :acciones="accionesFila"
        :total="filasVisibles.length"
        :resaltar-id="resaltado"
        vacio-titulo="No hay parcelas que coincidan"
        vacio-texto="Prueba con otro nombre o quita los filtros."
        @ver="(f) => (detalle = f)"
        @accion="alAccion"
      >
        <template #celda-estado="{ fila }"><StatusBadge :estado="fila.estado" /></template>
        <template #celda-superficie="{ fila }">{{ hectareas(fila.superficie) }}</template>
      </DataTable>
      <div class="guia-fila">
        <BaseButton variante="secundario" @click="recargarTabla">Ver filas «esqueleto»</BaseButton>
        <BaseButton variante="secundario" @click="busqueda = 'zzzz'">Ver estado vacío</BaseButton>
        <BaseButton variante="secundario" @click="resaltado = 2">Resaltar fila 2 (2 s)</BaseButton>
      </div>
    </section>

    <!-- Vacío -->
    <section class="guia-bloque">
      <h2>EmptyState</h2>
      <EmptyState icono="ti-map-pin-off" titulo="Todavía no hay parcelas" texto="Registra la primera parcela para ver su estado sanitario aquí.">
        <BaseButton icono="ti-plus">Nueva parcela</BaseButton>
      </EmptyState>
    </section>

    <!-- Paneles y confirmación -->
    <section class="guia-bloque">
      <h2>FormDrawer, DetailDrawer, ConfirmDialog y Toast</h2>
      <div class="guia-fila">
        <BaseButton @click="formularioAbierto = true">Abrir formulario por pasos</BaseButton>
        <BaseButton variante="secundario" @click="detalle = filas[0]">Abrir detalle</BaseButton>
        <BaseButton variante="peligro" @click="confirmarAbierto = true">Anular venta…</BaseButton>
        <BaseButton variante="secundario" @click="toast.exito('Parcela guardada', { texto: 'Ver', to: '/dashboard/parcelas' })">Mostrar aviso</BaseButton>
      </div>
    </section>

    <FormDrawer
      v-model="formularioAbierto"
      v-model:paso="paso"
      titulo="Nueva parcela"
      subtitulo="Paso a paso, con validación en cada uno."
      :pasos="['Productor y nombre', 'Terreno', 'Ubicación']"
      :guardando="guardando"
      :validar-paso="validarPaso"
      texto-guardar="Guardar parcela"
      @guardar="guardarFormulario"
    >
      <template #default="{ paso: actual }">
        <FormSection v-if="actual === 1" titulo="Productor y nombre">
          <BaseSelect v-model="campos.productor" etiqueta="Productor" obligatorio :opciones="productoresEjemplo" :error="campos.productor ? '' : 'Elige un productor de la lista.'" :mostrar-errores="intentoPaso" />
          <BaseInput v-model="campos.nombre" etiqueta="Nombre de la parcela" obligatorio :error="campos.nombre ? '' : 'Escribe un nombre para reconocer la parcela.'" :mostrar-errores="intentoPaso" />
        </FormSection>
        <FormSection v-else-if="actual === 2" titulo="Terreno" :columnas="2">
          <BaseInput v-model="campos.superficie" etiqueta="Superficie" obligatorio unidad="ha" :error="errorSuperficie" :mostrar-errores="intentoPaso" />
          <BaseInput v-model="campos.altitud" etiqueta="Altitud" unidad="m s. n. m." />
        </FormSection>
        <FormSection v-else titulo="Ubicación">
          <p class="texto-secundario">Aquí irían las coordenadas y el mapa (etapa B3).</p>
        </FormSection>
      </template>
    </FormDrawer>

    <DetailDrawer v-model="detalleAbierto" :titulo="detalle?.nombre || ''" :codigo="detalle?.codigo" :subtitulo="detalle ? `${detalle.productor} · ${detalle.comunidad}` : ''">
      <template #estado><StatusBadge v-if="detalle" :estado="detalle.estado" /></template>
      <DetailBlock v-if="detalle" titulo="Datos del terreno" :datos="[
        { etiqueta: 'Superficie', valor: hectareas(detalle.superficie) },
        { etiqueta: 'Altitud', valor: altitud(3860) },
        { etiqueta: 'Riego', valor: 'Secano' },
        { etiqueta: 'Última visita', valor: fechaRelativa(haceDias(5)) },
        { etiqueta: 'Observaciones', valor: '', completo: true },
      ]" />
      <template #acciones>
        <BaseButton variante="secundario" @click="detalle = null">Cerrar</BaseButton>
        <BaseButton icono="ti-pencil">Editar</BaseButton>
      </template>
    </DetailDrawer>

    <ConfirmDialog
      v-model="confirmarAbierto"
      titulo="¿Anular la venta V-0142?"
      texto-confirmar="Sí, anular"
      :cargando="guardando"
      texto-cargando="Anulando…"
      @confirmar="simularGuardado(() => (confirmarAbierto = false))"
    >
      Se devolverán 12 unidades al almacén y la venta quedará como anulada. Esta acción no se puede deshacer.
    </ConfirmDialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import BaseRadioCards from '@/components/ui/BaseRadioCards.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import PriorityBadge from '@/components/ui/PriorityBadge.vue';
import KpiCard from '@/components/ui/KpiCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import PageHeader from '@/components/ui/PageHeader.vue';
import FilterBar from '@/components/ui/FilterBar.vue';
import DataTable from '@/components/ui/DataTable.vue';
import FormDrawer from '@/components/ui/FormDrawer.vue';
import FormSection from '@/components/ui/FormSection.vue';
import DetailDrawer from '@/components/ui/DetailDrawer.vue';
import DetailBlock from '@/components/ui/DetailBlock.vue';
import { useToast } from '@/composables/useToast';
import { altitud, bolivianos, fecha, fechaRelativa, hectareas, porcentaje } from '@/utils/formato';

const toast = useToast();

const colores = [
  { nombre: 'Fondo', valor: '#eef3f0' },
  { nombre: 'Verde principal', valor: '#1a5c3e', texto: '#ffffff' },
  { nombre: 'Verde oscuro', valor: '#0d3d2b', texto: '#ffffff' },
  { nombre: 'Verde claro', valor: '#a8e6c6' },
  { nombre: 'Naranja alerta', valor: '#f07a2a' },
  { nombre: 'Ámbar', valor: '#d97706', texto: '#ffffff' },
  { nombre: 'Rojo', valor: '#b91c1c', texto: '#ffffff' },
  { nombre: 'Azul', valor: '#2563eb', texto: '#ffffff' },
  { nombre: 'Texto secundario', valor: '#4a6070', texto: '#ffffff' },
  { nombre: 'Borde', valor: '#dce6ed' },
];

const gruposEstado = [
  { tono: 'verde', estados: ['Activo', 'Sana', 'Confirmado', 'Completada', 'Resuelta', 'Aprobada', 'Pagada'] },
  { tono: 'gris', estados: ['Inactivo', 'En_descanso', 'Cancelada', 'Anulada'] },
  { tono: 'ámbar', estados: ['Pendiente', 'Programada', 'Por vencer', 'Borrador'] },
  { tono: 'azul', estados: ['En_curso', 'En_tratamiento', 'Planificada'] },
  { tono: 'naranja', estados: ['Con alerta', 'Corregido'] },
  { tono: 'rojo', estados: ['Rechazado', 'Vencido', 'Sin stock'] },
];

const haceDias = (n) => {
  const d = new Date();

  d.setDate(d.getDate() - n);

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// ----- campos de ejemplo
const campos = reactive({ nombre: '', superficie: '', altitud: '', nota: '', productor: null, parcela: null, riego: 'Secano' });

const productoresEjemplo = [
  { valor: 1, etiqueta: 'Humberto Yucra Colque', detalle: 'CI 4876512 LP · Chiarpata' },
  { valor: 2, etiqueta: 'Natividad Yujra', detalle: 'CI 5392724 LP · Desaguadero' },
  { valor: 3, etiqueta: 'Sabino Mamani', detalle: 'CI 68425647 LP · Chijipina Grande' },
];
const parcelasEjemplo = [{ valor: 1, etiqueta: 'Lote Cruz Pata', detalle: 'CHI-HYC-01' }];
const riegoEjemplo = [
  { valor: 'Secano', titulo: 'Secano', descripcion: 'Solo agua de lluvia', icono: 'ti-cloud-rain' },
  { valor: 'Riego', titulo: 'Riego', descripcion: 'Canal, pozo o aspersión', icono: 'ti-droplet' },
  { valor: 'Mixto', titulo: 'Mixto', descripcion: 'Lluvia y riego de apoyo', icono: 'ti-cloud-storm' },
];

const errorSuperficie = computed(() => {
  const n = Number(String(campos.superficie).replace(',', '.'));

  if (campos.superficie === '') return 'Escribe la superficie en hectáreas.';

  return n >= 0.01 && n <= 500 ? '' : 'La superficie debe estar entre 0,01 y 500 ha.';
});

// ----- tabla de ejemplo
const filas = [
  { id: 1, nombre: 'Lote Cruz Pata', codigo: 'CHI-HYC-01', productor: 'Humberto Yucra', comunidad: 'Chiarpata', superficie: 1.8, estado: 'Activa' },
  { id: 2, nombre: 'Ladera Santa Cruz', codigo: 'AYO-DCA-01', productor: 'Dominga Catari', comunidad: 'Ayo Ayo', superficie: 0.8, estado: 'En_descanso' },
  { id: 3, nombre: 'Pampa Jawira', codigo: 'CAR-BHU-02', productor: 'Basilio Huanca', comunidad: 'Carabuco', superficie: 2.4, estado: 'Con alerta' },
];

const columnas = [
  { clave: 'nombre', titulo: 'Parcela', secundario: (f) => `${f.codigo} · ${f.productor}` },
  { clave: 'comunidad', titulo: 'Comunidad' },
  { clave: 'superficie', titulo: 'Superficie', align: 'der' },
  { clave: 'estado', titulo: 'Estado' },
];

const busqueda = ref('');
const filtro = ref('todas');
const filtros = computed(() => [
  { valor: 'todas', etiqueta: 'Todas', conteo: filas.length },
  { valor: 'alerta', etiqueta: 'Con alerta', conteo: 1 },
  { valor: 'descanso', etiqueta: 'En descanso', conteo: 1 },
]);

const filasVisibles = computed(() => {
  const q = busqueda.value.trim().toLowerCase();

  return filas.filter((f) => {
    const coincide = !q || `${f.nombre} ${f.codigo} ${f.productor} ${f.comunidad}`.toLowerCase().includes(q);
    const filtrada = filtro.value === 'todas' || (filtro.value === 'alerta' && f.estado === 'Con alerta') || (filtro.value === 'descanso' && f.estado === 'En_descanso');

    return coincide && filtrada;
  });
});

const accionesFila = () => [
  { id: 'editar', etiqueta: 'Editar', icono: 'ti-pencil' },
  { id: 'estado', etiqueta: 'Cambiar estado', icono: 'ti-refresh' },
  { id: 'eliminar', etiqueta: 'Eliminar', icono: 'ti-trash', peligro: true },
];

const cargandoTabla = ref(false);
const resaltado = ref(null);

function recargarTabla() {
  cargandoTabla.value = true;
  setTimeout(() => (cargandoTabla.value = false), 1500);
}

watch(resaltado, (v) => v !== null && setTimeout(() => (resaltado.value = null), 2100));

function alAccion({ id, fila }) {
  toast.exito(`Acción «${id}» sobre ${fila.nombre}`);
}

// ----- paneles
const detalle = ref(null);
const detalleAbierto = computed({ get: () => detalle.value !== null, set: (v) => !v && (detalle.value = null) });

const formularioAbierto = ref(false);
const paso = ref(1);
const intentoPaso = ref(false);
const confirmarAbierto = ref(false);
const guardando = ref(false);

watch(formularioAbierto, (v) => {
  if (v) {
    paso.value = 1;
    intentoPaso.value = false;
  }
});

function validarPaso(actual) {
  intentoPaso.value = true;

  const ok = actual === 1 ? Boolean(campos.productor && campos.nombre.trim()) : actual === 2 ? !errorSuperficie.value : true;

  if (ok) intentoPaso.value = false;

  return ok;
}

function simularGuardado(alTerminar) {
  if (guardando.value) return;

  guardando.value = true;
  setTimeout(() => {
    guardando.value = false;
    if (typeof alTerminar === 'function') alTerminar();
  }, 1200);
}

function guardarFormulario() {
  simularGuardado(() => {
    formularioAbierto.value = false;
    resaltado.value = 3;
    toast.exito('Parcela de ejemplo «guardada» (no se guardó nada)', { texto: 'Ver', to: '/dashboard/parcelas' });
  });
}

</script>

<style scoped>
.guia {
  display: grid;
  gap: 20px;
  padding: 24px 28px 40px;
  max-width: 1200px;
}

.guia-bloque {
  display: grid;
  gap: 14px;
  padding: 22px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.guia-bloque h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
}

.guia-tarjeta-titulo {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
}

.guia-fila {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.guia-etiqueta-fila {
  width: 76px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  color: #4a6070;
}

.guia-rejilla {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 16px;
}

.guia-rejilla--4 {
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
}

.guia-colores {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 12px;
}

.guia-color {
  display: grid;
  gap: 4px;
  font-size: 13px;
}

.guia-muestra {
  display: grid;
  place-items: center;
  height: 56px;
  border: 1px solid #dce6ed;
  border-radius: 12px;
  font-weight: 800;
}

.guia-formatos {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-weight: 700;
}

@media (max-width: 767px) {
  .guia {
    padding: 16px;
  }
}
</style>
