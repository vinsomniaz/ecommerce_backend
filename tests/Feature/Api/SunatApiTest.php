<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\SunatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Mockery;

class SunatApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function requiere_autenticacion_para_validar_documentos()
    {
        $response = $this->getJson('/api/sunat/validate/01/12345678');

        $response->assertStatus(401);
    }

    #[Test]
    public function valida_tipo_de_documento_invalido()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/99/12345678');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Tipo de documento no válido.');
    }

    #[Test]
    public function valida_longitud_de_dni_incorrecta()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/123456'); // Solo 6 dígitos

        $response->assertStatus(400)
            ->assertJsonPath('message', 'DNI debe tener 8 dígitos.');
    }

    #[Test]
    public function valida_longitud_de_ruc_incorrecta()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/06/123456789'); // Solo 9 dígitos

        $response->assertStatus(400)
            ->assertJsonPath('message', 'RUC debe tener 11 dígitos.');
    }

    #[Test]
    public function puede_validar_dni_exitosamente()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('01', '12345678')
            ->andReturn([
                'success' => true,
                'data' => [
                    'tipo_documento' => '01',
                    'numero_documento' => '12345678',
                    'nombres' => 'JUAN',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'GOMEZ',
                    'nombre_completo' => 'JUAN PEREZ GOMEZ'
                ]
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/12345678');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tipo_documento',
                'numero_documento',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'nombre_completo'
            ])
            ->assertJsonPath('numero_documento', '12345678')
            ->assertJsonPath('nombres', 'JUAN')
            ->assertJsonPath('apellido_paterno', 'PEREZ')
            ->assertJsonPath('apellido_materno', 'GOMEZ');
    }

    #[Test]
    public function puede_validar_ruc_exitosamente()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('06', '20123456789')
            ->andReturn([
                'success' => true,
                'data' => [
                    'tipo_documento' => '06',
                    'numero_documento' => '20123456789',
                    'razon_social' => 'EMPRESA SAC',
                    'nombre_comercial' => 'EMPRESA',
                    'direccion' => 'AV. EJEMPLO 123',
                    'ubigeo' => '150101',
                    'departamento' => 'LIMA',
                    'provincia' => 'LIMA',
                    'distrito' => 'LIMA',
                    'estado' => 'ACTIVO',
                    'condicion' => 'HABIDO'
                ]
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/06/20123456789');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tipo_documento',
                'numero_documento',
                'razon_social',
                'nombre_comercial',
                'direccion',
                'ubigeo',
                'departamento',
                'provincia',
                'distrito',
                'estado',
                'condicion'
            ])
            ->assertJsonPath('numero_documento', '20123456789')
            ->assertJsonPath('razon_social', 'EMPRESA SAC')
            ->assertJsonPath('estado', 'ACTIVO');
    }

    #[Test]
    public function maneja_dni_no_encontrado()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('01', '99999999')
            ->andReturn([
                'success' => false,
                'status' => 404,
                'message' => 'Documento no encontrado.'
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/99999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Documento no encontrado.');
    }

    #[Test]
    public function maneja_ruc_no_encontrado()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('06', '20999999999')
            ->andReturn([
                'success' => false,
                'status' => 404,
                'message' => 'Documento no encontrado.'
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/06/20999999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Documento no encontrado.');
    }

    #[Test]
    public function maneja_error_de_servicio_no_disponible()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('01', '12345678')
            ->andReturn([
                'success' => false,
                'status' => 503,
                'message' => 'Servicio no disponible. Puede continuar con el registro manual.'
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/12345678');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Servicio no disponible. Puede continuar con el registro manual.');
    }

    #[Test]
    public function maneja_token_no_configurado()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('01', '12345678')
            ->andReturn([
                'success' => false,
                'status' => 503,
                'message' => 'Servicio no configurado.'
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/12345678');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Servicio no configurado.');
    }

    #[Test]
    public function valida_formato_de_respuesta_para_dni()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('01', '87654321')
            ->andReturn([
                'success' => true,
                'data' => [
                    'tipo_documento' => '01',
                    'numero_documento' => '87654321',
                    'nombres' => 'MARIA',
                    'apellido_paterno' => 'LOPEZ',
                    'apellido_materno' => 'GARCIA',
                    'nombre_completo' => 'MARIA LOPEZ GARCIA'
                ]
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/01/87654321');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayHasKey('tipo_documento', $data);
        $this->assertArrayHasKey('numero_documento', $data);
        $this->assertArrayHasKey('nombres', $data);
        $this->assertArrayHasKey('apellido_paterno', $data);
        $this->assertArrayHasKey('apellido_materno', $data);
        $this->assertArrayHasKey('nombre_completo', $data);

        $this->assertEquals('01', $data['tipo_documento']);
        $this->assertEquals('87654321', $data['numero_documento']);
    }

    #[Test]
    public function valida_formato_de_respuesta_para_ruc()
    {
        // Mock del SunatService
        $mockService = Mockery::mock(SunatService::class);
        $mockService->shouldReceive('validateDocument')
            ->once()
            ->with('06', '20987654321')
            ->andReturn([
                'success' => true,
                'data' => [
                    'tipo_documento' => '06',
                    'numero_documento' => '20987654321',
                    'razon_social' => 'CORPORACION XYZ SAC',
                    'nombre_comercial' => 'XYZ CORP',
                    'direccion' => 'JR. LOS HEROES 456',
                    'ubigeo' => '150101',
                    'departamento' => 'LIMA',
                    'provincia' => 'LIMA',
                    'distrito' => 'LIMA',
                    'estado' => 'ACTIVO',
                    'condicion' => 'HABIDO'
                ]
            ]);

        $this->app->instance(SunatService::class, $mockService);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sunat/validate/06/20987654321');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertArrayHasKey('tipo_documento', $data);
        $this->assertArrayHasKey('numero_documento', $data);
        $this->assertArrayHasKey('razon_social', $data);
        $this->assertArrayHasKey('nombre_comercial', $data);
        $this->assertArrayHasKey('direccion', $data);
        $this->assertArrayHasKey('ubigeo', $data);
        $this->assertArrayHasKey('departamento', $data);
        $this->assertArrayHasKey('provincia', $data);
        $this->assertArrayHasKey('distrito', $data);
        $this->assertArrayHasKey('estado', $data);
        $this->assertArrayHasKey('condicion', $data);

        $this->assertEquals('06', $data['tipo_documento']);
        $this->assertEquals('20987654321', $data['numero_documento']);
    }
}
