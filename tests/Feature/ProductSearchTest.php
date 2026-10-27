<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost'], 'session.secure' => false]);
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_search_matches_name_or_description_and_excludes_other_users_products(): void
    {
        $user = User::factory()->create();
        $nameMatch = $user->products()->create(['name' => 'Mechanical Keyboard', 'price' => 10]);
        $descriptionMatch = $user->products()->create(['name' => 'Accessory', 'description' => 'A keyboard cover', 'price' => 5]);
        $user->products()->create(['name' => 'Mouse', 'price' => 8]);
        User::factory()->create()->products()->create(['name' => 'Private', 'description' => 'keyboard', 'price' => 20]);
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/products?search=KeYbOaRd');

        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->assertSame([$descriptionMatch->id, $nameMatch->id], array_column($response->json('data'), 'id'));
    }

    public function test_search_returns_empty_results_when_nothing_matches(): void
    {
        $user = User::factory()->create();
        $user->products()->create(['name' => 'Keyboard', 'price' => 10]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/products?search=monitor')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public static function accentedSearchValues(): array
    {
        return [
            'lowercase search against uppercase name' => ['éclair', 'Éclair', null],
            'uppercase search against lowercase name' => ['ÉCLAIR', 'éclair', null],
            'accented description' => ['crème', 'Dessert', 'CRÈME brûlée'],
        ];
    }

    #[DataProvider('accentedSearchValues')]
    public function test_search_matches_accented_text_case_insensitively(string $search, string $name, ?string $description): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => $name, 'description' => $description, 'price' => 10]);
        $user->products()->create(['name' => 'Unrelated product', 'price' => 5]);
        User::factory()->create()->products()->create(['name' => $name, 'description' => $description, 'price' => 20]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/products?'.http_build_query(['search' => $search]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public static function emptySearchValues(): array
    {
        return ['omitted' => [''], 'empty' => ['?search='], 'whitespace' => ['?search=%20%20%20']];
    }

    #[DataProvider('emptySearchValues')]
    public function test_missing_or_blank_search_preserves_the_existing_list(string $query): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'Keyboard', 'price' => 10]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/products'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_search_trims_surrounding_whitespace(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'Keyboard', 'price' => 10]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/products?search=%20board%20')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_search_paginates_filtered_results_and_keeps_the_term_in_links(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 16; $i++) {
            $user->products()->create(['name' => 'Keyboard '.$i, 'price' => 10]);
        }
        $user->products()->create(['name' => 'Mouse', 'price' => 8]);
        $this->actingAs($user, 'web');

        $response = $this->getJson('/api/products?search=keyboard');

        $response->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 16);
        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $nextQuery);
        $this->assertSame(['search' => 'keyboard', 'page' => '2'], $nextQuery);
        $this->getJson('/api/products?search=keyboard&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 16);
    }

    public static function literalSearchValues(): array
    {
        return [
            'percent' => ['50%', 'Save 50% today', 'Save 500 today'],
            'underscore' => ['usb_', 'USB_C adapter', 'USBA adapter'],
            'escape character' => ['wow!', 'Wow! keyboard', 'Wow keyboard'],
            'quotes and SQL text' => ["' OR 1=1 --", "Label ' OR 1=1 --", 'Other product'],
        ];
    }

    #[DataProvider('literalSearchValues')]
    public function test_search_treats_special_characters_as_literal_text(string $term, string $matching, string $other): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => $matching, 'price' => 10]);
        $user->products()->create(['name' => $other, 'price' => 8]);
        $this->actingAs($user, 'web');

        $this->getJson('/api/products?'.http_build_query(['search' => $term]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public static function invalidSearchValues(): array
    {
        return ['array' => ['?search[]=keyboard'], 'too long' => ['?search='.str_repeat('a', 256)]];
    }

    #[DataProvider('invalidSearchValues')]
    public function test_invalid_search_returns_json_422(string $query): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $this->getJson('/api/products'.$query)->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/products?search=keyboard')->assertUnauthorized();
    }
}
