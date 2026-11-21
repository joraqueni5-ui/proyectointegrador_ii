<template>
  <main class="proinpa-auth-page">
    <!-- FONDO -->
    <div class="proinpa-scene">
      <div class="orb orb1"></div>
      <div class="orb orb2"></div>
      <div class="orb orb3"></div>

      <svg
        class="mountains"
        viewBox="0 0 1440 500"
        preserveAspectRatio="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <linearGradient id="m1" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#1a5c35" stop-opacity="0.55" />
            <stop offset="100%" stop-color="#0a2015" stop-opacity="0.9" />
          </linearGradient>
          <linearGradient id="m2" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#0e3a20" stop-opacity="0.7" />
            <stop offset="100%" stop-color="#061510" stop-opacity="1" />
          </linearGradient>
          <linearGradient id="m3" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#082018" stop-opacity="0.85" />
            <stop offset="100%" stop-color="#040e09" stop-opacity="1" />
          </linearGradient>
        </defs>

        <path
          d="M0,280 L80,160 L160,220 L260,80 L360,200 L460,120 L560,180 L660,60 L760,200 L860,100 L960,220 L1060,140 L1160,200 L1280,90 L1380,180 L1440,140 L1440,500 L0,500 Z"
          fill="url(#m1)"
        />
        <path
          d="M0,320 L120,200 L200,270 L320,130 L400,250 L520,160 L600,280 L720,150 L820,260 L940,170 L1040,280 L1160,190 L1260,260 L1380,210 L1440,260 L1440,500 L0,500 Z"
          fill="url(#m2)"
        />
        <path
          d="M0,400 L180,290 L300,370 L440,280 L580,360 L700,300 L860,390 L980,320 L1120,400 L1260,340 L1440,390 L1440,500 L0,500 Z"
          fill="url(#m3)"
        />
      </svg>

      <span
        v-for="star in stars"
        :key="star.id"
        class="star"
        :style="star.style"
      ></span>

      <span
        v-for="leaf in leaves"
        :key="leaf.id"
        class="leaf"
        :style="leaf.style"
      >
        <svg :width="leaf.size" :height="leaf.size" viewBox="0 0 20 20">
          <path
            d="M10,1 C5,5 0,10 3,16 C6,22 14,20 16,14 C18,8 15,2 10,1Z"
            fill="rgba(82,194,120,0.4)"
          />
        </svg>
      </span>
    </div>

    <!-- CONTENIDO -->
    <section class="proinpa-page-content">
      <!-- LADO MARCA -->
      <aside class="brand-side">
        <div class="logo-container">
          <img
            src="https://www.proinpa.org/web/wp-content/uploads/2024/01/Logo-PROINPA-2024-con-resplandor-2.png"
            alt="Logo Fundación PROINPA"
            class="logo-img"
          />
        </div>

        <p class="tagline">
          "Innovación tecnológica al servicio de la agricultura andina boliviana"
        </p>

        <div class="badges">
          <div class="badge">
            <div class="badge-dot green"></div>
            Ciencia y tecnología agropecuaria
          </div>

          <div class="badge">
            <div class="badge-dot gold"></div>
            Bioinsumos y agrobiodiversidad
          </div>

          <div class="badge">
            <div class="badge-dot pulse"></div>
            Acceso institucional seguro
          </div>
        </div>
      </aside>

      <!-- TARJETA LOGIN -->
      <section class="glass-card">
        <div class="card-shine"></div>

        <div class="card-head">
          <div class="card-head-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
              />
            </svg>
          </div>

          <h1 class="card-title">Portal de Acceso</h1>
          <p class="card-subtitle">Sistema de Gestión Institucional</p>
        </div>

        <!-- TABS -->
        <div class="tabs">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'credenciales' }"
            @click="activeTab = 'credenciales'"
          >
            Credenciales
          </button>

          <button
            v-if="FACE_RECOGNITION_ENABLED"
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'facial' }"
            @click="activeTab = 'facial'"
          >
            Reconocimiento facial
          </button>
        </div>

        <!-- LOGIN CREDENCIALES -->
        <form
          v-if="activeTab === 'credenciales'"
          class="login-form"
          @submit.prevent="handleLogin"
        >
          <div class="field">
            <label class="field-label">Correo institucional</label>
            <div class="field-wrap">
              <input
                v-model.trim="form.email"
                type="email"
                class="field-input"
                placeholder="usuario@proinpa.org"
                autocomplete="email"
                required
              />
              <span class="field-ico">✉</span>
            </div>
          </div>

          <div class="field">
            <label class="field-label">Contraseña</label>
            <div class="field-wrap">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="field-input"
                placeholder="••••••••••"
                autocomplete="current-password"
                required
              />
              <button
                type="button"
                class="field-ico field-button"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? 'Ocultar' : 'Ver' }}
              </button>
            </div>
          </div>

          <div class="between-row">
            <label class="remember-label" @click.prevent="remember = !remember">
              <span class="chk" :class="{ on: remember }"></span>
              Mantener sesión
            </label>

          </div>

          <div v-if="error" class="auth-message error">
            {{ error }}
          </div>

          <div v-if="success" class="auth-message success">
            {{ success }}
          </div>

          <button type="submit" class="btn-cta" :disabled="loading">
            {{ loading ? 'Autenticando...' : 'Ingresar al sistema' }}
          </button>

          <template v-if="FACE_RECOGNITION_ENABLED">
            <div class="divider">o continúa con</div>

            <button
              type="button"
              class="btn-ghost"
              @click="activeTab = 'facial'"
            >
              Usar reconocimiento facial
            </button>
          </template>
        </form>

        <!-- LOGIN FACIAL: contraseña primero; el rostro reemplaza al código OTP -->
        <div v-else class="face-panel">
          <div class="field">
            <label class="field-label">Correo institucional</label>
            <div class="field-wrap">
              <input
                v-model.trim="form.email"
                type="email"
                class="field-input"
                placeholder="usuario@proinpa.org"
                autocomplete="email"
              />
              <span class="field-ico">✉</span>
            </div>
          </div>

          <div class="field">
            <label class="field-label">Contraseña</label>
            <div class="field-wrap">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="field-input"
                placeholder="••••••••••"
                autocomplete="current-password"
                @keyup.enter="abrirLoginFacial"
              />
              <button
                type="button"
                class="field-ico field-button"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? 'Ocultar' : 'Ver' }}
              </button>
            </div>
          </div>

          <div class="face-zone">
            <div class="face-frame">
              <div class="halo"></div>
              <div class="halo"></div>
              <div class="halo"></div>

              <div class="face-oval">
                <div class="face-person">
                  <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M6 20v-1a6 6 0 0112 0v1" />
                  </svg>
                </div>

                <div class="scan-beam"></div>
                <div class="bk tl"></div>
                <div class="bk tr"></div>
                <div class="bk bl"></div>
                <div class="bk br"></div>
              </div>
            </div>

            <div class="face-status">
              <div class="status-ring"></div>
              Tu contraseña se valida primero; el rostro reemplaza al código OTP
            </div>
          </div>

          <div v-if="error" class="auth-message error">
            {{ error }}
          </div>

          <div v-if="success" class="auth-message success">
            {{ success }}
          </div>

          <button
            type="button"
            class="btn-cta"
            :disabled="loading"
            @click="abrirLoginFacial"
          >
            {{ loading ? 'Verificando...' : (loginToken ? 'Volver a escanear el rostro' : 'Verificar identidad') }}
          </button>

          <button
            v-if="loginToken"
            type="button"
            class="btn-ghost"
            :disabled="loading"
            @click="usarOtpEnLugarDelRostro"
          >
            Usar código OTP en su lugar
          </button>

          <div class="divider">o</div>

          <button
            type="button"
            class="btn-ghost"
            @click="activeTab = 'credenciales'"
          >
            Ingresar con contraseña
          </button>
        </div>

        <div class="card-footer">
          <span>¿Nuevo usuario? Solicita acceso al administrador.</span>
          <span>·</span>
          <span>Soporte técnico</span>
        </div>
      </section>

      <!-- ESTADÍSTICAS -->
      <aside class="stats-col">
        <div class="stat-mini">
          <strong>+30</strong>
          <span>Años de innovación</span>
        </div>

        <div class="stat-mini">
          <strong>120K</strong>
          <span>Hectáreas activas</span>
        </div>

        <div class="stat-mini">
          <strong>+8</strong>
          <span>Bioinsumos</span>
        </div>

        <div class="stat-mini">
          <strong>5</strong>
          <span>Oficinas regionales</span>
        </div>
      </aside>
    </section>

    <FaceCaptureModal
      v-if="showFaceModal"
      @captured="onFaceLoginCaptured"
      @close="showFaceModal = false"
    />
  </main>
  <footer class="version-footer">
  Copyright © PROINPA 2026 | v{{ appVersion }} | {{ appEnv }}
