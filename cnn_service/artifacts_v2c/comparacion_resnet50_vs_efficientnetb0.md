# ResNet50 vs EfficientNetB0 (v2c, misma partición)

## Tamaño, parámetros y velocidad (CPU, 1 imagen)

| Modelo | Archivo (MB) | Parámetros | Mediana (ms) | P95 (ms) |
|---|---|---|---|---|
| resnet50 | 97.2 | 24,113,284 | 146.2 | 163.0 |
| efficientnetb0 | 18.4 | 4,378,535 | 121.3 | 134.4 |

## Accuracy por conjunto (no vistos al entrenar)

| Conjunto | n | resnet50 | efficientnetb0 |
|---|---|---|---|
| tanzania_test | 1313 | 98.3% | 98.6% |
| etiopia_holeta | 429 | 95.8% | 81.1% |
| peru_reserva | 160 | 98.1% | 97.5% |
| plantdoc_papa | 75 | 38.7% | 49.3% |
| otros_cultivos | 473 | 85.8% rechazadas | 93.0% rechazadas |

## F1 macro

| Conjunto | resnet50 | efficientnetb0 |
|---|---|---|
| tanzania_test | 0.980 | 0.984 |
| etiopia_holeta | 0.947 | 0.861 |
| peru_reserva | 0.981 | 0.980 |
| plantdoc_papa | 0.441 | 0.569 |

## Prueba de McNemar (resnet50 = A, efficientnetb0 = B)

H0: ambos modelos tienen la misma tasa de error. Diferencia significativa si p < 0,05.

| Conjunto | Solo A acierta | Solo B acierta | p-valor | Método | ¿Significativa? |
|---|---|---|---|---|---|
| tanzania_test | 9 | 13 | 0.523467 | exacta binomial | no |
| etiopia_holeta | 68 | 5 | 0.0 | chi-cuadrado con corrección de continuidad | sí |
| peru_reserva | 3 | 2 | 1.0 | exacta binomial | no |
| plantdoc_papa | 4 | 12 | 0.076813 | exacta binomial | no |
| otros_cultivos | 9 | 43 | 5e-06 | chi-cuadrado con corrección de continuidad | sí |
| todos_los_conjuntos | 93 | 75 | 0.189662 | chi-cuadrado con corrección de continuidad | no |

## Conclusión

- **EfficientNetB0 es 5,3 veces más liviano** (18,4 MB frente a 97,2 MB; 4,4 M frente a 24,1 M de parámetros) y ~17 % más rápido en CPU (121 ms frente a 146 ms por imagen).
- En el conjunto de la misma fuente (Tanzania) y en Perú no hay diferencia significativa (McNemar p = 0,52 y p = 1,0).
- **ResNet50 generaliza mucho mejor a una parcela y cámara nuevas** (Etiopía: 95,8 % frente a 81,1 %, p < 0,001), que es el escenario más parecido al uso real.
- EfficientNetB0 rechaza mejor las hojas de otros cultivos (93,0 % frente a 85,8 %, p < 0,001) y rinde algo mejor en PlantDoc (diferencia no significativa, p = 0,08).
- En el total de todos los conjuntos no hay diferencia significativa (p = 0,19).
- **Decisión:** el modelo activo del sistema NO se cambia; cualquier cambio requiere confirmación del usuario. Si se priorizara el tamaño (despliegue en equipos modestos), EfficientNetB0 sería la alternativa, a costa de generalización.
- Duración del entrenamiento (CPU): ResNet50 139,7 min; EfficientNetB0 120,9 min.
