import api from '../api';

const cotizacionService = {
  listar(params = {}) {
    return api.get('/cotizaciones', { params });
  },

  obtener(id) {
    return api.get(`/cotizaciones/${id}`);
  },

  crear(data) {
    return api.post('/cotizaciones', data);
  },

  aprobar(id) {
    return api.patch(`/cotizaciones/${id}/aprobar`);
  },

  rechazar(id, motivo_rechazo) {
    return api.patch(`/cotizaciones/${id}/rechazar`, { motivo_rechazo });
  },

  anular(id) {
    return api.patch(`/cotizaciones/${id}/anular`);
  },

  convertirVenta(id) {
    return api.post(`/cotizaciones/${id}/convertir-venta`);
  },
};

export default cotizacionService;