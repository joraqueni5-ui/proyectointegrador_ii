import api from '../api';

const inspeccionService = {
  listar(params = {}) {
    return api.get('/inspecciones', { params });
  },

  resumen() {
    return api.get('/inspecciones/resumen');
  },

  obtener(id) {
    return api.get(`/inspecciones/${id}`);
  },

  crear(data) {
    return api.post('/inspecciones', data);
  },

  actualizar(id, data) {
    return api.put(`/inspecciones/${id}`, data);
  },

  cambiarEstado(id, payload) {
  const body = typeof payload === 'string'
    ? { estado: payload }
    : payload;

  return api.patch(`/inspecciones/${id}/estado`, body);
}
};

export default inspeccionService;