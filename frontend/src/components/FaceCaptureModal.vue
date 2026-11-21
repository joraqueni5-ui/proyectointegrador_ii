<template>
  <div class="modal-overlay" @click.self="cerrar">
    <div class="modal-card">

      <div class="modal-header">
        <h2>Captura facial</h2>
        <span class="status-badge" :class="statusClass">{{ statusText }}</span>
      </div>

      <div class="camera-container">
        <video ref="videoRef" autoplay muted playsinline class="camera-feed" />
        <canvas ref="canvasRef" class="camera-canvas" />

        <!-- Marco con esquinas -->
        <div class="face-frame" :class="{ detected: faceDetected, capturing: isCapturing }">
          <div class="corner tl"></div>
          <div class="corner tr"></div>
          <div class="corner bl"></div>
          <div class="corner br"></div>
        </div>

        <!-- Cargando modelos -->
        <div v-if="loadingModels" class="loading-overlay">
          <div class="spinner"></div>
          <p>Cargando modelos de IA...</p>
        </div>

        <div v-if="cameraError" class="loading-overlay error-overlay">
          <p>{{ cameraError }}</p>
        </div>

        <!-- Cuenta regresiva -->
        <div v-if="countdown > 0" class="countdown-overlay">
          <span class="countdown-number">{{ countdown }}</span>
        </div>
      </div>

      <p class="instruction">{{ instruction }}</p>

      <div class="modal-actions">
        <button class="btn-cancel" @click="cerrar">Cancelar</button>
        <button
          class="btn-capture"
          :disabled="!faceDetected || isCapturing || loadingModels || !!cameraError"
          @click="captureManual"
        >
          {{ isCapturing ? 'Capturando...' : 'Capturar ahora' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import * as faceapi from '@vladmandic/face-api';
import { FACE_MODELS_URL } from '@/services/api';

const emit = defineEmits(['captured', 'close']);

const videoRef = ref(null);
const canvasRef = ref(null);

const loadingModels = ref(true);
const cameraError = ref('');
const faceDetected = ref(false);
const isCapturing = ref(false);
const countdown = ref(0);

let stream = null;
let detectionTimer = null;
let countdownTimer = null;
let stableStart = null;
let detecting = false;
let modalActivo = true;

const STABLE_TIME = 2000;

const statusText = computed(() => {
  if (cameraError.value) return 'Error de cámara';
  if (loadingModels.value) return 'Cargando IA...';
  if (isCapturing.value) return 'Capturando';
  if (faceDetected.value) return 'Rostro detectado ✓';
  return 'Buscando rostro...';
});

const statusClass = computed(() => ({
  'status-loading': loadingModels.value,
  'status-detected': faceDetected.value && !isCapturing.value,
  'status-capturing': isCapturing.value,
  'status-searching': !faceDetected.value && !loadingModels.value && !cameraError.value,
}));

const instruction = computed(() => {
  if (cameraError.value) return cameraError.value;
  if (loadingModels.value) return 'Cargando modelos de reconocimiento facial...';
  if (!faceDetected.value) return 'Centra tu rostro en el marco.';
  if (countdown.value > 0) return `Capturando en ${countdown.value}...`;
  return 'Rostro encuadrado. Puedes capturar ahora.';
});

onMounted(async () => {
  modalActivo = true;

  try {
    await nextTick();
    await loadModels();
    await startCamera();
    startDetectionLoop();
  } catch (error) {
    console.error('Error en reconocimiento facial:', error);

    cameraError.value = traducirErrorCamara(error);
    loadingModels.value = false;

    stopAll();
  }
});

onBeforeUnmount(() => {
  modalActivo = false;
  stopAll();
});

async function loadModels() {
  loadingModels.value = true;

  await Promise.all([
    faceapi.nets.tinyFaceDetector.loadFromUri(FACE_MODELS_URL),
    faceapi.nets.faceLandmark68Net.loadFromUri(FACE_MODELS_URL),
    faceapi.nets.faceRecognitionNet.loadFromUri(FACE_MODELS_URL),
  ]);

  loadingModels.value = false;
}

async function startCamera() {
  stopAll();

  if (!navigator.mediaDevices?.getUserMedia) {
    throw new Error('MEDIA_DEVICES_NOT_SUPPORTED');
  }

  stream = await navigator.mediaDevices.getUserMedia({
    video: {
      facingMode: 'user',
      width: { ideal: 640 },
      height: { ideal: 480 },
    },
    audio: false,
  });

  if (!videoRef.value) {
    stopAll();
    throw new Error('VIDEO_NOT_FOUND');
  }

  videoRef.value.srcObject = stream;

  await new Promise((resolve, reject) => {
    const video = videoRef.value;

    if (!video) {
      reject(new Error('VIDEO_NOT_FOUND'));
      return;
    }

    video.onloadedmetadata = () => resolve();

    if (video.readyState >= 2) {
      resolve();
    }
  });

  await videoRef.value.play();
}

function startDetectionLoop() {
  stopDetectionLoop();

  if (!modalActivo || !stream || cameraError.value || isCapturing.value) {
    return;
  }

  detectionTimer = window.setTimeout(detectFace, 250);
}

async function detectFace() {
  if (!modalActivo || !stream || cameraError.value) {
    stopDetectionLoop();
    return;
  }

  if (
    detecting ||
    loadingModels.value ||
    isCapturing.value ||
    !videoRef.value ||
    videoRef.value.readyState < 2
  ) {
    if (modalActivo && stream && !cameraError.value && !isCapturing.value) {
      detectionTimer = window.setTimeout(detectFace, 300);
    }

    return;
  }

  detecting = true;

  try {
    const detection = await faceapi
      .detectSingleFace(
        videoRef.value,
        new faceapi.TinyFaceDetectorOptions({
          inputSize: 224,
          scoreThreshold: 0.5,
        })
      )
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!modalActivo || !stream) {
      return;
    }

    if (detection) {
      faceDetected.value = true;
      drawMesh(detection);

      if (!stableStart) {
        stableStart = Date.now();
      }

      const elapsed = Date.now() - stableStart;

      if (elapsed >= STABLE_TIME && countdown.value === 0 && !isCapturing.value) {
        startCountdown();
      }
    } else {
      faceDetected.value = false;
      stableStart = null;
      clearCanvas();

      if (!isCapturing.value) {
        stopCountdown();
      }
    }
  } catch (error) {
    console.error('Error detectando rostro:', error);
  } finally {
    detecting = false;

    if (modalActivo && stream && !cameraError.value && !isCapturing.value) {
      detectionTimer = window.setTimeout(detectFace, 300);
    }
  }
}

function drawMesh(detection) {
  const video = videoRef.value;
  const canvas = canvasRef.value;

  if (!video || !canvas || !video.videoWidth || !video.videoHeight) {
    return;
  }

  faceapi.matchDimensions(canvas, {
    width: video.videoWidth,
    height: video.videoHeight,
  });

  const resized = faceapi.resizeResults(detection, {
    width: video.videoWidth,
    height: video.videoHeight,
  });

  const context = canvas.getContext('2d');

  if (!context) {
    return;
  }

  context.clearRect(0, 0, canvas.width, canvas.height);

  faceapi.draw.drawFaceLandmarks(canvas, resized);
}

function clearCanvas() {
  const canvas = canvasRef.value;

  if (!canvas) {
    return;
  }

  const context = canvas.getContext('2d');

  if (context) {
    context.clearRect(0, 0, canvas.width, canvas.height);
  }
}

function startCountdown() {
  if (countdownTimer || isCapturing.value || !modalActivo) {
    return;
  }

  countdown.value = 3;

  countdownTimer = window.setInterval(() => {
    if (!modalActivo || !stream) {
      stopCountdown();
      return;
    }

    countdown.value -= 1;

    if (countdown.value <= 0) {
      stopCountdown();
      capture();
    }
  }, 1000);
}

function captureManual() {
  if (!faceDetected.value || isCapturing.value || cameraError.value || !modalActivo) {
    return;
  }

  capture();
}

async function capture() {
  if (!videoRef.value || isCapturing.value || !stream || !modalActivo) {
    return;
  }

  isCapturing.value = true;
  stopDetectionLoop();
  stopCountdown();

  try {
    const video = videoRef.value;

    if (!video.videoWidth || !video.videoHeight) {
      throw new Error('VIDEO_NOT_READY');
    }

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = video.videoWidth;
    tempCanvas.height = video.videoHeight;

    const context = tempCanvas.getContext('2d');

    if (!context) {
      throw new Error('CANVAS_CONTEXT_ERROR');
    }

    context.drawImage(video, 0, 0);

    const photoBase64 = tempCanvas.toDataURL('image/jpeg', 0.85);

    const detection = await faceapi
      .detectSingleFace(
        video,
        new faceapi.TinyFaceDetectorOptions({
          inputSize: 224,
          scoreThreshold: 0.5,
        })
      )
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (!detection) {
      isCapturing.value = false;
      faceDetected.value = false;
      stableStart = null;
      clearCanvas();
      startDetectionLoop();
      return;
    }

    const descriptor = Array.from(detection.descriptor);

    stopAll();

    emit('captured', {
      photoBase64,
      descriptor,
    });
  } catch (error) {
    console.error('Error capturando rostro:', error);

    cameraError.value = 'No se pudo capturar el rostro. Intenta nuevamente.';
    isCapturing.value = false;

    startDetectionLoop();
  }
}

function stopDetectionLoop() {
  if (detectionTimer) {
    clearTimeout(detectionTimer);
    detectionTimer = null;
  }
}

function stopCountdown() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }

  countdown.value = 0;
}

