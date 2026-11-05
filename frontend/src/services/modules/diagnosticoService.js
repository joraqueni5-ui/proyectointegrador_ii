import api from '../api';

const diagnosticoService = {
  listar(params = {}) {
    return api.get('/diagnosticos', { params });
  },

  obtener(id) {
    return api.get(`/diagnosticos/${id}`);
  },

  estadoCnn() {
    return api.get('/diagnosticos/cnn/estado');
  },

  crear(formData) {
    return api.post('/diagnosticos', formData);
  },

  validar(id, data) {
    return api.patch(`/diagnosticos/${id}/validar`, data);
  },
};

export default diagnosticoService;