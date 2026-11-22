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

  siguienteCodigo(idProductor) {
    return api.get('/parcelas/siguiente-codigo', { params: { id_productor: idProductor } });
  },

  lineaTiempo(id, params = {}) {
    return api.get(`/parcelas/${id}/linea-tiempo`, { params });
  },

  historialFitosanitario(id, params = {}) {
    return api.get(`/parcelas/${id}/historial-fitosanitario`, { params });
  },
};

export default parcelaService;