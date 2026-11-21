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

  // Desempeño de la CNN calculado con los diagnósticos validados (solo lectura).
  desempenoModelo(params = {}) {
    return api.get('/diagnosticos/desempeno-modelo', { params });
  },

  desempenoModeloPdf(params = {}) {
    return api.get('/diagnosticos/desempeno-modelo/pdf', { params, responseType: 'blob' });
  },
};

export default diagnosticoService;