import api from '../api';

const reporteFitosanitarioService = {
  preview(params = {}) {
    return api.get('/reportes-fitosanitarios', { params });
  },

  excel(params = {}) {
    return api.get('/reportes-fitosanitarios/excel', {
      params,
      responseType: 'blob',
    });
  },

  pdf(params = {}) {
    return api.get('/reportes-fitosanitarios/pdf', {
      params,
      responseType: 'blob',
    });
  },
};

export default reporteFitosanitarioService;