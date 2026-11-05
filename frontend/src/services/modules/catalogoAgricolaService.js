import api from '../api';

const catalogoAgricolaService = {
  etapasFenologicas() {
    return api.get('/etapas-fenologicas');
  },
};

export default catalogoAgricolaService;
