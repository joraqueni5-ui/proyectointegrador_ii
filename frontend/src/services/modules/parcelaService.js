import api from '../api';

const parcelaService = {
  listar(params = {}) {
    return api.get('/parcelas', { params });
  },

  obtener(id) {
    return api.get(`/parcelas/${id}`);
  },

  crear(data) {
    return api.post('/parcelas', data);
  },

  actualizar(id, data) {
    return api.put(`/parcelas/${id}`, data);
  },

  eliminar(id) {
    return api.delete(`/parcelas/${id}`);
  },

  cambiarEstado(id, estado) {
    return api.patch(`/parcelas/${id}/estado`, { estado });
  },

  historialFitosanitario(id, params = {}) {
    return api.get(`/parcelas/${id}/historial-fitosanitario`, { params });
  },
};

export default parcelaService;