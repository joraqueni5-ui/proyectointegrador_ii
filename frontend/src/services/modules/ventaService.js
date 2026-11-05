import api from '../api';

const ventaService = {
  dashboard() {
    return api.get('/ventas/dashboard');
  },

  tendencia(params = {}) {
    return api.get('/ventas/tendencia', { params });
  },

  listar(params = {}) {
    return api.get('/ventas', { params });
  },

  obtener(id) {
    return api.get(`/ventas/${id}`);
  },

  crear(data) {
    return api.post('/ventas', data);
  },

  cambiarEstado(id, estado) {
    return api.patch(`/ventas/${id}/estado`, { estado });
  },
};

export default ventaService;