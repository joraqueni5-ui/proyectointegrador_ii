<template>
  <div class="otp-input-group">
    <input
      v-for="(_, index) in digits"
      :key="index"
      ref="inputs"
      v-model="digits[index]"
      class="otp-box"
      type="text"
      inputmode="numeric"
      maxlength="1"
      autocomplete="one-time-code"
      @input="handleInput(index)"
      @keydown="handleKeydown($event, index)"
      @paste="handlePaste"
    />
  </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  length: {
    type: Number,
    default: 6,
  },
});

const emit = defineEmits(['update:modelValue', 'complete']);

const inputs = ref([]);
const digits = ref(Array.from({ length: props.length }, () => ''));

watch(
  () => props.modelValue,
  (value) => {
    const cleanValue = String(value || '').replace(/\D/g, '').slice(0, props.length);

    digits.value = Array.from({ length: props.length }, (_, index) => cleanValue[index] || '');
  }
);

function emitValue() {
  const value = digits.value.join('');

  emit('update:modelValue', value);

  if (value.length === props.length) {
    emit('complete', value);
  }
}

function handleInput(index) {
  digits.value[index] = String(digits.value[index] || '').replace(/\D/g, '').slice(0, 1);

  emitValue();

  if (digits.value[index] && index < props.length - 1) {
    nextTick(() => {
      inputs.value[index + 1]?.focus();
    });
  }
}

function handleKeydown(event, index) {
  if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
    nextTick(() => {
      inputs.value[index - 1]?.focus();
    });
  }

  if (event.key === 'ArrowLeft' && index > 0) {
    event.preventDefault();
    inputs.value[index - 1]?.focus();
  }

  if (event.key === 'ArrowRight' && index < props.length - 1) {
    event.preventDefault();
    inputs.value[index + 1]?.focus();
  }
}

function handlePaste(event) {
  event.preventDefault();

  const pasted = event.clipboardData
    .getData('text')
    .replace(/\D/g, '')
    .slice(0, props.length);

  digits.value = Array.from({ length: props.length }, (_, index) => pasted[index] || '');

  emitValue();

  nextTick(() => {
    const nextIndex = Math.min(pasted.length, props.length - 1);
    inputs.value[nextIndex]?.focus();
  });
}
</script>