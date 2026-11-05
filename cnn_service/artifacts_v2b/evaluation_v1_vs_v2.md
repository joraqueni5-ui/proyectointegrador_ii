# Evaluación comparativa: CNN v1 (actual) vs v2 (prototipo)

Todas las cifras se calculan con el modelo solo (sin los filtros de calidad de imagen del servicio).

| Conjunto | n | v1 accuracy | v2 accuracy | v1 F1 macro | v2 F1 macro | v1 ECE | v2 ECE |
|---|---|---|---|---|---|---|---|
| tanzania_test | 623 | 58.1% | 97.6% | 0.583 | 0.974 | 0.266 | 0.012 |
| peru_cajamarca | 160 | 47.5% | 98.1% | 0.422 | 0.984 | 0.352 | 0.022 |
| plantdoc_papa | 75 | 56.0% | 45.3% | 0.552 | 0.525 | 0.354 | 0.337 |

ECE = error de calibración (menor es mejor): qué tan lejos está la confianza mostrada de la probabilidad real de acertar.

## Recall por clase

| Conjunto | Clase | v1 recall | v2 recall | n |
|---|---|---|---|---|
| tanzania_test | Tizón temprano | 93.1% | 94.5% | 145 |
| tanzania_test | Sana | 50.2% | 98.5% | 273 |
| tanzania_test | Tizón tardío | 43.9% | 98.5% | 205 |
| peru_cajamarca | Sana | 8.3% | 98.6% | 72 |
| peru_cajamarca | Tizón tardío | 79.5% | 97.7% | 88 |
| plantdoc_papa | Tizón temprano | 40.0% | 30.0% | 40 |
| plantdoc_papa | Tizón tardío | 74.3% | 62.9% | 35 |

## Confianza cuando el modelo se equivoca

| Conjunto | v1 | v2 |
|---|---|---|
| tanzania_test | 77.7% | 81.0% |
| peru_cajamarca | 76.0% | 56.6% |
| plantdoc_papa | 89.4% | 73.4% |

## Con los umbrales del servicio (confianza >= 55 % y margen >= 8 puntos)

| Conjunto | v1 se entrega | v1 acierta si entrega | v1 errores entregados | v2 se entrega | v2 acierta si entrega | v2 errores entregados |
|---|---|---|---|---|---|---|
| tanzania_test | 93.6% | 60.0% | 233 | 99.7% | 97.8% | 14 |
| peru_cajamarca | 87.5% | 52.1% | 67 | 98.1% | 98.7% | 2 |
| plantdoc_papa | 97.3% | 56.2% | 32 | 77.3% | 48.3% | 30 |

## Hojas de otros cultivos (no deben diagnosticarse como papa)

n = 213

| Métrica | v1 | v2 |
|---|---|---|
| Rechazadas como "no es papa" | 0.0% (v1 no tiene esa clase) | 91.5% |
| **Falso diagnóstico de papa entregado** | 91.5% | 4.7% |

## Filtros de calidad del servicio (antes de la red)

| Conjunto | n | rechazadas por los filtros |
|---|---|---|
| tanzania_test | 623 | 94.1% |
| peru_cajamarca | 160 | 13.1% |
| plantdoc_papa | 75 | 44.0% |
| otros_cultivos | 213 | 63.4% |
