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

## Decisión

**Se mantiene v2b (modelo activo) con confianza mínima 55 % y margen mínimo 8.** No se cambia el modelo, la ruta por defecto ni la versión de `/health`.

Tabla final (prueba, una sola evaluación; las cifras y el McNemar son los de las secciones anteriores):

| Conjunto | v2c-ResNet50 (80 % / 0) | v2b activo (55 % / 8) | v2c-EfficientNetB0 (55 % / 8) |
|---|---|---|---|
| Tanzania | 96,8 % | 98,4 % | 98,5 % |
| Etiopía | 76,0 % | 81,3 % | 78,5 % |
| Perú | 96,2 % | 96,9 % | 97,5 % |
| PlantDoc | 25,3 % | 37,3 % | 42,7 % |
| Otros cultivos rechazados | 95,8 % | 95,8 % | 94,5 % |

McNemar (v2c con umbrales nuevos contra v2b): total p = 3·10⁻⁶, con 38 casos a favor de v2c y 92 a favor de v2b; Tanzania p = 0,0002, Etiopía p = 0,004 y PlantDoc p = 0,022, todos a favor de v2b; Perú y otros cultivos sin diferencia significativa.

### Conclusiones

1. **La ventaja de v2c en Etiopía dependía de no rechazar.** Sin umbrales, v2c acierta el 95,8 % en Etiopía, pero rechaza solo el 85,8 % de otros cultivos. Al exigir un rechazo equivalente al de v2b (≥ 93 %), su cobertura en Etiopía baja a 76 % y la ventaja desaparece.
2. **Con rechazo equivalente, v2b es mejor en el total.** La diferencia es significativa a favor de v2b (p = 3·10⁻⁶).
3. **Se conserva v2b** porque ya cumple el criterio de rechazo con umbrales moderados, sin perder cobertura.

### Limitaciones

- El criterio "F1 macro sobre las fotos entregadas" mejora cuando se entrega menos, y empuja la elección al borde de la grilla (confianza 80 %, el máximo probado; margen 0).
- El conjunto de validación se usó también para calibrar la temperatura del modelo, por lo que no es totalmente independiente.
- Los conjuntos externos son pequeños (PlantDoc, 75 fotos; Perú, 160), y las diferencias allí son poco precisas.

### Trabajo futuro

- Reentrenar v2c con más ejemplos de "otros cultivos", para conservar su generalización en Etiopía y rechazar bien.
- Evaluar EfficientNetB0 para uso en celular (5,3 veces más liviano y ~17 % más rápido en CPU).
