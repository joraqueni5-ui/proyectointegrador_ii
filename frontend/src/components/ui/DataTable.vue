<template>
  <section class="ui-dt">
    <!-- Escritorio / tablet: tabla con scroll horizontal dentro de su caja -->
    <div class="ui-dt__caja">
      <table class="ui-dt__tabla">
        <thead>
          <tr>
            <th v-for="c in columnas" :key="c.clave" :class="`al-${c.align || 'izq'}`" :style="c.ancho ? { width: c.ancho } : null" scope="col">
              {{ c.titulo }}
            </th>
            <th v-if="conAcciones" class="al-der ui-dt__th-acc" scope="col"><span class="ui-dt__sr">Acciones</span></th>
          </tr>
        </thead>

        <tbody v-if="cargando">
          <tr v-for="n in esqueletos" :key="`e${n}`" class="ui-dt__esqueleto">
            <td v-for="c in columnas" :key="c.clave"><span class="ui-dt__barra"></span></td>
            <td v-if="conAcciones"><span class="ui-dt__barra ui-dt__barra--corta"></span></td>
          </tr>
        </tbody>

        <tbody v-else-if="filas.length">
          <tr v-for="fila in filas" :key="fila[claveFila]" :class="{ 'is-resaltada': resaltarId !== null && fila[claveFila] === resaltarId }">
            <td v-for="(c, i) in columnas" :key="c.clave" :class="[`al-${c.align || 'izq'}`, { 'es-principal': i === 0 }]">
              <slot :name="`celda-${c.clave}`" :fila="fila" :valor="fila[c.clave]">
                <template v-if="i === 0">
                  <strong class="ui-dt__nombre">{{ fila[c.clave] }}</strong>
                  <span v-if="valorSecundario(c, fila)" class="ui-dt__secundario">{{ valorSecundario(c, fila) }}</span>
                </template>
                <template v-else>{{ fila[c.clave] ?? '—' }}</template>
              </slot>
            </td>
            <td v-if="conAcciones" class="al-der ui-dt__acciones">
              <button v-if="mostrarVer" type="button" class="ui-dt__ver" @click="$emit('ver', fila)">Ver</button>
              <RowMenu v-if="acciones" :acciones="acciones(fila)" :etiqueta="String(fila[columnas[0]?.clave] ?? '')" @accion="(id) => $emit('accion', { id, fila })" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Celular: tarjetas -->
    <div class="ui-dt__tarjetas">
      <template v-if="cargando">
        <div v-for="n in 3" :key="`t${n}`" class="ui-dt__tarjeta ui-dt__esqueleto"><span class="ui-dt__barra"></span><span class="ui-dt__barra"></span></div>
      </template>
      <template v-else-if="filas.length">
        <article
          v-for="fila in filas"
          :key="`m${fila[claveFila]}`"
          class="ui-dt__tarjeta"
          :class="{ 'is-resaltada': resaltarId !== null && fila[claveFila] === resaltarId }"
        >
          <header class="ui-dt__tarjeta-cabeza">
            <div>
              <slot :name="`celda-${columnas[0].clave}`" :fila="fila" :valor="fila[columnas[0].clave]">
                <strong class="ui-dt__nombre">{{ fila[columnas[0].clave] }}</strong>
                <span v-if="valorSecundario(columnas[0], fila)" class="ui-dt__secundario">{{ valorSecundario(columnas[0], fila) }}</span>
              </slot>
            </div>
            <RowMenu v-if="acciones" :acciones="acciones(fila)" :etiqueta="String(fila[columnas[0]?.clave] ?? '')" @accion="(id) => $emit('accion', { id, fila })" />
          </header>
          <dl class="ui-dt__datos">
            <div v-for="c in columnas.slice(1)" :key="c.clave">
              <dt>{{ c.titulo }}</dt>
              <dd>
                <slot :name="`celda-${c.clave}`" :fila="fila" :valor="fila[c.clave]">{{ fila[c.clave] ?? '—' }}</slot>
              </dd>
            </div>
          </dl>
          <button v-if="mostrarVer" type="button" class="ui-dt__ver ui-dt__ver--tarjeta" @click="$emit('ver', fila)">Ver detalle</button>
        </article>
      </template>
    </div>

    <EmptyState v-if="!cargando && !filas.length" :icono="vacioIcono" :titulo="vacioTitulo" :texto="vacioTexto">
      <slot name="vacio" />
    </EmptyState>

    <footer v-if="total > 0" class="ui-dt__pie">
      <span class="ui-dt__conteo">Mostrando {{ filas.length }} de {{ total }}</span>
      <div v-if="totalPaginas > 1" class="ui-dt__paginas">
        <button type="button" class="ui-dt__pag" :disabled="pagina <= 1" @click="$emit('cambiar-pagina', pagina - 1)">Anterior</button>
        <span class="ui-dt__pagina-actual">Página {{ pagina }} de {{ totalPaginas }}</span>
        <button type="button" class="ui-dt__pag" :disabled="pagina >= totalPaginas" @click="$emit('cambiar-pagina', pagina + 1)">Siguiente</button>
      </div>
    </footer>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';
import RowMenu from './RowMenu.vue';

