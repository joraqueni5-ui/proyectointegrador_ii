// Íconos por defecto de Leaflet servidos desde el paquete (no desde cdnjs): el sistema no
// necesita internet para dibujar marcadores. Se importa una sola vez por quien use Leaflet.
import L from 'leaflet';
import iconoRetina from 'leaflet/dist/images/marker-icon-2x.png';
import icono from 'leaflet/dist/images/marker-icon.png';
import sombra from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl: iconoRetina, iconUrl: icono, shadowUrl: sombra });

export default L;