</footer>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import FaceCaptureModal from '@/components/FaceCaptureModal.vue';

import authService from '@/services/modules/authService';
import { FACE_RECOGNITION_ENABLED } from '@/services/api';
import { obtenerMensajeError } from '@/services/errorService';

import '@/styles/auth.css';

const router = useRouter();

const form = reactive({
  email: '',
  password: '',
});

const activeTab = ref('credenciales');
const loading = ref(false);
const error = ref('');
const success = ref('');
const showPassword = ref(false);
const remember = ref(false);
const showFaceModal = ref(false);
// Ticket de un solo uso que entrega el servidor después de validar la contraseña.
const loginToken = ref('');

const appVersion = import.meta.env.VITE_APP_VERSION || '1.0.0'
const appEnv = import.meta.env.VITE_APP_ENV || 'dev'

const stars = computed(() =>
  Array.from({ length: 80 }, (_, index) => {
    const size = Math.random() * 2 + 0.5;

    return {
      id: index,
      style: {
        width: `${size}px`,
        height: `${size}px`,
        top: `${Math.random() * 55}%`,
        left: `${Math.random() * 100}%`,
        '--dur': `${2 + Math.random() * 4}s`,
        animationDelay: `${Math.random() * 5}s`,
      },
    };
  })
);

const leaves = computed(() =>
  Array.from({ length: 10 }, (_, index) => {
    const scale = 0.5 + Math.random() * 0.6;

    return {
      id: index,
      size: 20 * scale,
      style: {
        left: `${Math.random() * 48}%`,
        '--ldur': `${10 + Math.random() * 14}s`,
        '--ldel': `${Math.random() * 12}s`,
      },
    };
  })
);

