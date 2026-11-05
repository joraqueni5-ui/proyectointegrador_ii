import api from '../api';

const movimientoService = {
  listar(params = {}) {
    return api.get('/inventario/movimientos', { params });
  },

  registrar(data) {
    return api.post('/inventario/movimientos', data);
  },
};

export default movimientoService;