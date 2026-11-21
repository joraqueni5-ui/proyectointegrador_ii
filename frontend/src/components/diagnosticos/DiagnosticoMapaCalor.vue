<template>
  <section class="mapa-calor">
    <div class="mapa-calor-par">
      <figure class="mapa-calor-figura">
        <div class="mapa-calor-marco">
          <img
            v-if="urlOriginal && !falloOriginal"
            :src="urlOriginal"
            alt="Fotografía original de la hoja"
            @error="falloOriginal = true"
          />
          <div v-else class="mapa-calor-vacio">
            <i class="ti ti-photo-off"></i>
            <span>No se pudo cargar la fotografía.</span>
          </div>
        </div>
        <figcaption>
          <strong>Foto original</strong>
          <span>Lo que se envió a la IA</span>
        </figcaption>
      </figure>

      <figure class="mapa-calor-figura">
        <div class="mapa-calor-marco">
          <img
            v-if="urlMapa && !falloMapa"
            :src="urlMapa"
            alt="Mapa de calor Grad-CAM sobre la hoja"
            @error="falloMapa = true"
          />
          <div v-else class="mapa-calor-vacio">
            <i class="ti ti-flame-off"></i>
            <span>{{ mensajeSinMapa }}</span>
          </div>
        </div>
        <figcaption>
          <strong>Mapa de calor (Grad-CAM)</strong>
          <span>Zonas que más influyeron en la decisión</span>
        </figcaption>
      </figure>
    </div>

    <div class="mapa-calor-leyenda">
      <div class="mapa-calor-escala" aria-hidden="true">
        <span>Poca influencia</span>
        <div class="mapa-calor-degradado"></div>
        <span>Mucha influencia</span>
      </div>

      <p>
        <i class="ti ti-info-circle"></i>
        <span>
          Las zonas <b>rojas y amarillas</b> son las que más pesaron para que la IA eligiera
          <b>{{ claseExplicada }}</b>; las azules casi no influyeron. Es una ayuda visual para entender
          la decisión: <b>no mide el daño real</b> de la hoja ni reemplaza el criterio del agrónomo.
        </span>
      </p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

import { urlArchivo } from '@/services/api';

const props = defineProps({
  diagnostico: {
    type: Object,
    default: null,
  },
});

const falloOriginal = ref(false);
const falloMapa = ref(false);

// Si se abre otro diagnóstico, se vuelve a intentar cargar sus imágenes.
watch(
  () => props.diagnostico?.id,
  () => {
    falloOriginal.value = false;
    falloMapa.value = false;
  }
);

const urlOriginal = computed(() => {
  const ruta = props.diagnostico?.ruta_fotografia;

  return ruta ? urlArchivo(ruta) : '';
});

const urlMapa = computed(() => {
  const ruta = props.diagnostico?.ruta_mapa_calor;

  return ruta ? urlArchivo(ruta) : '';
});

const claseExplicada = computed(() => {
  return props.diagnostico?.detalle_inferencia?.mapa_calor?.clase_explicada
    || props.diagnostico?.enfermedad?.nombre_comun
    || 'la clase predicha';
});

const mensajeSinMapa = computed(() => {
  if (falloMapa.value) {
    return 'No se pudo cargar el mapa de calor.';
  }

  return 'Este diagnóstico no tiene mapa de calor: se registró antes de esta función o la IA no pudo generarlo.';
});
</script>

<style scoped>
.mapa-calor {
  display: grid;
  gap: 12px;
}

.mapa-calor-par {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.mapa-calor-figura {
  margin: 0;
  min-width: 0;
}

.mapa-calor-marco {
  display: grid;
  place-items: center;
  height: 260px;
  overflow: hidden;
  border: 1px solid var(--gris-200);
  border-radius: 16px;
  background: #0f172a;
}

.mapa-calor-marco img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.mapa-calor-vacio {
  display: grid;
  gap: 8px;
  justify-items: center;
  padding: 16px;
  color: #cbd5e1;
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.4;
  text-align: center;
}

.mapa-calor-vacio i {
  font-size: 30px;
  color: #94a3b8;
}

.mapa-calor-figura figcaption {
  display: grid;
  gap: 2px;
  margin-top: 8px;
  text-align: center;
}

.mapa-calor-figura figcaption strong {
  color: var(--gris-950);
  font-size: 13px;
  font-weight: 900;
}

.mapa-calor-figura figcaption span {
  color: var(--gris-500);
  font-size: 11.5px;
  font-weight: 600;
}

.mapa-calor-leyenda {
  padding: 12px 14px;
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  background: #f8fafc;
}

.mapa-calor-escala {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--gris-500);
  font-size: 11px;
  font-weight: 800;
}

.mapa-calor-degradado {
  flex: 1;
  height: 10px;
  border-radius: 999px;
  background: linear-gradient(90deg, #1e3a8a, #06b6d4, #4ade80, #facc15, #ef4444);
}

.mapa-calor-leyenda p {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 10px 0 0;
  color: var(--gris-700);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.5;
}

.mapa-calor-leyenda p i {
  margin-top: 2px;
  color: var(--verde-700);
  font-size: 16px;
}

@media (max-width: 640px) {
  .mapa-calor-par {
    grid-template-columns: 1fr;
  }

  .mapa-calor-marco {
    height: 220px;
  }
}
</style>
