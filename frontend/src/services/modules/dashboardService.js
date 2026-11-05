import api from '../api';

const dashboardService = {
  kpis() {
    return api.get('/dashboard/kpis');
  },

  mapaParcelas() {
    return api.get('/dashboard/mapa-parcelas');
  },

  alertasRecientes() {
    return api.get('/dashboard/alertas-recientes');
  },

  inspeccionesRecientes() {
    return api.get('/dashboard/inspecciones-recientes');
  },

  graficoEnfermedades() {
    return api.get('/dashboard/grafico-enfermedades');
  },

  graficoCosechas() {
    return api.get('/dashboard/grafico-cosechas');
  },
};

export default dashboardService;
