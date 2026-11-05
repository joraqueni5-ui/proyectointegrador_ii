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
          <linearGradient id="otp-m1" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#1a5c35" stop-opacity="0.55" />
            <stop offset="100%" stop-color="#0a2015" stop-opacity="0.9" />
          </linearGradient>

          <linearGradient id="otp-m2" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#0e3a20" stop-opacity="0.7" />
            <stop offset="100%" stop-color="#061510" stop-opacity="1" />
          </linearGradient>

          <linearGradient id="otp-m3" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#082018" stop-opacity="0.85" />
            <stop offset="100%" stop-color="#040e09" stop-opacity="1" />
          </linearGradient>
        </defs>

        <path
          d="M0,280 L80,160 L160,220 L260,80 L360,200 L460,120 L560,180 L660,60 L760,200 L860,100 L960,220 L1060,140 L1160,200 L1280,90 L1380,180 L1440,140 L1440,500 L0,500 Z"
          fill="url(#otp-m1)"
        />

        <path
          d="M0,320 L120,200 L200,270 L320,130 L400,250 L520,160 L600,280 L720,150 L820,260 L940,170 L1040,280 L1160,190 L1260,260 L1380,210 L1440,260 L1440,500 L0,500 Z"
          fill="url(#otp-m2)"
        />

        <path
          d="M0,400 L180,290 L300,370 L440,280 L580,360 L700,300 L860,390 L980,320 L1120,400 L1260,340 L1440,390 L1440,500 L0,500 Z"
          fill="url(#otp-m3)"
        />
      </svg>

      <span
        v-for="star in stars"
        :key="star.id"
        class="star"
        :style="star.style"
      ></span>
    </div>

    <section class="proinpa-page-content otp-page-content">
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
          "Acceso seguro para personal autorizado de la institución"
        </p>

        <div class="badges">
          <div class="badge">
            <div class="badge-dot green"></div>
            Verificación en dos pasos
          </div>

          <div class="badge">
            <div class="badge-dot gold"></div>
            Protección de credenciales
          </div>

          <div class="badge">
            <div class="badge-dot pulse"></div>
            Código temporal activo
          </div>
        </div>
      </aside>

      <!-- TARJETA OTP -->
      <section class="glass-card otp-card">
        <div class="card-shine"></div>

        <div class="card-head">
          <div class="card-head-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path
                d="M9 12l2 2 4-4m5-6.5V10c0 5.25-3.75 10-8 11-4.25-1-8-5.75-8-11V3.5L12 1l8 2.5z"
              />
            </svg>
          </div>

          <h1 class="card-title">Verificación OTP</h1>

          <p class="card-subtitle">
            Ingresa el código enviado a tu correo institucional.
          </p>
        </div>

        <div class="otp-email-box">
          <span>Correo verificado</span>
          <strong>{{ email }}</strong>
        </div>

        <div v-if="debugOtp" class="auth-message success otp-debug-message">
          <strong>Modo local:</strong> código OTP {{ debugOtp }}
        </div>

        <form class="login-form" @submit.prevent="handleVerifyOtp">
          <div class="field">
            <label class="field-label">Código de seguridad</label>

            <div class="otp-code-group">
              <input
                v-for="(_, index) in otpDigits"
                :key="index"
                ref="otpInputs"
                v-model="otpDigits[index]"
                type="text"
                inputmode="numeric"
                maxlength="1"
                class="otp-code-box"
                autocomplete="one-time-code"
                @input="handleOtpInput(index)"
                @keydown="handleOtpKeydown($event, index)"
                @paste="handleOtpPaste"
              />
            </div>
          </div>

          <div v-if="error" class="auth-message error">
            {{ error }}
          </div>

          <div v-if="success" class="auth-message success">
            {{ success }}
          </div>

          <button type="submit" class="btn-cta" :disabled="loading || otpCode.length < 6">
            {{ loading ? 'Verificando...' : 'Verificar código' }}
          </button>

          <div class="divider">acciones</div>

          <button
            type="button"
            class="btn-ghost"
            :disabled="resending"
            @click="reenviarOtp"
          >
            {{ resending ? 'Reenviando...' : 'Reenviar código OTP' }}
          </button>

          <button
            type="button"
            class="btn-ghost"
            @click="volverLogin"
          >
            Volver al inicio de sesión
          </button>
        </form>

        <div class="card-footer">
          <span>El código OTP es temporal y solo debe ser usado por el usuario autorizado.</span>
        </div>
      </section>
    </section>
  </main>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import authService from '@/services/modules/authService';
