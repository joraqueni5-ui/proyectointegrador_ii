import api from '../api';

const cultivoVariedadService = {
  listar(params = {}) {
    return api.get('/cultivo-variedades', { params });
  },

  obtener(id) {
    return api.get(`/cultivo-variedades/${id}`);
  },

  crear(data) {
    return api.post('/cultivo-variedades', data);
  },

  actualizar(id, data) {
    return api.put(`/cultivo-variedades/${id}`, data);
  },

  cambiarEstado(id, estado) {
    return api.patch(`/cultivo-variedades/${id}/estado`, { estado });
  },

  eliminar(id) {
    return api.delete(`/cultivo-variedades/${id}`);
  },
};

export default cultivoVariedadService;