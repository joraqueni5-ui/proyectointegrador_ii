import api from '../api';

const reporteService = {
  preview(params = {}) {
    return api.get('/reportes', { params });
  },

  excel(params = {}) {
    return api.get('/reportes/excel', {
      params,
      responseType: 'blob',
    });
  },

  pdf(params = {}) {
    return api.get('/reportes/pdf', {
      params,
      responseType: 'blob',
    });
  },
};

export default reporteService;