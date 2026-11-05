# Evaluación comparativa: CNN v1 (actual) vs v2 (prototipo)

Todas las cifras se calculan con el modelo solo (sin los filtros de calidad de imagen del servicio).

| Conjunto | n | v1 accuracy | v2 accuracy | v1 F1 macro | v2 F1 macro | v1 ECE | v2 ECE |
|---|---|---|---|---|---|---|---|
| tanzania_test | 623 | 58.1% | 97.6% | 0.583 | 0.973 | 0.266 | 0.019 |
| peru_cajamarca | 484 | 55.0% | 1.7% | 0.497 | 0.033 | 0.296 | 0.961 |
| plantdoc_papa | 218 | 56.0% | 4.6% | 0.546 | 0.080 | 0.352 | 0.922 |

ECE = error de calibración (menor es mejor): qué tan lejos está la confianza mostrada de la probabilidad real de acertar.

## Recall por clase

| Conjunto | Clase | v1 recall | v2 recall | n |
|---|---|---|---|---|
| tanzania_test | Tizón temprano | 93.1% | 93.1% | 145 |
| tanzania_test | Sana | 50.2% | 98.5% | 273 |
| tanzania_test | Tizón tardío | 43.9% | 99.5% | 205 |
| peru_cajamarca | Sana | 16.2% | 1.8% | 216 |
| peru_cajamarca | Tizón tardío | 86.2% | 1.5% | 268 |
| plantdoc_papa | Tizón temprano | 36.8% | 0.9% | 114 |
| plantdoc_papa | Tizón tardío | 76.9% | 8.6% | 104 |

## Confianza cuando el modelo se equivoca

| Conjunto | v1 | v2 |
|---|---|---|
| tanzania_test | 77.7% | 85.1% |
| peru_cajamarca | 79.9% | 98.0% |
| plantdoc_papa | 89.7% | 97.4% |

## Con los umbrales del servicio (confianza >= 55 % y margen >= 8 puntos)

| Conjunto | v1 se entrega | v1 acierta si entrega | v1 errores entregados | v2 se entrega | v2 acierta si entrega | v2 errores entregados |
|---|---|---|---|---|---|---|
| tanzania_test | 93.6% | 60.0% | 233 | 99.8% | 97.8% | 14 |
| peru_cajamarca | 90.9% | 57.5% | 187 | 99.2% | 1.7% | 472 |
| plantdoc_papa | 97.2% | 56.1% | 93 | 99.1% | 4.2% | 207 |

## Hojas de otros cultivos (no deben diagnosticarse como papa)

n = 213

| Métrica | v1 | v2 |
|---|---|---|
| Rechazadas como "no es papa" | 0.0% (v1 no tiene esa clase) | 98.6% |
| **Falso diagnóstico de papa entregado** | 91.5% | 0.5% |

## Filtros de calidad del servicio (antes de la red)

| Conjunto | n | rechazadas por los filtros |
|---|---|---|
| tanzania_test | 623 | 94.1% |
| peru_cajamarca | 484 | 10.5% |
| plantdoc_papa | 218 | 40.8% |
| otros_cultivos | 213 | 63.4% |
