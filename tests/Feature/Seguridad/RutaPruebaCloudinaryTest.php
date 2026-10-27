<?php

namespace Tests\Feature\Seguridad;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RutaPruebaCloudinaryTest extends TestCase
{
    #[Test]
    public function la_ruta_de_prueba_cloudinary_no_existe(): void
    {
        $this->get('/test-cloudinary')->assertNotFound();
    }

    #[Test]
    public function el_archivo_de_prueba_no_esta_en_public(): void
    {
        $this->assertFileDoesNotExist(public_path('test.jpg'));
    }

    #[Test]
    public function el_health_check_sigue_disponible(): void
    {
        $this->get('/ping')->assertOk();
    }
}
