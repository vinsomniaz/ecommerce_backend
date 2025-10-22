<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function puede_crear_categoria_nivel_1()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Componentes PC',
                'description' => 'Categoría principal de componentes',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'slug',
                    'level'
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Componentes PC',
                    'slug' => 'componentes-pc',
                    'level' => 1,
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Componentes PC',
            'slug' => 'componentes-pc',
            'level' => 1,
        ]);
    }

    #[Test]
    public function puede_crear_categoria_nivel_2_con_padre()
    {
        $parent = Category::factory()->create([
            'name' => 'Componentes PC',
            'level' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Procesadores',
                'parent_id' => $parent->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Procesadores',
                    'parent_id' => $parent->id,
                    'level' => 2,
                ]
            ]);
    }

    #[Test]
    public function puede_crear_categoria_nivel_3()
    {
        $nivel1 = Category::factory()->create(['level' => 1]);
        $nivel2 = Category::factory()->create([
            'parent_id' => $nivel1->id,
            'level' => 2,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'AMD Ryzen',
                'parent_id' => $nivel2->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'level' => 3,
                    'parent_id' => $nivel2->id,
                ]
            ]);
    }

    #[Test]
    public function slug_se_genera_automaticamente()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Tarjetas Gráficas RTX',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'tarjetas-graficas-rtx',
                ]
            ]);
    }

    #[Test]
    public function puede_enviar_slug_personalizado()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Procesadores AMD',
                'slug' => 'amd-processors',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'slug' => 'amd-processors',
                ]
            ]);
    }

    #[Test]
    public function requiere_autenticacion()
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function valida_nombre_requerido()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'description' => 'Sin nombre',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function valida_nombre_maximo_100_caracteres()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => str_repeat('a', 101),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function valida_nombre_unico_en_mismo_nivel()
    {
        Category::factory()->create([
            'name' => 'Procesadores',
            'parent_id' => null,
            'level' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Procesadores',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function permite_mismo_nombre_en_diferente_padre()
    {
        $padre1 = Category::factory()->create(['level' => 1]);
        $padre2 = Category::factory()->create(['level' => 1]);

        Category::factory()->create([
            'name' => 'Accesorios',
            'parent_id' => $padre1->id,
            'level' => 2,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Accesorios',
                'parent_id' => $padre2->id,
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function no_permite_mas_de_3_niveles()
    {
        $nivel1 = Category::factory()->create(['level' => 1]);
        $nivel2 = Category::factory()->create([
            'parent_id' => $nivel1->id,
            'level' => 2,
        ]);
        $nivel3 = Category::factory()->create([
            'parent_id' => $nivel2->id,
            'level' => 3,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Nivel 4 prohibido',
                'parent_id' => $nivel3->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function valida_parent_id_existe()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'parent_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function valida_nivel_coherente_con_padre()
    {
        $parent = Category::factory()->create(['level' => 1]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'parent_id' => $parent->id,
                'level' => 3,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function categoria_sin_padre_debe_ser_nivel_1()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'level' => 2,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function valida_slug_unico()
    {
        Category::factory()->create([
            'slug' => 'procesadores',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Otra categoria',
                'slug' => 'procesadores',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function valida_formato_slug()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'slug' => 'MAYUSCULAS-NO-PERMITIDAS',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function puede_establecer_orden()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'order' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'order' => 5
                ]
            ]);
    }

    #[Test]
    public function puede_establecer_estado_inactivo()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'Test',
                'is_active' => false,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'is_active' => false
                ]
            ]);
    }

    #[Test]
    public function puede_actualizar_solo_nombre_con_patch()
    {
        $category = Category::factory()->create([
            'name' => 'Computadoras',
            'description' => 'Descripción original',
            'order' => 5,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'Laptops', // Solo cambiamos el nombre
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Laptops',
                    'description' => 'Descripción original', // Se mantiene
                    'order' => 5, // Se mantiene
                ]
            ]);
    }

    #[Test]
    public function puede_actualizar_solo_estado_con_patch()
    {
        $category = Category::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", [
                'is_active' => false, // Solo cambiamos el estado
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'is_active' => false
                ]
            ]);
    }

    #[Test]
    public function put_vs_patch_ambos_funcionan()
    {
        $category = Category::factory()->create(['name' => 'Original']);

        // PUT - actualización completa
        $responsePut = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'Actualizado con PUT',
            ]);

        $responsePut->assertStatus(200);

        // PATCH - actualización parcial
        $responsePatch = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'Actualizado con PATCH',
            ]);

        $responsePatch->assertStatus(200);
    }
}
