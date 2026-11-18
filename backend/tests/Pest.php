<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Las pruebas de dominio (tests/Unit) no cargan Laravel: son PHP puro y rápidas
| Las pruebas de integración (tests/Feature) levantan la aplicación y usan una
| base PostgreSQL para pruebas (funeraria_test), porque las restricciones de la base
| (EXCLUDE, CHECK, disparadores) son parte de lo que se verifica.
*/

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
