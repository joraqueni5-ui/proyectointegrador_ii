<template>
  <div class="ia-informe">
    <!-- Las 3 clases más probables -->
    <section class="ia-bloque">
      <h4>
        <i class="ti ti-chart-bar"></i>
        Las 3 clases más probables según la IA
      </h4>

      <div v-if="top3.length" class="ia-barras">
        <div
          v-for="(prediccion, indice) in top3"
          :key="indice"
          class="ia-barra"
          :class="{ principal: indice === 0 }"
        >
          <div class="ia-barra-cab">
            <span>{{ indice + 1 }}. {{ prediccion.nombre }}</span>
            <strong>{{ porcentaje(prediccion.confianza) }}%</strong>
          </div>

          <div class="ia-barra-pista">
            <div
              class="ia-barra-relleno"
              :class="`pos-${indice}`"
              :style="{ width: ancho(prediccion.confianza) }"
            ></div>
          </div>
        </div>
      </div>

      <p v-else class="ia-vacio">
        Este diagnóstico no guardó las probabilidades de la IA (por ejemplo, se registró manualmente).
      </p>
    </section>

    <!-- Margen, confianza y modelo -->
    <section class="ia-bloque ia-metricas">
      <div class="ia-metrica">
        <label>Margen entre la 1.ª y la 2.ª clase</label>
        <strong>{{ margen === null ? '—' : `${porcentaje(margen)} puntos` }}</strong>
        <small :class="`tono-${margenNivel.tono}`">{{ margenNivel.texto }}</small>
      </div>

      <div class="ia-metrica">
        <label>Confianza {{ calibrado ? 'calibrada' : 'del modelo' }}</label>
        <strong>{{ porcentaje(diagnostico?.porcentaje_confianza) }}%</strong>
        <small>{{ textoCalibracion }}</small>
      </div>

      <div class="ia-metrica">
        <label>Modelo de IA</label>
        <strong>{{ diagnostico?.modelo_version || '—' }}</strong>
        <small>{{ detalle?.modelo?.arquitectura || 'Versión registrada al momento del análisis' }}</small>
      </div>
    </section>

    <!-- Calidad de la fotografía -->
    <section class="ia-bloque">
      <h4>
        <i class="ti ti-photo-check"></i>
        Calidad de la fotografía
      </h4>

      <div v-if="medidores.length" class="ia-medidores">
        <div
          v-for="medidor in medidores"
          :key="medidor.clave"
          class="ia-medidor"
        >
          <div class="ia-medidor-cab">
            <span>{{ medidor.etiqueta }}</span>
            <span class="ia-chip" :class="`tono-${medidor.tono}`">{{ medidor.estado }}</span>
          </div>

          <strong>{{ medidor.valor }}</strong>

          <div class="ia-barra-pista pequena">
            <div
              class="ia-barra-relleno"
              :class="`tono-${medidor.tono}`"
              :style="{ width: medidor.ancho }"
            ></div>
          </div>

          <small>{{ medidor.ayuda }}</small>
        </div>
      </div>

      <p v-else class="ia-vacio">
        Este diagnóstico se registró antes de guardar las métricas de calidad de la foto.
      </p>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  diagnostico: {
    type: Object,
    default: null,
  },
});

const detalle = computed(() => props.diagnostico?.detalle_inferencia || null);

const top3 = computed(() => {
  const datos = props.diagnostico?.top3_predicciones;

  if (!Array.isArray(datos)) {
    return [];
  }

  return datos
    .map((p) => ({
      nombre: p.nombre || p.enfermedad || p.clase || 'Sin nombre',
      confianza: Number(p.confianza ?? p.probabilidad ?? 0),
    }))
    .slice(0, 3);
});

const margen = computed(() => {
  const guardado = detalle.value?.validacion_prediccion?.margen;

  if (guardado !== undefined && guardado !== null) {
    return Number(guardado);
  }

  if (top3.value.length >= 2) {
    return top3.value[0].confianza - top3.value[1].confianza;
  }

  return null;
});

