import api from '../api';

const alertaService = {
  listar(params = {}) {
    return api.get('/alertas', { params });
  },

  obtener(id) {
    return api.get(`/alertas/${id}`);
  },

  estadisticas() {
    return api.get('/alertas/estadisticas');
  },

  noLeidas() {
    return api.get('/alertas/no-leidas');
  },

  marcarLeida(id) {
    return api.patch(`/alertas/${id}/leer`);
  },

  // Seguimiento fitosanitario
  iniciarRevision(id, data = {}) {
    return api.patch(`/alertas/${id}/atender`, data);
  },

  planificar(id, data) {
    return api.patch(`/alertas/${id}/planificar`, data);
  },

  iniciarTratamiento(id, data = {}) {
    return api.patch(`/alertas/${id}/iniciar-tratamiento`, data);
  },

  registrarSeguimiento(id, data) {
    return api.patch(`/alertas/${id}/seguimiento`, data);
  },

  resolver(id, data) {
    return api.patch(`/alertas/${id}/resolver`, data);
  },
};

export default alertaService;
