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

  verifyFace(data) {
    return api.post('/verify-face', data);
  },

  logout() {
    return api.post('/logout');
  },
};

export default authService;