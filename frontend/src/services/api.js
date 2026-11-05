import axios from 'axios';

export const FACE_RECOGNITION_ENABLED =
    import.meta.env.VITE_FACE_RECOGNITION_ENABLED === 'true';

export const FACE_THRESHOLD =
    parseFloat(import.meta.env.VITE_FACE_THRESHOLD || '0.45');

export const FACE_MODELS_URL =
    import.meta.env.VITE_FACE_MODELS_URL || '/models';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

// Raíz pública del backend (sin /api), usada para archivos en /storage.
const PUBLIC_URL = API_URL.replace(/\/api\/?$/, '');

export function urlArchivo(ruta) {
    if (!ruta) {
        return '';
    }

    const texto = String(ruta);

    if (texto.startsWith('http')) {
        return texto;
    }

    return texto.startsWith('/')
        ? `${PUBLIC_URL}${texto}`
        : `${PUBLIC_URL}/storage/${texto}`;
}

const api = axios.create({
    baseURL: API_URL,

    withCredentials: true,

    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('user_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    // Si enviamos una imagen/FormData,
    // Axios debe generar multipart/form-data automáticamente.
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
    }

    return config;
});

const RUTAS_PUBLICAS = ['/login', '/verify-otp', '/resend-otp', '/verify-face'];

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const data = error.response?.data;

        // En descargas (responseType: 'blob') el error JSON llega como Blob;
        // se convierte para que obtenerMensajeError pueda leer el mensaje.
        if (data instanceof Blob && data.type.includes('json')) {
            try {
                error.response.data = JSON.parse(await data.text());
            } catch {
                // Se deja el Blob original si no es JSON válido.
            }
        }

        // Token vencido o revocado: se limpia la sesión y se vuelve al login.
        const esRutaPublica = RUTAS_PUBLICAS.some((ruta) => error.config?.url?.startsWith(ruta));

        if (error.response?.status === 401 && !esRutaPublica) {
            localStorage.removeItem('user_token');
            localStorage.removeItem('user_data');

            if (window.location.pathname !== '/') {
                window.location.assign('/');
            }
        }

        return Promise.reject(error);
    }
);

export function descargarBlob(data, filename, type) {
    const url = window.URL.createObjectURL(new Blob([data], { type }));
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}

export default api;