# Registro de validaciones (se completa en cada etapa)
| Etapa | Módulo | Campo o regla | Antes | Ahora | Probado |
|---|---|---|---|---|---|
| 2 | Diagnósticos CNN | Tipo de imagen JPG/PNG/WEBP | Front, Laravel y FastAPI | Igual, con mensajes que dicen qué hacer | Sí (feature test + CNN real) |
| 2 | Diagnósticos CNN | Peso máximo 5 MB | Front, Laravel (5120 KB) y FastAPI | Igual; exactamente 5 MB se acepta y 5121 KB se rechaza | Sí |
| 2 | Diagnósticos CNN | Tamaño mínimo 160×160 px | Solo FastAPI | Front + Laravel (dimensions) + FastAPI | Sí |
| 2 | Diagnósticos CNN | Permiso crear/validar | Se validaba el cuerpo primero (422) y luego el permiso | authorize() en el FormRequest: 403 antes de validar | Sí |
| 2 | Diagnósticos CNN | No validar dos veces | Ya existía (lockForUpdate) | Cubierto con prueba de 2 usuarios | Sí |
| 2 | Diagnósticos CNN | Severidad obligatoria al confirmar/corregir | Ya existía | Cubierto con prueba | Sí |
| 2 | Diagnósticos CNN | % plantas afectadas 0–100 | Ya existía | Cubierto con prueba (-1, 100.5, texto) | Sí |
| 2 | Diagnósticos CNN | Doble clic al analizar | Botón deshabilitado | + guarda en handleSave | Sí |
| 2 | Diagnósticos CNN | Motivo del rechazo de la foto | Solo texto | Código + consejo (14 motivos) | Sí (navegador real) |
| 3 | Diagnósticos CNN | Mapa de calor recibido de la CNN | No existía | Se valida: base64 estricto, firma PNG y máx. 3 MB; si falla, el diagnóstico se guarda sin mapa | Sí (3 pruebas) |
| 4 | Reportes (desempeño del modelo) | Fechas: formato AAAA-MM-DD; hasta ≥ desde | No existía | Backend (FormRequest) + frontend con el mismo mensaje | Sí |
| 4 | Reportes (desempeño del modelo) | Rango máximo de fechas | No existía | 1095 días (3 años), backend y frontend | Sí |
| 4 | Reportes (desempeño del modelo) | Versión del modelo | No existía | Debe existir en los diagnósticos (exists) y ≤ 50 caracteres | Sí |
| 4 | Reportes (desempeño del modelo) | Permiso | No existía | ver_reportes o ver_diagnosticos, comprobado en el FormRequest (403 antes de validar) | Sí (8 roles) |
| 5 | Diagnósticos CNN (exportar dataset) | Quién puede exportar | No existía | Solo Administrador, Agrónomo y Superadmin (403 antes de validar) | Sí (7 roles) |
| 5 | Diagnósticos CNN (exportar dataset) | Qué diagnósticos entran | No existía | Solo Confirmado/Corregido, enfermedad final en las 3 clases de la CNN y foto existente | Sí |
| 5 | Diagnósticos CNN (exportar dataset) | Filtros de fecha/versión | No existía | Mismas reglas que desempeño (hasta ≥ desde, ≤ 3 años, versión existente) | Sí |
| 5 | Diagnósticos CNN (exportar dataset) | Contenido del CSV | No existía | Se antepone ' a textos que empiezan con = + - @ (inyección de fórmulas) | Sí |
| 5 | Diagnósticos CNN (exportar dataset) | Sin datos para exportar | No existía | 422 con mensaje claro | Sí |
| 6 | Login | Segundo factor con rostro | /verify-face daba el token con solo correo + descriptor (sin contraseña) | Contraseña primero; el rostro exige el ticket de un solo uso; 3 intentos por ticket y bloqueo por usuario | Sí (navegador + 26 pruebas) |
| 6 | Login | Distancia facial en las respuestas | Se devolvía en éxito y en error | Nunca se devuelve | Sí |
| 6 | Login | Intentos de contraseña | Solo 10/min por IP (contador compartido entre rutas) | 5 fallos por correo+IP bloquean 15 min; limitadores por ruta | Sí |
| 6 | OTP | Intentos y uso único | Sin tope por usuario; consumo no atómico | 5 intentos por código (luego se anula); consumo atómico; un código nuevo reinicia | Sí |
| 6 | OTP | Expiración | Ya existía | Cubierta con prueba | Sí |
| 6 | Login/OTP | Correo inexistente | 404 "Usuario no encontrado" (revela quién tiene cuenta) | Misma respuesta que un código incorrecto | Sí |
| 6 | Login | Usuario desactivado | No entraba por /login, pero un token anterior seguía funcionando | Bloqueo inmediato (middleware) y tokens revocados al desactivar | Sí |
| 6 | Usuarios | Contraseña | Mínimo 8 caracteres | 8–72 con mayúscula, minúscula, número y símbolo (frontend y backend) | Sí |
| 6 | Usuarios | Celular | 8 dígitos y opcional (la BD lo exige NOT NULL: daba 500) | Obligatorio; 8 dígitos y empieza con 6 o 7 | Sí |
| 6 | Usuarios | Correo único | Sensible a mayúsculas | Único sin importar mayúsculas (también frente a datos antiguos) | Sí |
| 6 | Usuarios | CI | Único; extensión opcional | Único; extensión obligatoria al crear/editar | Sí |
| 6 | Usuarios | Desactivarse / cambiarse el rol | Desactivarse ya estaba bloqueado; cambiarse el rol no | Ambos bloqueados (403) | Sí |
| 6 | Usuarios | Editar usuarios antiguos sin CI/sexo/cargo/área | Error 500 | Se pueden editar (y el formulario exige completarlos) | Sí |
| 6 | Usuarios | Permiso gestionar_usuarios | Se comprobaba después de validar | authorize() del FormRequest: 403 antes de validar | Sí |
| 6 | Usuarios (frontend) | Mensajes por campo | Un solo mensaje arriba, solo al enviar | Mensaje bajo cada campo al escribir/salir y al enviar; botón deshabilitado mientras guarda | Sí (navegador) |