const margenMinimo = computed(() => Number(detalle.value?.modelo?.margen_minimo ?? 8));

const margenNivel = computed(() => {
  if (margen.value === null) {
    return { tono: 'neutro', texto: 'Sin datos para calcular el margen.' };
  }

  if (margen.value >= 40) {
    return { tono: 'ok', texto: 'Muy claro: la IA distingue bien la clase ganadora.' };
  }

  if (margen.value >= 20) {
    return { tono: 'ok', texto: 'Claro: la clase ganadora destaca sobre las demás.' };
  }

  if (margen.value >= margenMinimo.value) {
    return { tono: 'medio', texto: 'Ajustado: conviene revisar la foto con atención.' };
  }

  return { tono: 'bajo', texto: 'Ambiguo: dos clases son casi igual de probables.' };
});

const calibrado = computed(() => Boolean(detalle.value?.modelo?.calibrado));

const textoCalibracion = computed(() => {
  if (calibrado.value) {
    return 'Calibrada: si la IA indica 80 %, acierta cerca de 80 de cada 100 casos parecidos.';
  }

  return 'Confianza que devuelve el modelo, sin calibración registrada.';
});

function numero(valor) {
  const n = Number(valor);

  return Number.isFinite(n) ? n : null;
}

function porcentaje(valor) {
  const n = numero(valor);

  return n === null
    ? '—'
    : n.toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function ancho(valor) {
  const n = numero(valor) ?? 0;

  return `${Math.max(0, Math.min(100, n))}%`;
}

function nivel(valor, bueno, regular) {
  if (valor >= bueno) return { tono: 'ok', estado: 'Buena' };
  if (valor >= regular) return { tono: 'medio', estado: 'Regular' };

  return { tono: 'bajo', estado: 'Baja' };
}

const medidores = computed(() => {
  const calidad = detalle.value?.calidad_imagen;

  if (!calidad) {
    return [];
  }

  const lista = [];

  const nitidez = numero(calidad.nitidez ?? detalle.value?.validacion_nitidez?.nitidez);
  const umbralNitidez = numero(detalle.value?.validacion_nitidez?.umbral) ?? 0.42;

  if (nitidez !== null) {
    lista.push({
      clave: 'nitidez',
      etiqueta: 'Nitidez (enfoque)',
      valor: nitidez.toLocaleString('es-BO', { maximumFractionDigits: 2 }),
      ancho: `${Math.min(100, (nitidez / 0.9) * 100)}%`,
      ayuda: `Mínimo exigido: ${umbralNitidez.toLocaleString('es-BO')}. Más alto = más enfocada.`,
      ...nivel(nitidez, 0.6, umbralNitidez),
    });
  }

  const brillo = numero(calidad.brillo);

  if (brillo !== null) {
    const ideal = brillo >= 90 && brillo <= 180;
    const aceptable = brillo >= 35 && brillo <= 220;

    lista.push({
      clave: 'brillo',
      etiqueta: 'Iluminación',
      valor: `${Math.round(brillo)} de 255`,
      ancho: `${Math.min(100, (brillo / 255) * 100)}%`,
      ayuda: 'Lo ideal está entre 90 y 180: ni muy oscura ni muy clara.',
      tono: ideal ? 'ok' : aceptable ? 'medio' : 'bajo',
      estado: ideal ? 'Buena' : aceptable ? 'Regular' : 'Baja',
    });
  }

  const contraste = numero(calidad.contraste);

  if (contraste !== null) {
    lista.push({
      clave: 'contraste',
      etiqueta: 'Contraste',
      valor: contraste.toLocaleString('es-BO', { maximumFractionDigits: 1 }),
      ancho: `${Math.min(100, (contraste / 60) * 100)}%`,
      ayuda: 'Diferencia entre zonas claras y oscuras; ayuda a ver las lesiones.',
      ...nivel(contraste, 25, 8),
    });
  }

  const vegetacion = numero(detalle.value?.validacion_contenido?.vegetacion_pct);

  if (vegetacion !== null) {
    lista.push({
      clave: 'vegetacion',
      etiqueta: 'Material vegetal visible',
      valor: `${porcentaje(vegetacion)} %`,
      ancho: `${Math.min(100, vegetacion)}%`,
      ayuda: 'Parte de la foto que se ve como hoja o planta (verde).',
      ...nivel(vegetacion, 30, 8),
    });
  }

  if (calidad.resolucion) {
    const [w, h] = String(calidad.resolucion).split('x').map(Number);
    const lado = Math.min(w || 0, h || 0);

    lista.push({
      clave: 'resolucion',
      etiqueta: 'Resolución',
      valor: `${calidad.resolucion} px`,
      ancho: `${Math.min(100, (lado / 1000) * 100)}%`,
      ayuda: 'La CNN reduce la foto a 224×224; más detalle original ayuda.',
      ...nivel(lado, 400, 160),
    });
  }

  return lista;
});
</script>

<style scoped>
.ia-informe {
  display: grid;
  gap: 14px;
}

.ia-bloque {
  padding: 14px 16px;
  border: 1px solid var(--gris-200);
  border-radius: 14px;
  background: white;
}

.ia-bloque h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 12px;
  color: var(--verde-700);
  font-size: 13px;
  font-weight: 800;
}

