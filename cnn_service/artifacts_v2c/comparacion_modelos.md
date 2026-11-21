# Comparación de modelos: v2b (activo), v2c ResNet50 y v2c EfficientNetB0

## Tamaño, parámetros y velocidad (CPU, 1 imagen)

| Modelo | Archivo (MB) | Parámetros | Mediana (ms) | P95 (ms) |
|---|---|---|---|---|
| v2b_resnet50 | 97.1 | 24,113,284 | 151.6 | 166.6 |
| v2c_resnet50 | 97.2 | 24,113,284 | 148.6 | 185.0 |
| v2c_efficientnetb0 | 18.4 | 4,378,535 | 137.6 | 166.3 |

## Accuracy por conjunto (no vistos al entrenar)

| Conjunto | n | v2b_resnet50 | v2c_resnet50 | v2c_efficientnetb0 |
|---|---|---|---|---|
| tanzania_test | 1313 | 98.6% | 98.3% | 98.6% |
| etiopia_holeta | 429 | 86.5% | 95.8% | 81.1% |
| peru_reserva | 160 | 98.1% | 98.1% | 97.5% |
| plantdoc_papa | 75 | 45.3% | 38.7% | 49.3% |
| otros_cultivos | 473 | 93.5% rechazadas | 85.8% rechazadas | 93.0% rechazadas |

## F1 macro

| Conjunto | v2b_resnet50 | v2c_resnet50 | v2c_efficientnetb0 |
|---|---|---|---|
| tanzania_test | 0.984 | 0.980 | 0.984 |
| etiopia_holeta | 0.845 | 0.947 | 0.861 |
| peru_reserva | 0.984 | 0.981 | 0.980 |
| plantdoc_papa | 0.525 | 0.441 | 0.569 |

## Prueba de McNemar: v2b_resnet50 (A) vs v2c_resnet50 (B)

H0: ambos modelos tienen la misma tasa de error. Diferencia significativa si p < 0,05.

| Conjunto | Solo A acierta | Solo B acierta | p-valor | Método | ¿Significativa? |
|---|---|---|---|---|---|
| tanzania_test | 9 | 6 | 0.607239 | exacta binomial | no |
| etiopia_holeta | 1 | 41 | 0.0 | chi-cuadrado con corrección de continuidad | sí |
| peru_reserva | 1 | 1 | 1.0 | exacta binomial | no |
| plantdoc_papa | 10 | 5 | 0.301758 | exacta binomial | no |
| otros_cultivos | 42 | 6 | 0.0 | chi-cuadrado con corrección de continuidad | sí |
| todos_los_conjuntos | 63 | 59 | 0.785924 | chi-cuadrado con corrección de continuidad | no |

## Prueba de McNemar: v2b_resnet50 (A) vs v2c_efficientnetb0 (B)

H0: ambos modelos tienen la misma tasa de error. Diferencia significativa si p < 0,05.

| Conjunto | Solo A acierta | Solo B acierta | p-valor | Método | ¿Significativa? |
|---|---|---|---|---|---|
| tanzania_test | 10 | 11 | 1.0 | exacta binomial | no |
| etiopia_holeta | 55 | 32 | 0.018342 | chi-cuadrado con corrección de continuidad | sí |
| peru_reserva | 2 | 1 | 1.0 | exacta binomial | no |
| plantdoc_papa | 7 | 10 | 0.629059 | exacta binomial | no |
| otros_cultivos | 17 | 15 | 0.859684 | chi-cuadrado con corrección de continuidad | no |
| todos_los_conjuntos | 91 | 69 | 0.096875 | chi-cuadrado con corrección de continuidad | no |

## Prueba de McNemar: v2c_resnet50 (A) vs v2c_efficientnetb0 (B)

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

- El modelo activo (v2b, ResNet50) **no se cambia**.
- v2b frente a v2c-ResNet50: iguales en Tanzania, Perú y PlantDoc; v2c es mejor en Etiopía (86,5 % → 95,8 %, p < 0,001) pero acepta más hojas de otros cultivos (v2b rechaza el 93,5 % frente al 85,8 % de v2c, p < 0,001). En el total no hay diferencia significativa (p = 0,79).
- v2b frente a v2c-EfficientNetB0: v2b es mejor en Etiopía (86,5 % frente a 81,1 %, p = 0,018); en el resto y en el total no hay diferencia significativa (p = 0,097).
- Tiempos en CPU (1 imagen): v2b 147,5 ms, v2c-ResNet50 145,0 ms, v2c-EfficientNetB0 125,6 ms.
- Etiopía y otros cultivos son los conjuntos que más separan a los modelos; en Tanzania y Perú los tres rinden igual.
