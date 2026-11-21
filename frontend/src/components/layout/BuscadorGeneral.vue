<template>
  <div ref="raiz" class="buscador" :class="{ 'is-expandido': expandido }">
    <button type="button" class="buscador-icono" aria-label="Buscar" @click="expandir">
      <i class="ti ti-search" aria-hidden="true"></i>
    </button>

    <div class="buscador-caja">
      <i class="ti ti-search buscador-lupa" aria-hidden="true"></i>
      <label class="sr-only" for="buscador-general">Buscar parcelas, productores, productos o comunidades</label>
      <input
        id="buscador-general"
        ref="entrada"
        v-model="texto"
        type="search"
        autocomplete="off"
        maxlength="80"
        placeholder="Buscar parcelas, productores, productos…"
        role="combobox"
        :aria-expanded="abierto"
        aria-controls="buscador-resultados"
        @focus="abierto = true"
        @input="programarBusqueda"
        @keydown.down.prevent="mover(1)"
        @keydown.up.prevent="mover(-1)"
        @keydown.enter.prevent="elegirActual"
        @keydown.esc="cerrar"
      />
      <kbd class="buscador-atajo" aria-hidden="true">Ctrl K</kbd>
      <button v-if="expandido" type="button" class="buscador-cerrar" aria-label="Cerrar búsqueda" @click="cerrar">
        <i class="ti ti-x"></i>
      </button>
    </div>

    <div v-if="abierto && textoLimpio.length > 0" id="buscador-resultados" class="buscador-panel" role="listbox">
      <p v-if="textoLimpio.length < 2" class="buscador-nota">Escribe al menos 2 caracteres.</p>
      <p v-else-if="cargando && !planos.length" class="buscador-nota">Buscando…</p>
      <p v-else-if="error" class="buscador-nota buscador-error">{{ error }}</p>
      <p v-else-if="!planos.length" class="buscador-nota">Sin resultados para «{{ textoLimpio }}».</p>

      <template v-for="grupo in grupos" :key="grupo.clave">
        <div class="buscador-grupo">{{ grupo.titulo }}</div>
        <button
          v-for="r in grupo.items"
          :key="grupo.clave + r.id"
          type="button"
          role="option"
          class="buscador-item"
          :class="{ 'is-activo': planos[indice] === r }"
          :aria-selected="planos[indice] === r"
          @mousedown.prevent="elegir(r)"
        >
          <i :class="['ti', grupo.icono]" aria-hidden="true"></i>
          <span class="buscador-item-texto">
            <strong>{{ r.etiqueta }}</strong>
            <small v-if="r.detalle">{{ r.detalle }}</small>
          </span>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import buscarService from '@/services/modules/buscarService';

const router = useRouter();

const raiz = ref(null);
const entrada = ref(null);
const texto = ref('');
const abierto = ref(false);
const expandido = ref(false);
const cargando = ref(false);
const error = ref('');
const resultados = ref({});
const indice = ref(0);

let temporizador = null;
let controlador = null;

// Cada tipo de resultado lleva a su módulo con el texto buscado ya aplicado.
const TIPOS = [
  { clave: 'parcelas', titulo: 'Parcelas', icono: 'ti-map-pin', ruta: '/dashboard/parcelas' },
  { clave: 'productores', titulo: 'Productores', icono: 'ti-user', ruta: '/dashboard/productores' },
  { clave: 'productos', titulo: 'Productos', icono: 'ti-package', ruta: '/dashboard/inventario/productos' },
  { clave: 'comunidades', titulo: 'Comunidades', icono: 'ti-building-community', ruta: '/dashboard/comunidades' },
];

const textoLimpio = computed(() => texto.value.trim());

const grupos = computed(() =>
  TIPOS.map((t) => ({ ...t, items: resultados.value[t.clave] || [] })).filter((g) => g.items.length)
);

const planos = computed(() =>
  grupos.value.flatMap((g) => g.items.map((i) => Object.assign(i, { _ruta: g.ruta })))
);

function programarBusqueda() {
  clearTimeout(temporizador);
  error.value = '';
  indice.value = 0;

  if (textoLimpio.value.length < 2) {
    resultados.value = {};
    return;
  }

  temporizador = setTimeout(buscar, 250);
}

