<template>
  <div class="modal-overlay user-modal-overlay" @click.self="close">
    <div class="modal-card user-form-modal">
      <div class="modal-header user-modal-header">
        <div>
          <div class="modal-title">
            {{ mode === 'create' ? 'Nuevo usuario' : 'Editar usuario' }}
          </div>
          <p class="user-modal-subtitle">
            {{ mode === 'create'
              ? 'Registra un usuario interno para el sistema PROINPA.'
              : 'Actualiza los datos personales, institucionales y de acceso.'
            }}
          </p>
        </div>

        <button class="modal-close" type="button" @click="close">
          ✕
        </button>
      </div>

      <div class="modal-body">
        <div class="user-form-section">
          <div class="user-form-section-title">
            <i class="ti ti-user"></i>
            Datos personales
          </div>

          <div class="user-form-grid">
            <div class="form-group">
              <label>Nombres *</label>
              <input
                v-model="form.name"
                maxlength="80"
                placeholder="Ej. Juan Carlos"
                :class="{ invalido: errores.name }"
                @input="form.name = soloLetras(form.name); revalidar('name')"
                @blur="validarCampo('name')"
              />
              <small v-if="errores.name" class="field-error">{{ errores.name }}</small>
              <small v-else class="field-help">Solo letras y espacios.</small>
            </div>

            <div class="form-group">
              <label>Ap. Paterno *</label>
              <input
                v-model="form.paterno"
                maxlength="80"
                placeholder="Ej. Pérez"
                :class="{ invalido: errores.paterno }"
                @input="form.paterno = soloLetras(form.paterno); revalidar('paterno')"
                @blur="validarCampo('paterno')"
              />
              <small v-if="errores.paterno" class="field-error">{{ errores.paterno }}</small>
              <small v-else class="field-help">No se permiten números.</small>
            </div>

            <div class="form-group">
              <label>Ap. Materno</label>
              <input
                v-model="form.materno"
                maxlength="80"
                placeholder="Ej. García"
                :class="{ invalido: errores.materno }"
                @input="form.materno = soloLetras(form.materno); revalidar('materno')"
                @blur="validarCampo('materno')"
              />
              <small v-if="errores.materno" class="field-error">{{ errores.materno }}</small>
              <small v-else class="field-help">Opcional.</small>
            </div>

            <div class="form-group">
              <label>Carnet de identidad *</label>
              <input
                v-model="form.ci"
                inputmode="numeric"
                maxlength="10"
                placeholder="Ej. 12345678"
                :class="{ invalido: errores.ci }"
                @input="form.ci = soloNumeros(form.ci, 10); revalidar('ci')"
                @blur="validarCampo('ci')"
              />
              <small v-if="errores.ci" class="field-error">{{ errores.ci }}</small>
              <small v-else class="field-help">Entre 5 y 10 dígitos.</small>
            </div>

            <div class="form-group">
              <label>Extensión CI *</label>
              <select
                v-model="form.ci_extension"
                :class="{ invalido: errores.ci_extension }"
                @change="validarCampo('ci_extension')"
              >
                <option value="">Selecciona</option>
                <option value="LP">LP</option>
                <option value="CB">CB</option>
                <option value="SC">SC</option>
                <option value="OR">OR</option>
                <option value="PT">PT</option>
                <option value="CH">CH</option>
                <option value="TJ">TJ</option>
                <option value="BE">BE</option>
                <option value="PD">PD</option>
              </select>
              <small v-if="errores.ci_extension" class="field-error">{{ errores.ci_extension }}</small>
            </div>

            <div class="form-group">
              <label>Sexo *</label>
              <select
                v-model="form.sexo"
                :class="{ invalido: errores.sexo }"
                @change="validarCampo('sexo')"
              >
                <option value="">Selecciona</option>
                <option value="Masculino">Masculino</option>
                <option value="Femenino">Femenino</option>
                <option value="Otro">Otro</option>
                <option value="Prefiero no decir">Prefiero no decir</option>
              </select>
              <small v-if="errores.sexo" class="field-error">{{ errores.sexo }}</small>
            </div>
          </div>
        </div>

        <div class="user-form-section">
          <div class="user-form-section-title">
            <i class="ti ti-address-book"></i>
            Datos de contacto
          </div>

          <div class="user-form-grid">
            <div class="form-group span-2">
              <label>Correo electrónico *</label>
              <input
                v-model.trim="form.email"
                type="email"
                maxlength="120"
                placeholder="correo@proinpa.org"
                :class="{ invalido: errores.email }"
                @input="revalidar('email')"
                @blur="validarCampo('email')"
              />
              <small v-if="errores.email" class="field-error">{{ errores.email }}</small>
            </div>

            <div class="form-group">
              <label>Celular *</label>
              <input
                v-model="form.celular"
                inputmode="numeric"
                maxlength="8"
                placeholder="Ej. 71234567"
                :class="{ invalido: errores.celular }"
                @input="form.celular = soloNumeros(form.celular, 8); revalidar('celular')"
                @blur="validarCampo('celular')"
              />
              <small v-if="errores.celular" class="field-error">{{ errores.celular }}</small>
              <small v-else class="field-help">8 dígitos; empieza con 6 o 7.</small>
            </div>
          </div>
        </div>

        <div class="user-form-section">
          <div class="user-form-section-title">
            <i class="ti ti-building"></i>
            Datos institucionales
          </div>

          <div class="user-form-grid">
            <div class="form-group">
              <label>Cargo *</label>
              <input
                v-model.trim="form.cargo"
                maxlength="100"
                placeholder="Ej. Técnico de campo"
                :class="{ invalido: errores.cargo }"
                @input="revalidar('cargo')"
                @blur="validarCampo('cargo')"
              />
              <small v-if="errores.cargo" class="field-error">{{ errores.cargo }}</small>
            </div>

            <div class="form-group">
              <label>Área / Unidad *</label>
              <input
                v-model.trim="form.area"
                maxlength="100"
                placeholder="Ej. Producción agrícola"
                :class="{ invalido: errores.area }"
                @input="revalidar('area')"
                @blur="validarCampo('area')"
              />
              <small v-if="errores.area" class="field-error">{{ errores.area }}</small>
            </div>

            <div class="form-group">
              <label>Oficina regional</label>
              <input
                v-model.trim="form.oficina_regional"
                maxlength="100"
                placeholder="Ej. La Paz"
              />
            </div>
          </div>
        </div>

        <div class="user-form-section">
          <div class="user-form-section-title">
            <i class="ti ti-lock"></i>
            Datos de acceso
          </div>

          <div class="user-form-grid">
            <div class="form-group">
              <label>Rol *</label>
              <select
                v-model="form.id_rol"
                :class="{ invalido: errores.id_rol }"
                :disabled="mode === 'edit' && esPropioUsuario"
                @change="validarCampo('id_rol')"
              >
                <option value="">Selecciona un rol</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">
                  {{ r.nombre }}
                </option>
              </select>
              <small v-if="errores.id_rol" class="field-error">{{ errores.id_rol }}</small>
              <small v-else-if="mode === 'edit' && esPropioUsuario" class="field-help">
                No puedes cambiar tu propio rol.
              </small>
            </div>

            <div v-if="mode === 'edit'" class="form-group">
              <label>Estado</label>
              <select v-model="form.activo" :disabled="esPropioUsuario">
                <option :value="true">Activo</option>
                <option :value="false">Inactivo</option>
              </select>
              <small v-if="esPropioUsuario" class="field-help">No puedes desactivar tu propia cuenta.</small>
            </div>

            <div class="form-group" :class="{ 'span-2': mode === 'edit' }">
              <label>
                {{ mode === 'create' ? 'Contraseña *' : 'Nueva contraseña' }}
                <small v-if="mode === 'edit'">(opcional)</small>
              </label>

              <input
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :class="{ invalido: errores.password }"
                :placeholder="mode === 'create'
                  ? 'Mínimo 8 caracteres'
                  : 'Dejar vacío para no cambiar'"
                @input="revalidar('password')"
                @blur="validarCampo('password')"
              />
              <small v-if="errores.password" class="field-error">{{ errores.password }}</small>
              <small v-else class="field-help">
                8 o más caracteres con mayúscula, minúscula, número y símbolo (por ejemplo ! # $ %).
              </small>
            </div>
          </div>
        </div>

        <div v-if="localError || error" class="user-form-error">
          {{ localError || error }}
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-s" type="button" @click="close">
          Cancelar
        </button>

        <button class="btn btn-p" type="button" :disabled="saving" @click="submit">
          <i class="ti ti-check"></i>
          {{ saving ? 'Guardando...' : mode === 'create' ? 'Crear usuario' : 'Guardar cambios' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';

import {
  celularBoliviano,
  ciDigitos,
  contrasenaSegura,
  correo,
  cuando,
  longitud,
  requerido,
  soloLetras as reglaSoloLetras,
  unoDe,
  useValidacion,
} from '@/utils/validaciones';
import { usuarioGuardado } from '@/utils/permisos';

const props = defineProps({
  // Error devuelto por el servidor al guardar.
  error: {
    type: String,
    default: '',
  },
  mode: {
    type: String,
    default: 'create',
  },
  user: {
    type: Object,
    default: null,
  },
  roles: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'save']);

const localError = ref('');
const form = reactive(defaultForm());

// Quien edita su propia cuenta no puede desactivarse ni cambiarse el rol (el backend también lo impide).
const esPropioUsuario = computed(() => Number(props.user?.id) === Number(usuarioGuardado()?.id));

// Mismas reglas que StoreUserRequest / UpdateUserRequest del backend.
const { errores, validarCampo, validarTodo, limpiar, primerCampoConError } = useValidacion(form, {
  name: [requerido('El nombre es obligatorio.'), reglaSoloLetras('El nombre'), longitud(3, 80, 'El nombre')],
  paterno: [requerido('El apellido paterno es obligatorio.'), reglaSoloLetras('El apellido paterno'), longitud(3, 80, 'El apellido paterno')],
  materno: [reglaSoloLetras('El apellido materno'), longitud(3, 80, 'El apellido materno')],
  ci: [requerido('El carnet de identidad es obligatorio.'), ciDigitos()],
  ci_extension: [requerido('Selecciona la extensión del carnet (por ejemplo LP).'), unoDe(['LP', 'CB', 'SC', 'OR', 'PT', 'CH', 'TJ', 'BE', 'PD'], 'La extensión del CI no es válida.')],
  sexo: [requerido('Debes seleccionar el sexo.')],
  email: [requerido('El correo electrónico es obligatorio.'), correo()],
  celular: [requerido('El celular es obligatorio.'), celularBoliviano()],
  cargo: [requerido('El cargo es obligatorio.'), longitud(0, 100, 'El cargo')],
  area: [requerido('El área o unidad es obligatoria.'), longitud(0, 100, 'El área o unidad')],
  id_rol: [requerido('Debes seleccionar un rol.')],
  password: [
    cuando((f) => props.mode === 'create', requerido('La contraseña es obligatoria para crear un usuario.')),
    contrasenaSegura(),
  ],
});

// Mientras se escribe, solo se actualiza el mensaje de un campo que ya tenía error.
function revalidar(campo) {
  if (errores[campo]) {
    validarCampo(campo);
  }
}

watch(
  () => [props.mode, props.user, props.roles],
  () => {
    resetForm();
    limpiar(); // sin mensajes de error al abrir el formulario
  },
  { immediate: true }
);

function defaultForm() {
  return {
    name: '',
    paterno: '',
    materno: '',
    ci: '',
    ci_extension: '',
    sexo: '',
    email: '',
    celular: '',
    cargo: '',
    area: '',
    oficina_regional: '',
    id_rol: '',
    activo: true,
    password: '',
  };
}

function resetForm() {
  localError.value = '';

  if (props.mode === 'edit' && props.user) {
    form.name = soloLetras(props.user.name || '');
    form.paterno = soloLetras(props.user.paterno || '');
    form.materno = soloLetras(props.user.materno || '');
    form.ci = soloNumeros(props.user.ci || '', 10);
    form.ci_extension = props.user.ci_extension || '';
    form.sexo = props.user.sexo || '';
    form.email = props.user.email || '';
    form.celular = soloNumeros(props.user.celular || '', 8);
    form.cargo = props.user.cargo || '';
    form.area = props.user.area || '';
    form.oficina_regional = props.user.oficina_regional || '';
    form.id_rol = props.user.id_rol || props.user.rol?.id || '';
    form.activo = Boolean(props.user.activo);
    form.password = '';
    return;
  }

  const base = defaultForm();

  Object.keys(base).forEach((key) => {
    form[key] = base[key];
  });

  form.id_rol = props.roles[0]?.id || '';
}

function soloLetras(value) {
  return String(value || '')
    .replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]/g, '')
    .replace(/\s{2,}/g, ' ')
    .trimStart();
}

function soloNumeros(value, max = 8) {
  return String(value || '')
    .replace(/\D/g, '')
    .slice(0, max);
}

function esCorreoValido(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validarTextoCampo(value, label, obligatorio = false) {
  const texto = String(value || '').trim();

  if (obligatorio && !texto) {
    return `${label} es obligatorio.`;
  }

  if (texto && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]+$/.test(texto)) {
    return `${label} solo debe contener letras y espacios.`;
  }

  if (texto && texto.length < 3) {
    return `${label} debe tener al menos 3 caracteres.`;
  }

  return '';
}

function submit() {
  if (props.saving) {
    return; // evita registros duplicados por doble clic
  }

  localError.value = '';

  form.name = soloLetras(form.name).trim();
  form.paterno = soloLetras(form.paterno).trim();
  form.materno = soloLetras(form.materno).trim();
  form.ci = soloNumeros(form.ci, 10);
  form.celular = soloNumeros(form.celular, 8);

  if (!validarTodo()) {
    const primero = primerCampoConError();

    localError.value = 'Revisa los campos marcados en rojo antes de guardar.';
    document.querySelector(`.user-form-modal .invalido`)?.focus?.();

    return primero;
  }

  const payload = {
    name: form.name,
    paterno: form.paterno,
    materno: form.materno || null,
    ci: form.ci,
    ci_extension: form.ci_extension,
    sexo: form.sexo,
    email: form.email,
    celular: form.celular,
    cargo: form.cargo,
    area: form.area,
    oficina_regional: form.oficina_regional || null,
    id_rol: form.id_rol,
    activo: form.activo,
    password: form.password,
  };

  if (!payload.password) {
    delete payload.password;
  }

  emit('save', payload);
}

function showError(message) {
  localError.value = message;
}

function close() {
  emit('close');
}
</script>