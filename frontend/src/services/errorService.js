export function obtenerMensajeError(error, mensajeDefault = 'Ocurrió un error inesperado.') {
  const data = error.response?.data;

  if (data?.errors) {
    const primerCampo = Object.keys(data.errors)[0];
    return data.errors[primerCampo][0];
  }

  if (data?.message) {
    return data.message;
  }

  return mensajeDefault;
}