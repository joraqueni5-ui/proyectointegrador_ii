import api from '../api';

const cosechaService = {
  listar(params = {}) {
    return api.get('/cosechas', { params });
  },

  papelera(params = {}) {
    return api.get('/cosechas/papelera', { params });
  },

  obtener(id) {
    return api.get(`/cosechas/${id}`);
  },

  crear(data) {
    return api.post('/cosechas', data);
  },

  actualizar(id, data) {
    return api.put(`/cosechas/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`/cosechas/${id}`);
  },

  restaurar(id) {
    return api.patch(`/cosechas/${id}/restaurar`);
  },
};

export default cosechaService;