function stopAll() {
  stopDetectionLoop();
  stopCountdown();
  clearCanvas();

  detecting = false;
  faceDetected.value = false;
  stableStart = null;

  if (videoRef.value) {
    try {
      videoRef.value.pause();
      videoRef.value.srcObject = null;
      videoRef.value.removeAttribute('src');

      if (typeof videoRef.value.load === 'function') {
        videoRef.value.load();
      }
    } catch (error) {
      console.warn('No se pudo limpiar el video:', error);
    }
  }

  if (stream) {
    stream.getTracks().forEach((track) => {
      track.stop();
    });

    stream = null;
  }
}

function cerrar() {
  modalActivo = false;
  stopAll();
  emit('close');
}

function traducirErrorCamara(error) {
  if (error?.name === 'NotAllowedError') {
    return 'Permiso de cámara denegado. Habilita la cámara en el navegador.';
  }

  if (error?.name === 'NotFoundError') {
    return 'No se encontró una cámara disponible.';
  }

  if (error?.name === 'NotReadableError') {
    return 'La cámara está siendo usada por otra aplicación. Cierra Zoom, Meet, Teams u otra pestaña que use cámara.';
  }

  if (error?.name === 'OverconstrainedError') {
    return 'La cámara no cumple con la configuración solicitada.';
  }

  if (error?.message === 'MEDIA_DEVICES_NOT_SUPPORTED') {
    return 'El navegador no permite acceso a cámara en este entorno.';
  }

  if (error?.message === 'VIDEO_NOT_FOUND') {
    return 'No se encontró el componente de video para mostrar la cámara.';
  }

  if (error?.message === 'VIDEO_NOT_READY') {
    return 'La cámara todavía no está lista. Intenta nuevamente.';
  }

  if (
    location.protocol !== 'https:' &&
    location.hostname !== 'localhost' &&
    location.hostname !== '127.0.0.1'
  ) {
    return 'La cámara solo funciona en HTTPS o localhost.';
  }

  return 'No se pudo iniciar la cámara. Revisa permisos o intenta cerrar otras aplicaciones.';
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-card {
  background: #111411;
  border: 1px solid rgba(76, 175, 80, 0.25);
  border-radius: 8px;
  padding: 32px;
  width: 520px;
  box-shadow: 0 40px 80px rgba(0,0,0,0.8);
  font-family: 'JetBrains Mono', monospace;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.modal-header h2 {
  color: #f0f5f0;
  font-size: 18px;
  font-weight: 400;
}

.status-badge {
  font-size: 10px;
  padding: 4px 10px;
  border-radius: 20px;
  letter-spacing: 0.05em;
}

.status-loading {
  background: rgba(255,193,7,0.15);
  color: #FFC107;
  border: 1px solid rgba(255,193,7,0.3);
}

.status-detected {
  background: rgba(76,175,80,0.15);
  color: #4CAF50;
  border: 1px solid rgba(76,175,80,0.3);
}

.status-capturing {
  background: rgba(33,150,243,0.15);
  color: #2196F3;
  border: 1px solid rgba(33,150,243,0.3);
}

.status-searching {
  background: rgba(158,158,158,0.1);
  color: #9E9E9E;
  border: 1px solid rgba(158,158,158,0.2);
}

.camera-container {
  position: relative;
  width: 100%;
  aspect-ratio: 4/3;
  background: #000;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 16px;
}

.camera-feed {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);
}

.camera-canvas {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: scaleX(-1);
}

.face-frame {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -55%);
  width: 200px;
  height: 240px;
}