.ia-barras {
  display: grid;
  gap: 10px;
}

.ia-barra-cab,
.ia-medidor-cab {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--gris-700);
  font-size: 12.5px;
  font-weight: 700;
}

.ia-barra.principal .ia-barra-cab {
  color: var(--gris-950);
  font-weight: 800;
}

.ia-barra-pista {
  height: 10px;
  margin-top: 5px;
  overflow: hidden;
  border-radius: 999px;
  background: var(--gris-100);
}

.ia-barra-pista.pequena {
  height: 6px;
  margin-top: 6px;
}

.ia-barra-relleno {
  height: 100%;
  min-width: 2px;
  border-radius: 999px;
  transition: width 0.35s ease;
}

.ia-barra-relleno.pos-0 { background: linear-gradient(90deg, #16a34a, #4ade80); }
.ia-barra-relleno.pos-1 { background: linear-gradient(90deg, #2563eb, #93c5fd); }
.ia-barra-relleno.pos-2 { background: linear-gradient(90deg, #9ca3af, #d1d5db); }
.ia-barra-relleno.tono-ok { background: #22c55e; }
.ia-barra-relleno.tono-medio { background: #f59e0b; }
.ia-barra-relleno.tono-bajo { background: #ef4444; }

.ia-metricas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.ia-metrica label {
  display: block;
  margin-bottom: 4px;
  color: var(--gris-500);
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.ia-metrica strong {
  display: block;
  overflow-wrap: anywhere;
  color: var(--gris-950);
  font-size: 15px;
  font-weight: 800;
}

.ia-metrica small,
.ia-medidor small {
  display: block;
  margin-top: 4px;
  color: var(--gris-500);
  font-size: 11.5px;
  font-weight: 600;
  line-height: 1.4;
}

.ia-metrica small.tono-ok { color: #15803d; }
.ia-metrica small.tono-medio { color: #b45309; }
.ia-metrica small.tono-bajo { color: #b91c1c; }

.ia-medidores {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px;
}

.ia-medidor {
  padding: 11px 12px;
  border: 1px solid var(--gris-100);
  border-radius: 12px;
  background: #f8fafc;
}

.ia-medidor strong {
  display: block;
  margin-top: 4px;
  color: var(--gris-950);
  font-size: 15px;
  font-weight: 800;
}

.ia-chip {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 800;
}

.ia-chip.tono-ok { background: #dcfce7; color: #166534; }
.ia-chip.tono-medio { background: #fef3c7; color: #92400e; }
.ia-chip.tono-bajo { background: #fee2e2; color: #991b1b; }

.ia-vacio {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f8fafc;
  color: var(--gris-500);
  font-size: 12.5px;
  font-weight: 600;
}
</style>
