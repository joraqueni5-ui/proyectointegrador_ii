<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Base de datos exclusiva de pruebas. Si por cualquier motivo la conexión apunta a otra
     * (por ejemplo la base real), las pruebas se detienen ANTES de tocar nada.
     */
    private const BASE_DE_PRUEBAS = 'proinpa_test';

    protected function setUp(): void
    {
        parent::setUp();

        $base = (string) config('database.connections.'.config('database.default').'.database');

        if ($base !== self::BASE_DE_PRUEBAS) {
            throw new RuntimeException(
                "Pruebas abortadas: la base de datos activa es '{$base}', no '".self::BASE_DE_PRUEBAS."'. "
                .'Las pruebas nunca deben ejecutarse contra la base real.'
            );
        }
    }
}
