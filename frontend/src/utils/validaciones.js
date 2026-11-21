// Validaciones de formularios (frontend). Son un espejo de las reglas del backend (FormRequest de
// Laravel): mismos límites y mensajes. El backend siempre decide; esto solo adelanta el aviso.
//
// Cada regla recibe (valor, formulario) y devuelve '' si es válida o el mensaje de error en español.

import { reactive } from 'vue';

const texto = (valor) => String(valor ?? '').trim();

export const requerido = (mensaje = 'Este campo es obligatorio.') => (valor) =>
  (texto(valor) === '' ? mensaje : '');

// Solo se evalúa si hay contenido: combinar con `requerido` si el campo es obligatorio.
export const longitud = (min, max, etiqueta = 'El campo') => (valor) => {
  const t = texto(valor);

  if (t === '') return '';
  if (min && t.length < min) return `${etiqueta} debe tener al menos ${min} caracteres.`;
  if (max && t.length > max) return `${etiqueta} no debe superar los ${max} caracteres.`;

  return '';
};

export const soloLetras = (etiqueta = 'El campo') => (valor) => {
  const t = texto(valor);

  return t !== '' && !/^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s]+$/.test(t)
    ? `${etiqueta} solo debe contener letras y espacios.`
    : '';
};

export const correo = () => (valor) => {
  const t = texto(valor);

  if (t === '') return '';
  if (t.length > 120) return 'El correo no debe superar los 120 caracteres.';

  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t) ? '' : 'Ingresa un correo electrónico válido.';
};

// Celular boliviano: 8 dígitos que empiezan con 6 o 7.
export const celularBoliviano = () => (valor) => {
  const t = texto(valor);

  return t !== '' && !/^[67]\d{7}$/.test(t)
    ? 'El celular debe tener 8 dígitos y empezar con 6 o 7 (por ejemplo 71234567).'
    : '';
};

export const ciDigitos = () => (valor) => {
  const t = texto(valor);

  return t !== '' && !/^\d{5,10}$/.test(t) ? 'El carnet de identidad debe tener entre 5 y 10 dígitos.' : '';
};

// 8 a 72 caracteres con mayúscula, minúscula, número y símbolo.
export const contrasenaSegura = () => (valor) => {
  const t = String(valor ?? '');

  if (t === '') return '';
  if (t.length > 72) return 'La contraseña no puede superar los 72 caracteres.';

  const ok = t.length >= 8 && /\p{Ll}/u.test(t) && /\p{Lu}/u.test(t) && /\d/.test(t) && /[^\p{L}\d\s]/u.test(t);

  return ok
    ? ''
    : 'La contraseña debe tener al menos 8 caracteres e incluir una mayúscula, una minúscula, un número y un símbolo (por ejemplo ! # $ %).';
};

export const unoDe = (opciones, mensaje) => (valor) =>
  (texto(valor) !== '' && !opciones.includes(texto(valor)) ? mensaje : '');

// Aplica solo si la condición del formulario se cumple (por ejemplo, contraseña obligatoria al crear).
export const cuando = (condicion, regla) => (valor, formulario) =>
  (condicion(formulario) ? regla(valor, formulario) : '');

export function numeroEntre(min, max, etiqueta = 'El valor') {
  return (valor) => {
    const t = texto(valor);

    if (t === '') return '';

    const n = Number(t.replace(',', '.'));

    if (!Number.isFinite(n)) return `${etiqueta} debe ser un número.`;
    if (n < min || n > max) return `${etiqueta} debe estar entre ${String(min).replace('.', ',')} y ${String(max).replace('.', ',')}.`;

    return '';
  };
}

/**
 * Validación por campo para formularios.
 *
 *   const { errores, validarCampo, validarTodo, limpiar } = useValidacion(form, {
 *     email: [requerido('El correo es obligatorio.'), correo()],
 *   });
 *
 * - validarCampo(nombre): valida un campo (usar en @blur y @input después del primer intento).
 * - validarTodo(): valida todo, deja los mensajes en `errores` y devuelve true si no hay errores.
 */
export function useValidacion(formulario, esquema) {
  const errores = reactive(Object.fromEntries(Object.keys(esquema).map((campo) => [campo, ''])));

  function validarCampo(campo) {
    const reglas = esquema[campo] || [];

    errores[campo] = '';

    for (const regla of reglas) {
      const mensaje = regla(formulario[campo], formulario);

      if (mensaje) {
        errores[campo] = mensaje;
        break;
      }
    }

    return errores[campo] === '';
  }

  function validarTodo() {
    let correcto = true;

    for (const campo of Object.keys(esquema)) {
      correcto = validarCampo(campo) && correcto;
    }

    return correcto;
  }

  function limpiar() {
    for (const campo of Object.keys(errores)) {
      errores[campo] = '';
    }
  }

  function primerCampoConError() {
    return Object.keys(errores).find((campo) => errores[campo]) || null;
  }

  return { errores, validarCampo, validarTodo, limpiar, primerCampoConError };
}
