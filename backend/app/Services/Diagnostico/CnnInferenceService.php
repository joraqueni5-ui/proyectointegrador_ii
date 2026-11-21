<?php

namespace App\Services\Diagnostico;

use App\Exceptions\ImagenRechazadaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CnnInferenceService
{
    public function predecir(UploadedFile $fotografia): array
    {
        $baseUrl = rtrim((string) config('services.cnn.url'), '/');

        if ($baseUrl === '') {
            throw new HttpException(
                503,
                'El servicio CNN no está configurado. Define CNN_SERVICE_URL en el backend.'
            );
        }

        try {
            $response = Http::timeout(90)
                ->connectTimeout(10)
                ->acceptJson()
                ->attach(
                    'file',
                    file_get_contents($fotografia->getRealPath()),
                    $fotografia->getClientOriginalName(),
                    ['Content-Type' => $fotografia->getMimeType() ?: 'application/octet-stream']
                )
                ->post("{$baseUrl}/predict");
        } catch (ConnectionException $exception) {
            report($exception);

            throw new HttpException(
                503,
                'No se pudo conectar con el servicio de Inteligencia Artificial. Verifica que cnn_service esté ejecutándose.'
            );
        }

        if (! $response->successful()) {
            // FastAPI puede devolver "detail" como texto o como lista de errores.
            $detalle = $response->json('detail') ?: $response->json('message');
            $message = is_string($detalle) && $detalle !== ''
                ? $detalle
                : 'El servicio CNN no pudo procesar la imagen.';

            $status = in_array($response->status(), [413, 422, 503], true)
                ? $response->status()
                : 502;

            // El servicio CNN indica el motivo exacto del rechazo y un consejo para repetir la foto.
            $codigo = $response->json('codigo');

            if (in_array($status, [413, 422], true) && is_string($codigo) && $codigo !== '') {
                throw new ImagenRechazadaException(
                    (string) $message,
                    $status,
                    $codigo,
                    (string) ($response->json('consejo') ?: 'Tome otra fotografía de la hoja con buena luz y enfoque.')
                );
            }

            throw new HttpException($status, (string) $message);
        }

        $prediction = $response->json();

        if (! is_array($prediction)) {
            throw new HttpException(502, 'La respuesta del servicio CNN no es un JSON válido.');
        }

        foreach (['codigo_enfermedad', 'confianza', 'modelo_version', 'top3'] as $field) {
            if (! array_key_exists($field, $prediction)) {
                throw new HttpException(
                    502,
                    "La respuesta del servicio CNN es inválida: falta '{$field}'."
                );
            }
        }

        $confidence = (float) $prediction['confianza'];

        if ($confidence < 0 || $confidence > 100) {
            throw new HttpException(
                502,
                'El servicio CNN devolvió un porcentaje de confianza fuera del rango 0-100.'
            );
        }

        return $prediction;
    }

    public function estado(): array
    {
        $baseUrl = rtrim((string) config('services.cnn.url'), '/');

        if ($baseUrl === '') {
            return [
                'status' => 'not_configured',
                'model_loaded' => false,
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->get("{$baseUrl}/health");

            if (! $response->successful()) {
                return [
                    'status' => 'unavailable',
                    'model_loaded' => false,
                ];
            }

            return $response->json();
        } catch (ConnectionException) {
            return [
                'status' => 'unavailable',
                'model_loaded' => false,
            ];
        }
    }
}