.corner {
  position: absolute;
  width: 20px;
  height: 20px;
  border-style: solid;
  border-color: rgba(255,255,255,0.4);
  transition: border-color 0.3s;
}

.face-frame.detected .corner {
  border-color: #4CAF50;
}

.face-frame.capturing .corner {
  border-color: #2196F3;
}

.tl {
  top: 0;
  left: 0;
  border-width: 2px 0 0 2px;
}

.tr {
  top: 0;
  right: 0;
  border-width: 2px 2px 0 0;
}

.bl {
  bottom: 0;
  left: 0;
  border-width: 0 0 2px 2px;
}

.br {
  bottom: 0;
  right: 0;
  border-width: 0 2px 2px 0;
}

.loading-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.85);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #4CAF50;
  gap: 12px;
  font-size: 12px;
}

.spinner {
  width: 36px;
  height: 36px;
  border: 2px solid rgba(76,175,80,0.2);
  border-top-color: #4CAF50;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.countdown-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
}

.countdown-number {
  font-size: 80px;
  color: white;
  animation: pulse 1s ease-in-out;
}

.instruction {
  text-align: center;
  font-size: 11px;
  color: #5A6E5A;
  margin-bottom: 20px;
  min-height: 16px;
}

.modal-actions {
  display: flex;
  gap: 12px;
}

.btn-cancel {
  flex: 1;
  padding: 12px;
  background: transparent;
  border: 1px solid rgba(76,175,80,0.2);
  color: #5A6E5A;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.1em;
  cursor: pointer;
  border-radius: 2px;
}

.btn-capture {
  flex: 2;
  padding: 12px;
  background: #4CAF50;
  border: none;
  color: white;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.1em;
  cursor: pointer;
  border-radius: 2px;
}

.btn-capture:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.error-overlay {
  background: rgba(127, 29, 29, 0.88);
  color: #fecaca;
  text-align: center;
  padding: 20px;
}

.error-overlay p {
  max-width: 360px;
  line-height: 1.5;
  margin: 0 auto;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }

  50% {
    transform: scale(1.1);
  }
}
</style>