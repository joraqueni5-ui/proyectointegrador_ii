import api from '../api';

const comunidadService = {
  listar(params = {}) {
    return api.get('/comunidades', { params });
  },

  obtener(id) {
    return api.get(`/comunidades/${id}`);
  },

  crear(data) {
    return api.post('/comunidades', data);
  },

  actualizar(id, data) {
    return api.put(`/comunidades/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`/comunidades/${id}`);
  },
  activar(id) {
    return api.patch(`/comunidades/${id}/activar`);
  },
};

export default comunidadService;