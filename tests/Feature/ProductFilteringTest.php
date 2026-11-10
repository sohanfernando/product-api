<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductFilteringTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sanctum.stateful' => ['localhost'],
            'session.secure' => false,
        ]);

        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_filters_and_sorting_return_only_matching_owned_products(): void
    {
        $user = User::factory()->create();

        $cheap = $user->products()->create([
            'name' => 'Basic Keyboard',
            'price' => '50.00',
            'stock' => 5,
        ]);

        $expensive = $user->products()->create([
            'name' => 'Premium Keyboard',
            'price' => '150.00',
            'stock' => 2,
        ]);

        $user->products()->create([
            'name' => 'Budget Keyboard',
            'price' => '25.00',
            'stock' => 5,
        ]);

        $user->products()->create([
            'name' => 'Luxury Keyboard',
            'price' => '250.00',
            'stock' => 5,
        ]);

        $user->products()->create([
            'name' => 'Unavailable Keyboard',
            'price' => '100.00',
            'stock' => 0,
        ]);

        $user->products()->create([
            'name' => 'Mouse',
            'price' => '100.00',
            'stock' => 5,
        ]);

        User::factory()->create()->products()->create([
            'name' => 'Private Accessory',
            'description' => 'Keyboard accessory',
            'price' => '75.00',
            'stock' => 5,
        ]);

        $this->actingAs($user, 'web');

        $response = $this->getJson(
            '/api/products?search=keyboard'
                .'&min_price=50&max_price=200'
                .'&in_stock=1&sort=price&direction=asc'
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->assertSame(
            [$cheap->id, $expensive->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_zero_price_and_out_of_stock_filters_are_applied(): void
    {
        $user = User::factory()->create();

        $free = $user->products()->create([
            'name' => 'Free Sample',
            'price' => '0.00',
            'stock' => 0,
        ]);

        $user->products()->create([
            'name' => 'Paid Sample',
            'price' => '10.00',
            'stock' => 0,
        ]);

        $user->products()->create([
            'name' => 'Available Free Sample',
            'price' => '0.00',
            'stock' => 5,
        ]);

        $this->actingAs($user, 'web');

        $this->getJson('/api/products?min_price=0&max_price=0&in_stock=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $free->id);
    }

    public function test_invalid_filters_and_sorting_return_422(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $query = http_build_query([
            'min_price' => 200,
            'max_price' => 50,
            'in_stock' => 'invalid',
            'sort' => 'password',
            'direction' => 'sideways',
        ]);

        $this->getJson('/api/products?'.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'max_price',
                'in_stock',
                'sort',
                'direction',
            ]);
    }

    public function test_filtered_pagination_preserves_parameters_and_breaks_price_ties_by_id(): void
    {
        $user = User::factory()->create();
        $ids = [];

        for ($i = 0; $i < 16; $i++) {
            $product = $user->products()->create([
                'name' => 'Keyboard '.$i,
                'price' => '100.00',
                'stock' => 5,
            ]);

            $ids[] = $product->id;
        }

        $user->products()->create([
            'name' => 'Excluded Keyboard',
            'price' => '300.00',
            'stock' => 5,
        ]);

        $this->actingAs($user, 'web');

        $filters = [
            'search' => 'keyboard',
            'min_price' => '50',
            'max_price' => '200',
            'in_stock' => '1',
            'sort' => 'price',
            'direction' => 'asc',
        ];

        $response = $this->getJson(
            '/api/products?'.http_build_query($filters)
        );

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16);

        $this->assertSame(
            array_slice($ids, 0, 15),
            array_column($response->json('data'), 'id'),
        );

        $nextUrl = $response->json('links.next');

        parse_str(parse_url($nextUrl, PHP_URL_QUERY), $nextParameters);

        $this->assertEquals(
            [...$filters, 'page' => '2'],
            $nextParameters,
        );

        $this->getJson('/api/products?'.http_build_query($nextParameters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[15])
            ->assertJsonPath('meta.total', 16);
    }
}
