<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http; // ¡Importante!
use Tests\TestCase;

class SunatApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_validate_a_ruc_using_external_api_mock()
    {
        // 1. Preparamos la respuesta falsa que esperamos de la API
        $mockRucData = [
            'success' => true,
            'ruc' => '20100053455',
            'razonSocial' => 'EMPRESA DE PRUEBA S.A.C.',
            'nombreComercial' => 'PRUEBA SAC',
            'direccion' => 'AV. FALSA 123',
            'ubigeo' => '150101',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
        ];

        // 2. Instruimos a Laravel para que "intercepte" cualquier llamada
        // a esta URL y devuelva nuestro JSON falso en lugar de llamar a la API real.
        Http::fake([
            'dniruc.apisperu.com/api/v1/ruc/20100053455*' => Http::response($mockRucData, 200),
        ]);

        // 3. Ejecutamos nuestro endpoint
        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/sunat/validate/06/20100053455');
        
        $response->assertStatus(200);
        
        // 4. Verificamos que nuestro controlador formateó la respuesta
        $response->assertJson([
            'tipo_documento' => '06',
            'razon_social' => 'EMPRESA DE PRUEBA S.A.C.'
        ]);
    }
    
    /** @test */
    public function it_handles_api_failure_for_non_existent_document()
    {
        // Simulamos un error 404 de la API
        Http::fake([
            'dniruc.apisperu.com/api/v1/dni/00000000*' => Http::response(['message' => 'Not found'], 404),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/sunat/validate/01/00000000');
        
        // Tu controlador debe manejar esto y devolver un 404
        $response->assertStatus(404);
        $response->assertJson(['message' => 'Documento no encontrado.']);
    }
}