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
                @input="form.name = soloLetras(form.name)"
              />
              <small class="field-help">Solo letras y espacios.</small>
            </div>

            <div class="form-group">
              <label>Ap. Paterno *</label>
              <input
                v-model="form.paterno"
                maxlength="80"
                placeholder="Ej. Pérez"
                @input="form.paterno = soloLetras(form.paterno)"
              />
              <small class="field-help">No se permiten números.</small>
            </div>

            <div class="form-group">
              <label>Ap. Materno</label>
              <input
                v-model="form.materno"
                maxlength="80"
                placeholder="Ej. García"
                @input="form.materno = soloLetras(form.materno)"
              />
              <small class="field-help">Opcional.</small>
            </div>

            <div class="form-group">
              <label>Carnet de identidad *</label>
              <input
                v-model="form.ci"
                inputmode="numeric"
                maxlength="10"
                placeholder="Ej. 12345678"
                @input="form.ci = soloNumeros(form.ci, 10)"
              />
              <small class="field-help">Entre 5 y 10 dígitos.</small>
            </div>

            <div class="form-group">
              <label>Extensión CI</label>
              <select v-model="form.ci_extension">
                <option value="">Sin extensión</option>
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
            </div>

            <div class="form-group">
              <label>Sexo *</label>
              <select v-model="form.sexo">
                <option value="">Selecciona</option>
                <option value="Masculino">Masculino</option>
                <option value="Femenino">Femenino</option>
                <option value="Otro">Otro</option>
                <option value="Prefiero no decir">Prefiero no decir</option>
              </select>
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
              />
            </div>

            <div class="form-group">
              <label>Celular</label>
              <input
                v-model="form.celular"
                inputmode="numeric"
                maxlength="8"
                placeholder="Ej. 70000000"
                @input="form.celular = soloNumeros(form.celular, 8)"
              />
              <small class="field-help">Exactamente 8 dígitos si se registra.</small>
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
              />
            </div>

            <div class="form-group">
              <label>Área / Unidad *</label>
              <input
                v-model.trim="form.area"
                maxlength="100"
                placeholder="Ej. Producción agrícola"
              />
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
              <select v-model="form.id_rol">
                <option value="">Selecciona un rol</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">
                  {{ r.nombre }}
                </option>
              </select>
            </div>

            <div v-if="mode === 'edit'" class="form-group">
              <label>Estado</label>
              <select v-model="form.activo">
                <option :value="true">Activo</option>
                <option :value="false">Inactivo</option>
              </select>
            </div>

            <div class="form-group" :class="{ 'span-2': mode === 'edit' }">
              <label>
                {{ mode === 'create' ? 'Contraseña *' : 'Nueva contraseña' }}
                <small v-if="mode === 'edit'">(opcional)</small>
              </label>

              <input
                v-model="form.password"
                type="password"
                :placeholder="mode === 'create'
                  ? 'Mínimo 8 caracteres'
                  : 'Dejar vacío para no cambiar'"
              />
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
import { reactive, ref, watch } from 'vue';

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

watch(
  () => [props.mode, props.user, props.roles],
  () => {
    resetForm();
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
  localError.value = '';

  form.name = soloLetras(form.name).trim();
  form.paterno = soloLetras(form.paterno).trim();
  form.materno = soloLetras(form.materno).trim();
  form.ci = soloNumeros(form.ci, 10);
  form.celular = soloNumeros(form.celular, 8);

  let message = validarTextoCampo(form.name, 'Nombres', true);
  if (message) return showError(message);

  message = validarTextoCampo(form.paterno, 'Apellido paterno', true);
  if (message) return showError(message);

  message = validarTextoCampo(form.materno, 'Apellido materno', false);
  if (message) return showError(message);

  if (!form.ci) {
    return showError('El carnet de identidad es obligatorio.');
  }

  if (form.ci.length < 5 || form.ci.length > 10) {
    return showError('El carnet de identidad debe tener entre 5 y 10 dígitos.');
  }

  if (!form.sexo) {
    return showError('Debes seleccionar el sexo.');
  }

  if (!form.email) {
    return showError('El correo electrónico es obligatorio.');
  }

  if (!esCorreoValido(form.email)) {
    return showError('Ingresa un correo electrónico válido.');
  }

  if (form.celular && form.celular.length !== 8) {
    return showError('El número de celular debe tener exactamente 8 dígitos.');
  }

  if (!form.cargo) {
    return showError('El cargo es obligatorio.');
  }

  if (!form.area) {
    return showError('El área o unidad es obligatoria.');
  }

  if (!form.id_rol) {
    return showError('Debes seleccionar un rol.');
  }

  if (props.mode === 'create' && !form.password) {
    return showError('La contraseña es obligatoria para crear un usuario.');
  }

  if (form.password && form.password.length < 8) {
    return showError('La contraseña debe tener al menos 8 caracteres.');
  }

  const payload = {
    name: form.name,
    paterno: form.paterno,
    materno: form.materno || null,
    ci: form.ci,
    ci_extension: form.ci_extension || null,
    sexo: form.sexo,
    email: form.email,
    celular: form.celular || null,
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