import api from '../api';

const enfermedadService = {
  listar(params = {}) {
    return api.get('/enfermedades', { params });
  },

  obtener(id) {
    return api.get(`/enfermedades/${id}`);
  },

  crear(data) {
    return api.post('/enfermedades', data);
  },

  actualizar(id, data) {
    return api.put(`/enfermedades/${id}`, data);
  },

  desactivar(id) {
    return api.delete(`/enfermedades/${id}`);
  },

  cambiarEstado(id) {
    return api.patch(`/enfermedades/${id}/estado`);
  },
};

export default enfermedadService;