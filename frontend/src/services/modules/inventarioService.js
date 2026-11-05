import api from '../api';

const inventarioService = {
  dashboard() {
    return api.get('/inventario/dashboard');
  },

  listar(params = {}) {
    return api.get('/inventario', { params });
  },

  alertas() {
    return api.get('/inventario/alertas');
  },
};

export default inventarioService;