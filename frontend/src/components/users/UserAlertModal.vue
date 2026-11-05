<template>
  <div class="user-alert-overlay">
    <div class="user-alert-card">
      <div class="user-alert-icon">
        <i class="ti ti-x"></i>
      </div>

      <h3>{{ title }}</h3>

      <p>{{ message }}</p>

      <div class="user-alert-footer">
        <button type="button" @click="$emit('close')">
          <i class="ti ti-x"></i>
          Aceptar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'No permitido',
  },
  message: {
    type: String,
    required: true,
  },
});

defineEmits(['close']);
</script>

<style scoped>
.user-alert-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
}

.user-alert-card {
  width: min(520px, 92vw);
  overflow: hidden;
  border-radius: 22px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
  text-align: center;
  animation: alert-pop 0.22s ease-out;
}

.user-alert-icon {
  width: 76px;
  height: 76px;
  margin: 34px auto 16px;
  border-radius: 999px;
  display: grid;
  place-items: center;
  background: #fee2e2;
  color: #dc2626;
  font-size: 38px;
  border: 4px solid #fecaca;
}

.user-alert-card h3 {
  margin: 0;
  color: #b91c1c;
  font-size: 30px;
  font-weight: 900;
}

.user-alert-card p {
  margin: 12px auto 32px;
  max-width: 420px;
  color: #475569;
  font-size: 17px;
  font-weight: 600;
  line-height: 1.45;
}

.user-alert-footer {
  padding: 24px;
  background: linear-gradient(180deg, #fff1f2 0%, #fff7f7 100%);
}

.user-alert-footer button {
  border: 0;
  border-radius: 12px;
  padding: 13px 28px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  font-size: 16px;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 12px 24px rgba(220, 38, 38, 0.28);
}

@keyframes alert-pop {
  from {
    opacity: 0;
    transform: scale(0.94) translateY(8px);
  }

  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>