async function buscar() {
  controlador?.abort();
  controlador = new AbortController();
  cargando.value = true;

  try {
    const { data } = await buscarService.buscar(textoLimpio.value, controlador.signal);

    resultados.value = data || {};
    error.value = '';
  } catch (e) {
    if (e?.code === 'ERR_CANCELED') return;

    resultados.value = {};
    error.value = 'No se pudo buscar ahora. Intenta de nuevo.';
  } finally {
    cargando.value = false;
  }
}

function mover(paso) {
  if (!planos.value.length) return;

  indice.value = (indice.value + paso + planos.value.length) % planos.value.length;
}

function elegirActual() {
  const actual = planos.value[indice.value];

  if (actual) elegir(actual);
}

function elegir(resultado) {
  router.push({ path: resultado._ruta, query: { buscar: textoLimpio.value } });
  cerrar();
}

function expandir() {
  expandido.value = true;
  nextTick(() => entrada.value?.focus());
}

function cerrar() {
  abierto.value = false;
  expandido.value = false;
  texto.value = '';
  resultados.value = {};
  entrada.value?.blur();
}

function atajo(evento) {
  if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
    evento.preventDefault();
    expandido.value = true;
    nextTick(() => entrada.value?.focus());
  }
}

function clicFuera(evento) {
  if (raiz.value && !raiz.value.contains(evento.target)) {
    abierto.value = false;
    expandido.value = false;
  }
}

onMounted(() => {
  window.addEventListener('keydown', atajo);
  document.addEventListener('mousedown', clicFuera);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', atajo);
  document.removeEventListener('mousedown', clicFuera);
  clearTimeout(temporizador);
  controlador?.abort();
});
</script>

<style scoped>
.buscador {
  position: relative;
  flex: 1;
  max-width: 520px;
}

.buscador-icono {
  display: none;
}

.buscador-caja {
  display: flex;
  align-items: center;
  gap: 10px;
  height: 46px;
  padding: 0 12px;
  background: var(--gris-100);
  border: 2px solid transparent;
  border-radius: 12px;
}

.buscador-caja:focus-within {
  background: #fff;
  border-color: var(--verde-foco);
  box-shadow: var(--glow-verde);
}

.buscador-lupa {
  color: var(--texto-secundario);
  font-size: 18px;
}

.buscador-caja input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 14px;
  color: var(--texto);
}

.buscador-atajo {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  color: var(--texto-secundario);
  background: #fff;
  border: 1px solid var(--borde-fuerte);
  border-radius: 6px;
  padding: 2px 6px;
}

.buscador-cerrar {
  display: none;
  width: 36px;
  height: 36px;
  border: 0;
  background: transparent;
  font-size: 18px;
}

.buscador-panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  max-height: 70vh;
  overflow-y: auto;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
  padding: 8px;
  z-index: 100;
}

.buscador-nota {
  margin: 0;
  padding: 14px 12px;
  color: var(--texto-secundario);
  font-size: 13px;
}

.buscador-error {
  color: var(--rojo-700);
  font-weight: 600;
}

.buscador-grupo {
  padding: 10px 12px 4px;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--texto-secundario);
}

.buscador-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 48px;
  padding: 6px 12px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  text-align: left;
  font-family: inherit;
  cursor: pointer;
}

.buscador-item i {
  font-size: 18px;
  color: var(--verde-700);
}

.buscador-item.is-activo,
.buscador-item:hover {
  background: var(--verde-100);
}

.buscador-item-texto {
  display: grid;
  min-width: 0;
}

.buscador-item-texto strong {
  font-size: 14px;
  color: var(--texto);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.buscador-item-texto small {
  font-size: 12px;
  color: var(--texto-secundario);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

@media (max-width: 767px) {
  .buscador {
    flex: none;
    max-width: none;
  }

  .buscador-icono {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 12px;
    background: var(--gris-100);
    font-size: 20px;
    color: var(--texto);
  }

  .buscador-caja {
    display: none;
  }

  .buscador.is-expandido .buscador-caja {
    display: flex;
    position: fixed;
    top: 12px;
    left: 12px;
    right: 12px;
    z-index: 120;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.2);
  }

  .buscador.is-expandido .buscador-cerrar {
    display: grid;
    place-items: center;
  }

  .buscador-atajo {
    display: none;
  }

  .buscador.is-expandido .buscador-panel {
    position: fixed;
    top: 68px;
    left: 12px;
    right: 12px;
    z-index: 120;
  }
}
</style>
