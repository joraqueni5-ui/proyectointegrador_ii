#!/bin/bash
# Entrena v2c: ResNet50 y luego EfficientNetB0 (en CPU, en secuencia). Registro en artifacts_v2c/.
cd "$(dirname "$0")/.."
mkdir -p artifacts_v2c
PY=.venv/Scripts/python.exe
$PY -u -I training_v2/train_v2.py --data dataset_v2c --out model_v2c_resnet50 --artifacts artifacts_v2c/resnet50 \
  --backbone resnet50 --zoom 0.35 --epochs-head 4 --epochs-finetune 3 \
  --version PROINPA-ResNet50-v2.2-prototipo > artifacts_v2c/log_resnet50.txt 2>&1
$PY -u -I training_v2/train_v2.py --data dataset_v2c --out model_v2c_effnet --artifacts artifacts_v2c/effnet \
  --backbone efficientnetb0 --zoom 0.35 --epochs-head 4 --epochs-finetune 3 \
  --version PROINPA-EfficientNetB0-v2.2-prototipo > artifacts_v2c/log_effnet.txt 2>&1
echo TERMINADO > artifacts_v2c/terminado.txt
