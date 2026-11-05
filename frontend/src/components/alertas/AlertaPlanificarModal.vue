<template>
  <div class="modal-overlay alerta-modal-overlay" @click.self="$emit('close')">
    <div class="modal alerta-atender-modal">
      <!-- =====================================================
           CABECERA
      ====================================================== -->

      <div class="alerta-atender-header">
        <div class="alerta-atender-icon">
          <i class="ti ti-clipboard-text"></i>
        </div>

        <div class="alerta-atender-header-info">
          <h3>Crear plan de manejo</h3>

          <p>
            Asigna un responsable, define las acciones de manejo y programa la siguiente revisión de
            la parcela.
          </p>
        </div>

        <button type="button" class="modal-close" :disabled="saving" @click="$emit('close')">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <!-- =====================================================
           CUERPO
      ====================================================== -->

      <div class="alerta-atender-body">
        <!-- =================================================
             ALERTA SELECCIONADA
        ================================================== -->

        <div class="alerta-atender-summary">
          <span> ALERTA SELECCIONADA </span>

          <strong>
            {{ alerta?.titulo || 'Alerta fitosanitaria' }}
          </strong>

          <p>
            {{ alerta?.mensaje || 'Sin información adicional.' }}
          </p>
        </div>

        <!-- =================================================
             RESPONSABLE
        ================================================== -->

        <div class="form-group">
          <label for="responsableAlerta">
            Responsable

            <span class="required"> * </span>
          </label>

          <select
            id="responsableAlerta"
            v-model="form.id_responsable"
            :disabled="saving || cargandoTecnicos"
          >
            <option value="">
              {{ cargandoTecnicos ? 'Cargando responsables...' : 'Selecciona un responsable' }}
            </option>

            <option v-for="tecnico in tecnicos" :key="tecnico.id" :value="String(tecnico.id)">
              {{ etiquetaTecnico(tecnico) }}
            </option>
          </select>

          <small v-if="errorTecnicos" class="campo-ayuda-error">
            {{ errorTecnicos }}
          </small>

          <small v-else class="campo-ayuda">
            Selecciona al técnico, agrónomo o administrador responsable del seguimiento.
          </small>
        </div>

        <!-- =================================================
             PLAN DE MANEJO
        ================================================== -->

        <div class="form-group">
          <label for="planManejo">
            Plan de manejo

            <span class="required"> * </span>
          </label>

          <textarea
            id="planManejo"
            v-model="form.plan_manejo"
            maxlength="3000"
            :disabled="saving"
            placeholder="Ej. Realizar inspección detallada de la parcela, retirar material vegetal gravemente afectado y aplicar las medidas fitosanitarias definidas por el responsable agronómico..."
          ></textarea>

          <div class="revision-char-count">{{ form.plan_manejo.length }}/3000</div>
        </div>

        <!-- =================================================
             PRÓXIMA REVISIÓN
        ================================================== -->

        <div class="form-group">
          <label for="proximaRevision">
            Próxima revisión

            <span class="required"> * </span>
          </label>

          <input
            id="proximaRevision"
            v-model="form.proxima_revision"
            type="datetime-local"
            :min="fechaMinima"
            :disabled="saving"
          />

          <small class="campo-ayuda">
            Define cuándo deberá realizarse la siguiente evaluación de la parcela.
          </small>
        </div>

        <!-- =================================================
             INFORMACIÓN
        ================================================== -->

        <div class="revision-info-box">
          <i class="ti ti-info-circle"></i>

          <div>
            <strong> ¿Qué ocurrirá después? </strong>

            <p>
              La alerta cambiará de
              <b>En revisión</b> a <b>Planificada</b>. El tratamiento todavía no comenzará.
            </p>
          </div>
        </div>

        <!-- =================================================
             ERROR LOCAL
        ================================================== -->

        <div v-if="localError" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ localError }}
        </div>

        <!-- =================================================
             ERROR BACKEND
        ================================================== -->

        <div v-if="error" class="alerta-form-error">
          <i class="ti ti-alert-circle"></i>

          {{ error }}
        </div>
      </div>

      <!-- =====================================================
           FOOTER
      ====================================================== -->

      <div class="modal-footer">
        <button type="button" class="btn btn-s" :disabled="saving" @click="$emit('close')">
          Cancelar
        </button>

        <button
          type="button"
          class="btn btn-p"
          :disabled="saving || cargandoTecnicos"
          @click="guardar"
        >
          <i :class="saving ? 'ti ti-loader-2' : 'ti ti-device-floppy'"></i>

          {{ saving ? 'Guardando...' : 'Guardar plan' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import usuarioService from '@/services/modules/usuarioService';

/* ============================================================
   PROPS
============================================================ */

const props = defineProps({
  alerta: {
    type: Object,
    default: null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: '',
  },
});

/* ============================================================
   EMITS
============================================================ */

const emit = defineEmits(['close', 'save']);

/* ============================================================
   DATOS
============================================================ */

const tecnicos = ref([]);

const cargandoTecnicos = ref(false);

const errorTecnicos = ref('');

const localError = ref('');

const form = reactive({
  id_responsable: '',
  plan_manejo: '',
  proxima_revision: '',
});

/* ============================================================
   FECHA MÍNIMA
============================================================ */

const fechaMinima = computed(() => {
  const ahora = new Date();

  return new Intl.DateTimeFormat('sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/La_Paz',
  })
    .format(ahora)
    .replace(' ', 'T');
});

/* ============================================================
   MONTAJE
============================================================ */

onMounted(cargarTecnicos);

/* ============================================================
   REINICIAR AL CAMBIAR DE ALERTA
============================================================ */

watch(
  () => props.alerta?.id,

  () => {
    resetFormulario();
  },

  {
    immediate: true,
  }
);

/* ============================================================
   CARGAR TÉCNICOS
============================================================ */

async function cargarTecnicos() {
  cargandoTecnicos.value = true;

  errorTecnicos.value = '';

  try {
    const { data } = await usuarioService.listarTecnicosInspeccion();

    tecnicos.value = Array.isArray(data) ? data : [];
  } catch (err) {
    console.error(err);

    tecnicos.value = [];

    errorTecnicos.value = 'No se pudieron cargar los responsables disponibles.';
  } finally {
    cargandoTecnicos.value = false;
  }
}

/* ============================================================
   REINICIAR FORMULARIO
============================================================ */

function resetFormulario() {
  localError.value = '';

  form.id_responsable = props.alerta?.id_responsable ? String(props.alerta.id_responsable) : '';

  form.plan_manejo = props.alerta?.plan_manejo || '';

  form.proxima_revision = formatearParaInput(props.alerta?.proxima_revision);
}

/* ============================================================
   GUARDAR
============================================================ */

function guardar() {
  localError.value = '';

  /* RESPONSABLE */

  if (!form.id_responsable) {
    localError.value = 'Debes seleccionar un responsable.';

    return;
  }

  /* PLAN DE MANEJO */

  const plan = form.plan_manejo.trim();

  if (!plan) {
    localError.value = 'Debes registrar un plan de manejo.';

    return;
  }

  if (plan.length < 10) {
    localError.value = 'El plan de manejo debe tener al menos 10 caracteres.';

    return;
  }

  /* PRÓXIMA REVISIÓN */

  if (!form.proxima_revision) {
    localError.value = 'Debes programar la próxima revisión.';

    return;
  }

  const revision = new Date(form.proxima_revision);

  if (Number.isNaN(revision.getTime())) {
    localError.value = 'La fecha seleccionada no es válida.';

    return;
  }

  if (revision.getTime() <= new Date().getTime()) {
    localError.value = 'La próxima revisión debe ser una fecha futura.';

    return;
  }

  /* ENVIAR AL PADRE */

  emit('save', {
    id_responsable: Number(form.id_responsable),
    plan_manejo: plan,
    proxima_revision: form.proxima_revision,
  });
}

/* ============================================================
   TEXTO DEL TÉCNICO
============================================================ */

function etiquetaTecnico(tecnico) {
  const nombre = [tecnico?.name, tecnico?.paterno, tecnico?.materno].filter(Boolean).join(' ');

  const rol = tecnico?.rol?.nombre || tecnico?.cargo || 'Técnico';

  if (!nombre) {
    return rol;
  }

  return `${nombre} — ${rol}`;
}

/* ============================================================
   CONVERTIR FECHA A DATETIME-LOCAL
============================================================ */

function formatearParaInput(fecha) {
  if (!fecha) {
    return '';
  }

  const date = new Date(fecha);

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  return new Intl.DateTimeFormat('sv-SE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/La_Paz',
  })
    .format(date)
    .replace(' ', 'T');
}
</script>
