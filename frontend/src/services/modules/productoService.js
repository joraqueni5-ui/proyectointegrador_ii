import api from '../api';

const BASE_URL = '/inventario/productos';

const productoService = {
  listar(params = {}) {
    return api.get(BASE_URL, { params });
  },

  obtener(id) {
    return api.get(`${BASE_URL}/${id}`);
  },

  crear(data) {
    return api.post(BASE_URL, data);
  },

  actualizar(id, data) {
    return api.put(`${BASE_URL}/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`${BASE_URL}/${id}`);
  },

  cambiarEstado(id) {
    return api.patch(`${BASE_URL}/${id}/estado`);
  },
};

export default productoService;