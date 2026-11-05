import api from '../api';

const productorService = {
  listar(params = {}) {
    return api.get('/productores', { params });
  },

  obtener(id) {
    return api.get(`/productores/${id}`);
  },

  crear(data) {
    return api.post('/productores', data);
  },

  actualizar(id, data) {
    return api.put(`/productores/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`/productores/${id}`);
  },

  activar(id) {
    return api.patch(`/productores/${id}/activar`);
  },
};

export default productorService;