import { obtenerMensajeError } from '@/services/errorService';

import '@/styles/auth.css';

const router = useRouter();
const route = useRoute();

const loading = ref(false);
const resending = ref(false);
const error = ref('');
const success = ref('');
const debugOtp = ref(localStorage.getItem('pending_debug_otp') || '');

const otpInputs = ref([]);
const otpDigits = ref(['', '', '', '', '', '']);

const email = computed(() => {
  return (
    route.query.email ||
    localStorage.getItem('pending_email') ||
    ''
  );
});

const otpCode = computed(() => otpDigits.value.join(''));

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

onMounted(() => {
  if (!email.value) {
    volverLogin();
    return;
  }

  nextTick(() => {
    otpInputs.value[0]?.focus();
  });
});

function limpiarSesionAnterior() {
  localStorage.removeItem('user_token');
  localStorage.removeItem('user_data');
}

function limpiarPendientes() {
  localStorage.removeItem('pending_email');
  localStorage.removeItem('pending_remember_device');
  localStorage.removeItem('pending_debug_otp');
  debugOtp.value = '';
}

function guardarSesion(data) {
  const token = data.token;

  if (!token) {
    throw new Error('El servidor no devolvió token de acceso.');
  }

  localStorage.setItem('user_token', token);
  localStorage.setItem('user_data', JSON.stringify(data.user));

  limpiarPendientes();
}

function handleOtpInput(index) {
  otpDigits.value[index] = String(otpDigits.value[index] || '')
    .replace(/\D/g, '')
    .slice(0, 1);

  if (otpDigits.value[index] && index < otpDigits.value.length - 1) {
    nextTick(() => {
      otpInputs.value[index + 1]?.focus();
    });
  }
}

function handleOtpKeydown(event, index) {
  if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
    nextTick(() => {
      otpInputs.value[index - 1]?.focus();
    });
  }

  if (event.key === 'ArrowLeft' && index > 0) {
    event.preventDefault();
    otpInputs.value[index - 1]?.focus();
  }

  if (event.key === 'ArrowRight' && index < otpDigits.value.length - 1) {
    event.preventDefault();
    otpInputs.value[index + 1]?.focus();
  }
}

function handleOtpPaste(event) {
  event.preventDefault();

  const pasted = event.clipboardData
    .getData('text')
    .replace(/\D/g, '')
    .slice(0, 6);

  otpDigits.value = Array.from({ length: 6 }, (_, index) => pasted[index] || '');

  nextTick(() => {
    const nextIndex = Math.min(pasted.length, 5);
    otpInputs.value[nextIndex]?.focus();
  });
}

async function handleVerifyOtp() {
  error.value = '';
  success.value = '';
  limpiarSesionAnterior();

  if (otpCode.value.length !== 6) {
    error.value = 'Debes ingresar el código OTP de 6 dígitos.';
    return;
  }

  loading.value = true;

  try {
    const rememberDevice = localStorage.getItem('pending_remember_device') === '1';

    const { data } = await authService.verifyOtp({
      email: email.value,
      otp_code: String(otpCode.value).trim(),
      remember_device: rememberDevice,
    });

    guardarSesion(data);

    success.value = data.message || 'Código verificado correctamente.';

    router.push({ name: 'home' });
  } catch (err) {
    limpiarSesionAnterior();

    error.value = obtenerMensajeError(
      err,
      'Código OTP incorrecto o expirado.'
    );
  } finally {
    loading.value = false;
  }
}

async function reenviarOtp() {
  error.value = '';
  success.value = '';

  if (!email.value) {
    error.value = 'No se encontró el correo para reenviar el código.';
    return;
  }

  resending.value = true;

  try {
    const { data } = await authService.resendOtp({
      email: email.value,
    });

    debugOtp.value = data.debug_otp ? String(data.debug_otp) : '';

    if (debugOtp.value) {
      localStorage.setItem('pending_debug_otp', debugOtp.value);
    } else {
      localStorage.removeItem('pending_debug_otp');
    }

    otpDigits.value = ['', '', '', '', '', ''];
    success.value = data.message || 'Se envió un nuevo código OTP.';

    nextTick(() => {
      otpInputs.value[0]?.focus();
    });
  } catch (err) {
    error.value = obtenerMensajeError(
      err,
      'No se pudo reenviar el código OTP.'
    );
  } finally {
    resending.value = false;
  }
}

function volverLogin() {
  limpiarSesionAnterior();
  limpiarPendientes();

  router.push({ name: 'login' });
}
</script>