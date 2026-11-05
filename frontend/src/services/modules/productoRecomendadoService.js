import api from '../api';

const productoRecomendadoService = {
  listar(idEnfermedad) {
    return api.get(`/enfermedades/${idEnfermedad}/productos-recomendados`);
  },

  vincular(idEnfermedad, data) {
    return api.post(`/enfermedades/${idEnfermedad}/productos-recomendados`, data);
  },

  actualizar(idEnfermedad, idProducto, data) {
    return api.put(`/enfermedades/${idEnfermedad}/productos-recomendados/${idProducto}`, data);
  },

  desactivar(idEnfermedad, idProducto) {
    return api.delete(`/enfermedades/${idEnfermedad}/productos-recomendados/${idProducto}`);
  },

  eliminar(idEnfermedad, idProducto) {
    return api.delete(`/enfermedades/${idEnfermedad}/productos-recomendados/${idProducto}/force`);
  },
};

export default productoRecomendadoService;
