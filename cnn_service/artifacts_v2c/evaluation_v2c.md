# Evaluación v2c: comparación de modelos

Solo el modelo (sin filtros de imagen). "—" = el modelo vio ese conjunto al entrenar (no se evalúa).

## Accuracy (hojas de papa)

| Conjunto | n | v1 | v2b | v2c |
|---|---|---|---|---|
| tanzania_test | 1313 | 70.0% | 98.6% | 98.3% |
| etiopia_holeta | 429 | 63.2% | 86.5% | 95.8% |
| peru_reserva | 160 | 47.5% | 98.1% | 98.1% |
| plantdoc_papa | 75 | 56.0% | 45.3% | 38.7% |

## F1 macro

| Conjunto | v1 | v2b | v2c |
|---|---|---|---|
| tanzania_test | 0.704 | 0.984 | 0.980 |
| etiopia_holeta | 0.600 | 0.845 | 0.947 |
| peru_reserva | 0.422 | 0.984 | 0.981 |
| plantdoc_papa | 0.552 | 0.525 | 0.441 |

## Recall por clase

| Conjunto | Clase | v1 | v2b | v2c |
|---|---|---|---|---|
| tanzania_test | early_blight | 96.4% | 96.4% | 94.2% |
| tanzania_test | healthy | 58.6% | 99.7% | 100.0% |
| tanzania_test | late_blight | 66.3% | 98.6% | 99.1% |
| etiopia_holeta | healthy | 56.4% | 86.7% | 96.4% |
| etiopia_holeta | late_blight | 100.0% | 85.1% | 92.5% |
| peru_reserva | healthy | 8.3% | 98.6% | 98.6% |
| peru_reserva | late_blight | 79.5% | 97.7% | 97.7% |
| plantdoc_papa | early_blight | 40.0% | 30.0% | 22.5% |
| plantdoc_papa | late_blight | 74.3% | 62.9% | 57.1% |

## Hoja de papa rechazada como "no es papa"

| Conjunto | v1 | v2b | v2c |
|---|---|---|---|
| tanzania_test | — | 0.0% | 0.0% |
| etiopia_holeta | — | 6.5% | 2.3% |
| peru_reserva | — | 0.6% | 0.0% |
| plantdoc_papa | — | 30.7% | 25.3% |
| inat_papa (n=275) | 0.0% | 80.0% | 19.3% |

## Calibración (ECE, menor es mejor) y errores entregados por el servicio

| Conjunto | v1 ECE / errores entregados | v2b ECE / errores entregados | v2c ECE / errores entregados |
|---|---|---|---|
| tanzania_test | 0.161 / 348 | 0.005 / 16 | 0.005 / 19 |
| etiopia_holeta | 0.220 / 140 | 0.017 / 39 | 0.093 / 9 |
| peru_reserva | 0.352 / 67 | 0.022 / 2 | 0.007 / 2 |
| plantdoc_papa | 0.354 / 32 | 0.337 / 30 | 0.357 / 33 |

## Hojas de otros cultivos (n=473): falso diagnóstico de papa entregado

| v1 | v2b | v2c |
|---|---|---|
| 94.1% | 4.2% | 10.6% |