function limpiarSesionAnterior() {
  localStorage.removeItem('user_token');
  localStorage.removeItem('user_data');
  localStorage.removeItem('pending_email');
  localStorage.removeItem('pending_remember_device');
  localStorage.removeItem('pending_debug_otp');
}

function guardarSesion(data) {
  const token = data.token;

  if (!token) {
    throw new Error('El servidor no devolvió un token de acceso.');
  }

  localStorage.setItem('user_token', token);
  localStorage.setItem('user_data', JSON.stringify(data.user));

  localStorage.removeItem('pending_email');
  localStorage.removeItem('pending_remember_device');
}

async function handleLogin() {
  loading.value = true;
  error.value = '';
  success.value = '';

  limpiarSesionAnterior();

  try {
    const { data } = await authService.login({
      email: form.email,
      password: form.password,
    });

    irAlSegundoFactor(data);
  } catch (err) {
    error.value = obtenerMensajeError(
      err,
      'No se pudo iniciar sesión. Verifica tus credenciales.'
    );
  } finally {
    loading.value = false;
  }
}

// Decide qué sigue después de validar la contraseña: entrar directo (dispositivo de confianza),
// pedir el rostro o pedir el código OTP.
function irAlSegundoFactor(data) {
  if (data.skip_otp) {
    guardarSesion(data);
    router.push({ name: 'home' });
    return;
  }

  if (data.requiere_rostro && data.login_token) {
    loginToken.value = data.login_token;
    success.value = data.message || 'Contraseña correcta. Verifica tu identidad con el rostro.';
    showFaceModal.value = true;
    return;
  }

  const email = data.email || form.email;

  localStorage.setItem('pending_email', email);
  localStorage.setItem('pending_remember_device', remember.value ? '1' : '0');

  if (data.debug_otp) {
    localStorage.setItem('pending_debug_otp', String(data.debug_otp));
  } else {
    localStorage.removeItem('pending_debug_otp');
  }

  success.value = data.message || 'OTP enviado correctamente.';

  router.push({
    name: 'otp',
    query: { email },
  });
}

async function abrirLoginFacial() {
  error.value = '';
  success.value = '';

  // Con un ticket vigente solo se vuelve a escanear el rostro (sin repetir la contraseña).
  if (loginToken.value) {
    showFaceModal.value = true;
    return;
  }

  if (!form.email || !form.password) {
    error.value = 'Ingresa tu correo y tu contraseña: el rostro se pide después de validarlos.';
    return;
  }

  loading.value = true;
  limpiarSesionAnterior();

  try {
    const { data } = await authService.login({
      email: form.email,
      password: form.password,
      metodo: 'rostro',
    });

    irAlSegundoFactor(data);
  } catch (err) {
    error.value = obtenerMensajeError(
      err,
      'No se pudo iniciar sesión. Verifica tus credenciales.'
    );
  } finally {
    loading.value = false;
  }
}

async function onFaceLoginCaptured({ descriptor }) {
  showFaceModal.value = false;
  loading.value = true;
  error.value = '';
  success.value = '';

  limpiarSesionAnterior();

  try {
    const { data } = await authService.verifyFace({
      login_token: loginToken.value,
      descriptor,
    });

    if (!data.match) {
      error.value = data.message || 'Rostro no reconocido.';
      return;
    }

    loginToken.value = '';
    guardarSesion(data);
    router.push({ name: 'home' });
  } catch (err) {
    const respuesta = err?.response;

    // 429 (sin intentos) o 422 (ticket vencido): hay que empezar de nuevo o usar el OTP.
    if (respuesta?.status === 429 || respuesta?.status === 422) {
      loginToken.value = '';
    }

    error.value = obtenerMensajeError(
      err,
      'No se pudo verificar el rostro. Usa el código OTP o ingresa con contraseña.'
    );
  } finally {
    loading.value = false;
  }
}

async function usarOtpEnLugarDelRostro() {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const { data } = await authService.usarOtpTrasRostro({ login_token: loginToken.value });

    loginToken.value = '';
    irAlSegundoFactor(data);
  } catch (err) {
    loginToken.value = '';
    error.value = obtenerMensajeError(
      err,
      'No se pudo enviar el código OTP. Ingresa nuevamente tu correo y contraseña.'
    );
  } finally {
    loading.value = false;
  }
}
</script>