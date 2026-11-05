import api from '../api';

const usuarioService = {
  perfil() {
    return api.get('/profile');
  },

  listar(params = {}) {
    return api.get('/users', { params });
  },

  roles() {
    return api.get('/roles');
  },

  crear(data) {
    return api.post('/users', data);
  },

  actualizar(id, data) {
    return api.put(`/users/${id}`, data);
  },

  desactivar(id) {
    return api.delete(`/users/${id}`);
  },

  activar(id) {
    return api.patch(`/users/${id}/activar`);
  },

  listarTecnicosInspeccion() {
    return api.get('/users/tecnicos-inspeccion');
  },
};

export default usuarioService;
