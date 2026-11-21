import api from '../api';

const buscarService = {
  // GET /api/buscar?q=texto  (mínimo 2 caracteres; hasta 5 resultados por tipo)
  buscar(q, signal) {
    return api.get('/buscar', { params: { q }, signal });
  },
};

export default buscarService;