## Etapa 8 (módulos restantes)
| Módulo | Campo o regla | Antes | Ahora | Probado |
|---|---|---|---|---|
| Comunidades | latitud/longitud/altitud | -90..90 / -180..180 / ≥0 | Bolivia: -23..-9 / -70..-57 / 0..6600 (front y back) | ModulosAgricolasTest |
| Parcelas | coordenadas y altitud | mundo entero | Bolivia (igual que comunidades) | ModulosAgricolasTest |
| Parcelas | superficie | >0 | 0,01–500 ha, máx. 2 decimales | ModulosAgricolasTest |
| Parcelas | suma de superficies | sin control | ≤ superficie total declarada del productor (si la declaró) | ModulosAgricolasTest |
| Productores | superficie total | ≥0 | 0–5000 ha; mensajes de nombre alineados (mín. 3) | ModulosAgricolasTest |
| Cultivos | cantidad de semilla | >0 | 0,01–100.000 kg | ModulosAgricolasTest |
| Cosechas | cantidad | >0 | 0,001–10.000 t, máx. 3 decimales | ModulosAgricolasTest |
| Productos | precios | ≥0 | 0–9.999.999, 2 decimales; sin stock mínimo ya no da error 500 (se guarda 0) | ComercialTest |
| Movimientos | cantidad | ≥0,001 | 0,001–1.000.000, 3 decimales (stock con bloqueo ya existía) | ComercialTest |
| Ventas/Cotizaciones | fecha de emisión, precio, descuentos, líneas | precio ≥0, emisión libre | emisión no futura, precio ≥0,01, 2 decimales, máx. 100 líneas | ComercialTest |
| Ventas | cambio de estado | cualquiera | Pendiente→Parcial→Pagada; Pagada no retrocede; con bloqueo | ComercialTest |
| Inspecciones | técnico | podía repetir hora | un técnico no tiene dos inspecciones a la misma fecha y hora | InspeccionesAlertasTest |
| Reportes | rango y montos | fin ≥ inicio | además rango ≤ 3 años y monto máximo ≥ mínimo | ComercialTest |

Registros actuales que NO cumplen las reglas nuevas (no se modifican):
- Parcela id 2 (P-SUR-02): latitud 0,0000008 y longitud -0,0000004 (fuera de Bolivia).
- Productor id 4 (Humberto Yucra Colque): declara 1,20 ha y sus parcelas suman 2,70 ha.

## Decisión: stock vencido (se implementa en la etapa B8, inventario)

Antes no existía ninguna validación de "no vender producto vencido" porque la base no guardaba vencimientos
(`facturas.fecha_vencimiento` es la fecha de pago de un crédito, no del producto). Decisión acordada:

- Se agrega `inventarios.fecha_vencimiento` (migración aditiva, `nullable`, con `down()`): **vencimiento más próximo del stock
  de ese producto en ese almacén**. Los registros existentes quedan en `null` y se muestran como "—".
- Se llena o actualiza al registrar una **entrada** (campo opcional de vencimiento): si la nueva fecha es anterior a la guardada,
  la reemplaza.
- **No se bloquea la venta.** Si el vencimiento más próximo ya pasó, la pantalla muestra un aviso ámbar: «Este almacén tiene
  stock vencido de <producto> (vence <fecha>). Verifica el lote antes de vender» y pide confirmar. La confirmación queda
  registrada en la observación de la venta.
- Al registrar un **ajuste de salida por vencimiento**, el formulario pide la fecha de vencimiento del siguiente lote
  (opcional): reemplaza la guardada, o la deja en `null`.
