# Umbrales de v2c-ResNet50: barrido en validación y evaluación única en prueba

Validación: 1460 fotos de papa y 402 de otros cultivos (`dataset_v2c/val`). Criterio: mayor F1 macro (3 clases de papa) con ≥ 93 % de otros cultivos rechazados; desempate (≤ 0,002 de F1): mayor cobertura. Los conjuntos de prueba **no** intervinieron en la elección.

## Elección (solo validación)

| Umbrales | Confianza | Margen | Cobertura | Accuracy entregada | F1 macro | Errores entregados | Otros rechazados |
|---|---|---|---|---|---|---|---|
| Actuales del servicio | 55 % | 8 | 97.7% | 99.2% | 0.989 | 12 | 87.6% |
| **Elegidos** | 80 % | 0 | 95.1% | 99.7% | 0.996 | 4 | 95.5% |

## Prueba (una sola evaluación)

Acierto del sistema = papa: entregada y con la clase correcta · otro cultivo: rechazada.

| Conjunto | n | v2c_resnet50 (umbrales nuevos) | v2c_resnet50 (sin umbrales) | v2b_activo (55 % / 8) | v2c_efficientnetb0 (55 % / 8) |
|---|---|---|---|---|---|
| tanzania_test | 1313 | 96.8% (cob. 98%, acc. entreg. 99.2%) | 98.3% (cob. 100%, acc. entreg. 98.3%) | 98.4% (cob. 100%, acc. entreg. 98.8%) | 98.5% (cob. 100%, acc. entreg. 98.8%) |
| etiopia_holeta | 429 | 76.0% (cob. 76%, acc. entreg. 99.7%) | 95.8% (cob. 98%, acc. entreg. 98.1%) | 81.3% (cob. 86%, acc. entreg. 94.3%) | 78.5% (cob. 79%, acc. entreg. 99.1%) |
| peru_reserva | 160 | 96.2% (cob. 97%, acc. entreg. 99.4%) | 98.1% (cob. 100%, acc. entreg. 98.1%) | 96.9% (cob. 98%, acc. entreg. 98.7%) | 97.5% (cob. 99%, acc. entreg. 98.7%) |
| plantdoc_papa | 75 | 25.3% (cob. 41%, acc. entreg. 61.3%) | 38.7% (cob. 75%, acc. entreg. 51.8%) | 37.3% (cob. 55%, acc. entreg. 68.3%) | 42.7% (cob. 65%, acc. entreg. 65.3%) |
| otros_cultivos | 473 | 95.8% rechazadas | 85.8% rechazadas | 95.8% rechazadas | 94.5% rechazadas |

## McNemar: v2c_resnet50 (umbrales nuevos) (A) vs v2b_activo (55 % / 8) (B)

| Conjunto | Solo A acierta | Solo B acierta | p-valor | ¿Significativa (p<0,05)? |
|---|---|---|---|---|
| tanzania_test | 4 | 25 | 0.000204 | sí |
| etiopia_holeta | 18 | 41 | 0.004181 | sí |
| peru_reserva | 2 | 3 | 1.0 | no |
| plantdoc_papa | 2 | 11 | 0.022461 | sí |
| otros_cultivos | 12 | 12 | 1.0 | no |
| todos_los_conjuntos | 38 | 92 | 3e-06 | sí |

## McNemar: v2c_resnet50 (umbrales nuevos) (A) vs v2c_efficientnetb0 (55 % / 8) (B)

| Conjunto | Solo A acierta | Solo B acierta | p-valor | ¿Significativa (p<0,05)? |
|---|---|---|---|---|
| tanzania_test | 5 | 27 | 0.000205 | sí |
| etiopia_holeta | 34 | 45 | 0.260552 | no |
| peru_reserva | 3 | 5 | 0.726562 | no |
| plantdoc_papa | 0 | 13 | 0.000244 | sí |
| otros_cultivos | 19 | 13 | 0.376759 | no |
| todos_los_conjuntos | 61 | 103 | 0.001367 | sí |
