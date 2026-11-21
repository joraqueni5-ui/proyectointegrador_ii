import api from '../api';

const authService = {
  login(data) {
    return api.post('/login', data);
  },

  verifyOtp(data) {
    return api.post('/verify-otp', data);
  },

  resendOtp(data) {
    return api.post('/resend-otp', data);
  },

  // Rostro como segundo factor: recibe el ticket (login_token) que entrega /login tras validar la contraseña.
  verifyFace(data) {
    return api.post('/verify-face', data);
  },

  // Si el rostro falla, se pasa al código OTP sin escribir otra vez la contraseña.
  usarOtpTrasRostro(data) {
    return api.post('/verify-face/usar-otp', data);
  },

  logout() {
    return api.post('/logout');
  },
};

export default authService;