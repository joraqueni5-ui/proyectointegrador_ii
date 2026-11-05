import api from '../api';

const almacenService = {
  listar(params = {}) {
    return api.get('/inventario/almacenes', { params });
  },

  obtener(id) {
    return api.get(`/inventario/almacenes/${id}`);
  },

  crear(data) {
    return api.post('/inventario/almacenes', data);
  },

  actualizar(id, data) {
    return api.put(`/inventario/almacenes/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`/inventario/almacenes/${id}`);
  },

  cambiarEstado(id) {
    return api.patch(`/inventario/almacenes/${id}/estado`);
  },
};

export default almacenService;