// Tabla de datos. columnas: [{ clave, titulo, align?: 'izq'|'der'|'centro', ancho?, secundario?: clave|fn }].
// La primera columna lleva el nombre en negrita y debajo un dato secundario gris.
// Celdas personalizadas con el slot `celda-<clave>` ({ fila, valor }).
// Acciones: enlace "Ver" + menú ⋯ (acciones(fila) devuelve [{ id, etiqueta, peligro? }]).
const props = defineProps({
  columnas: { type: Array, required: true },
  filas: { type: Array, default: () => [] },
  claveFila: { type: String, default: 'id' },
  cargando: { type: Boolean, default: false },
  esqueletos: { type: Number, default: 6 },
  acciones: { type: Function, default: null },
  mostrarVer: { type: Boolean, default: true },
  resaltarId: { type: [Number, String, null], default: null },
  pagina: { type: Number, default: 1 },
  porPagina: { type: Number, default: 15 },
  total: { type: Number, default: 0 },
  vacioTitulo: { type: String, default: 'No hay registros' },
  vacioTexto: { type: String, default: '' },
  vacioIcono: { type: String, default: 'ti-inbox' },
});

defineEmits(['ver', 'accion', 'cambiar-pagina']);

const conAcciones = computed(() => props.mostrarVer || Boolean(props.acciones));
const totalPaginas = computed(() => Math.max(1, Math.ceil(props.total / props.porPagina)));

function valorSecundario(columna, fila) {
  const s = columna.secundario;

  if (!s) return '';

  return typeof s === 'function' ? s(fila) : fila[s];
}
</script>

<style scoped>
.ui-dt {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.ui-dt__caja {
  overflow-x: auto;
}

.ui-dt__tabla {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
}

.ui-dt__tabla th {
  padding: 12px 14px;
  background: #f8fafc;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #4a6070;
  white-space: nowrap;
}

.ui-dt__tabla td {
  padding: 14px;
  border-top: 1px solid #eef2f6;
  font-size: 14px;
  color: #111827;
  vertical-align: middle;
}

.al-izq {
  text-align: left;
}

.al-der {
  text-align: right;
}

.al-centro {
  text-align: center;
}

.ui-dt__nombre {
  display: block;
  font-weight: 700;
}

.ui-dt__secundario {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: #4a6070;
}

.ui-dt__acciones {
  white-space: nowrap;
}

.ui-dt__ver {
  min-height: 44px;
  padding: 0 12px;
  border: 0;
  background: transparent;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  color: #1a5c3e;
  cursor: pointer;
  border-radius: 10px;
}

.ui-dt__ver:hover {
  background: #edfaf4;
  text-decoration: underline;
}

.ui-dt__ver:focus-visible,
.ui-dt__pag:focus-visible {
  outline: 3px solid #3da87a;
  outline-offset: 2px;
}

.ui-dt__sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

.is-resaltada td,
.ui-dt__tarjeta.is-resaltada {
  animation: ui-dt-resalta 2s ease both;
}

@keyframes ui-dt-resalta {
  0%,
  70% {
    background: #a8e6c6;
  }

  100% {
    background: transparent;
  }
}

.ui-dt__barra {
  display: block;
  height: 14px;
  width: 80%;
  border-radius: 8px;
  background: linear-gradient(90deg, #eef2f6 25%, #f8fafc 50%, #eef2f6 75%);
  background-size: 200% 100%;
  animation: ui-dt-brillo 1.2s linear infinite;
}

.ui-dt__barra--corta {
  width: 40%;
  margin-left: auto;
}

@keyframes ui-dt-brillo {
  to {
    background-position: -200% 0;
  }
}

.ui-dt__pie {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 12px 16px;
  border-top: 1px solid #eef2f6;
  font-size: 13px;
  color: #4a6070;
}

.ui-dt__paginas {
  display: flex;
  align-items: center;
  gap: 10px;
}

.ui-dt__pag {
  min-height: 44px;
  padding: 0 16px;
  background: #fff;
  border: 2px solid #cbd5e1;
  border-radius: 12px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 700;
  color: #111827;
  cursor: pointer;
}

.ui-dt__pag:disabled {
  background: #dce6ed;
  border-color: transparent;
  color: #4a6070;
  cursor: not-allowed;
}

/* Tarjetas en celular */
.ui-dt__tarjetas {
  display: none;
}

@media (max-width: 767px) {
  .ui-dt__caja {
    display: none;
  }

  .ui-dt__tarjetas {
    display: grid;
    gap: 10px;
    padding: 12px;
  }

  .ui-dt__tarjeta {
    padding: 14px;
    background: #fff;
    border: 1px solid #dce6ed;
    border-radius: 16px;
  }

  .ui-dt__tarjeta-cabeza {
    display: flex;
    justify-content: space-between;
    gap: 8px;
  }

  .ui-dt__datos {
    display: grid;
    gap: 8px;
    margin: 10px 0 0;
  }

  .ui-dt__datos > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
  }

  .ui-dt__datos dt {
    color: #4a6070;
  }

  .ui-dt__datos dd {
    margin: 0;
    font-weight: 600;
    text-align: right;
  }

  .ui-dt__ver--tarjeta {
    width: 100%;
    margin-top: 8px;
    border: 2px solid #dce6ed;
  }
}
</style>
