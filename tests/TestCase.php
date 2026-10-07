<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les pages chargent leurs feuilles par @vite : sans `npm run build`,
        // le manifeste manque et la page tombe en 500. Les épreuves vérifient
        // ce que la page dit, pas la compilation des feuilles de style.
        $this->withoutVite();
    }
}
