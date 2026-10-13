<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class AdversarialProductSearchChallengeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin();
    }

    /**
     * Challenge 1: Category ID filtering when category_id is empty string, null, absent, or whitespace
     */
    public function test_challenge_category_id_empty_null_absent_whitespace_returns_all_products(): void
    {
        $cat1 = $this->createCategory(['name' => 'Cat Alpha']);
        $cat2 = $this->createCategory(['name' => 'Cat Beta']);
        $prod1 = $this->createProduct($cat1, ['name' => 'Product 1']);
        $prod2 = $this->createProduct($cat2, ['name' => 'Product 2']);

        // 1. Absent parameter
        $resAbsent = $this->actingAs($this->admin)->get(route('productos.index'));
        $resAbsent->assertOk();
        $resAbsent->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 2);

        // 2. Empty string
        $resEmpty = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => '']));
        $resEmpty->assertOk();
        $resEmpty->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 2);

        // 3. Whitespace string (trimmed by Laravel middleware to empty string)
        $resWhitespace = $this->actingAs($this->admin)->get(route('productos.index', ['category_id' => '   ']));
        $resWhitespace->assertOk();
        $resWhitespace->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 2);
    }

    /**
     * Challenge 2: Category ID filtering when category_id is an ID that has zero products
     */
    public function test_challenge_category_id_with_zero_products_returns_empty_pagination(): void
    {
        $catWithProducts = $this->createCategory(['name' => 'With Products']);
        $emptyCategory = $this->createCategory(['name' => 'Empty Category']);

        $this->createProduct($catWithProducts, ['name' => 'Item 1']);
        $this->createProduct($catWithProducts, ['name' => 'Item 2']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => $emptyCategory->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('products', function (LengthAwarePaginator $products) {
            return $products->total() === 0 && $products->count() === 0 && $products->isEmpty();
        });
    }

    /**
     * Challenge 3: Category ID filtering when category_id is invalid or non-existent ID
     */
    public function test_challenge_category_id_nonexistent_and_invalid_returns_empty_pagination_without_error(): void
    {
        $cat = $this->createCategory(['name' => 'Normal Cat']);
        $this->createProduct($cat, ['name' => 'Existing Product']);

        // Non-existent numeric ID
        $response999 = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => 999999,
        ]));
        $response999->assertOk();
        $response999->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 0);

        // Non-existent negative ID
        $responseNeg = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => -1,
        ]));
        $responseNeg->assertOk();
        $responseNeg->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 0);

        // Arbitrary alphanumeric string ID
        $responseStr = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => 'non_existent_slug',
        ]));
        $responseStr->assertOk();
        $responseStr->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 0);

        // Array category_id parameter e.g. category_id[]=1
        $responseArr = $this->actingAs($this->admin)->get(route('productos.index', [
            'category_id' => [$cat->id],
        ]));
        $responseArr->assertOk();
    }

    /**
     * Challenge 4: Text search q with special characters and SQL injection attempts
     */
    public function test_challenge_text_search_q_handles_special_characters_and_wildcards(): void
    {
        $cat = $this->createCategory(['name' => 'Test Cat']);
        $prodPercent = $this->createProduct($cat, [
            'name' => 'Caucho 50% Natural',
            'reference' => 'REF_50',
            'description' => "Pieza 'Especial' con \"Comillas\"",
        ]);
        $prodOther = $this->createProduct($cat, [
            'name' => 'Buje Sencillo',
            'reference' => 'REF-STD',
            'description' => 'Sin caracteres especiales',
        ]);

        // 1. Single quote in q
        $resQuote = $this->actingAs($this->admin)->get(route('productos.index', ['q' => "'Especial'"]));
        $resQuote->assertOk();
        $resQuote->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->contains('id', $prodPercent->id) && ! $p->contains('id', $prodOther->id));

        // 2. Double quote in q
        $resDQuote = $this->actingAs($this->admin)->get(route('productos.index', ['q' => '"Comillas"']));
        $resDQuote->assertOk();
        $resDQuote->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->contains('id', $prodPercent->id) && ! $p->contains('id', $prodOther->id));

        // 3. Percent character in q
        $resPercent = $this->actingAs($this->admin)->get(route('productos.index', ['q' => '50%']));
        $resPercent->assertOk();
        $resPercent->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->contains('id', $prodPercent->id));

        // 4. Underscore character in q
        $resUnderscore = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'REF_50']));
        $resUnderscore->assertOk();
        $resUnderscore->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->contains('id', $prodPercent->id));

        // 5. SQL injection probe string
        $sqlInjection = "' OR 1=1 --; DROP TABLE products; --";
        $resSqlInj = $this->actingAs($this->admin)->get(route('productos.index', ['q' => $sqlInjection]));
        $resSqlInj->assertOk();
        // Database must remain intact and return 0 results
        $resSqlInj->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 0);
        $this->assertDatabaseHas('products', ['id' => $prodPercent->id]);

        // 6. Accented characters
        $catAccents = $this->createCategory(['name' => 'Amortiguación']);
        $prodAccents = $this->createProduct($catAccents, ['name' => 'Válvula de Presión']);
        $resAccents = $this->actingAs($this->admin)->get(route('productos.index', ['q' => 'Presión']));
        $resAccents->assertOk();
        $resAccents->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->contains('id', $prodAccents->id));

        // 7. Very long query string (buffer / regex / memory stress)
        $longQuery = str_repeat('abc123_', 500); // 3500 chars
        $resLong = $this->actingAs($this->admin)->get(route('productos.index', ['q' => $longQuery]));
        $resLong->assertOk();
        $resLong->assertViewHas('products', fn (LengthAwarePaginator $p) => $p->total() === 0);
    }

    /**
     * Challenge 5: Text search q matching category name while filtering by a DIFFERENT category_id
     * Must return 0 products because they do not match the selected category.
     */
    public function test_challenge_search_matching_category_name_with_different_category_id_returns_zero(): void
    {
        $catAmortiguacion = $this->createCategory(['name' => 'Amortiguacion']);
        $catFrenos = $this->createCategory(['name' => 'Frenos']);

        // Product in category Amortiguacion
        $prodAmort = $this->createProduct($catAmortiguacion, [
            'name' => 'Tope de Goma',
            'reference' => 'TOP-01',
            'description' => 'Pieza estandar',
        ]);

        // Product in category Frenos
        $prodFrenos = $this->createProduct($catFrenos, [
            'name' => 'Pastilla de Goma',
            'reference' => 'PAS-01',
            'description' => 'Para frenado',
        ]);

        // User searches for 'Amortiguacion' in text q, but selects category 'Frenos'
        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Amortiguacion',
            'category_id' => $catFrenos->id,
        ]));

        $response->assertOk();
        // Must return 0 because neither product matches BOTH conditions
        $response->assertViewHas('products', function (LengthAwarePaginator $products) use ($prodAmort, $prodFrenos) {
            return $products->total() === 0
                && ! $products->contains('id', $prodAmort->id)
                && ! $products->contains('id', $prodFrenos->id);
        });
    }

    /**
     * Challenge 6: Text search q combined with matching category_id returns exactly the intersection
     */
    public function test_challenge_search_combined_with_category_id_returns_exact_intersection(): void
    {
        $catA = $this->createCategory(['name' => 'Categoria A']);
        $catB = $this->createCategory(['name' => 'Categoria B']);

        // In Cat A:
        $prodA_Match1 = $this->createProduct($catA, ['name' => 'Buje Delantero Derecho', 'reference' => 'BDD-01']);
        $prodA_Match2 = $this->createProduct($catA, ['name' => 'Buje Delantero Izquierdo', 'reference' => 'BDI-01']);
        $prodA_NoMatch = $this->createProduct($catA, ['name' => 'Soporte Motor', 'reference' => 'SM-01']);

        // In Cat B:
        $prodB_MatchText = $this->createProduct($catB, ['name' => 'Buje Trasero', 'reference' => 'BT-01']);
        $prodB_NoMatch = $this->createProduct($catB, ['name' => 'Abrazadera', 'reference' => 'AB-01']);

        $response = $this->actingAs($this->admin)->get(route('productos.index', [
            'q' => 'Buje Delantero',
            'category_id' => $catA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('products', function (LengthAwarePaginator $products) use ($prodA_Match1, $prodA_Match2, $prodA_NoMatch, $prodB_MatchText, $prodB_NoMatch) {
            $ids = $products->pluck('id')->all();
            return count($ids) === 2
                && in_array($prodA_Match1->id, $ids)
                && in_array($prodA_Match2->id, $ids)
                && ! in_array($prodA_NoMatch->id, $ids)
                && ! in_array($prodB_MatchText->id, $ids)
                && ! in_array($prodB_NoMatch->id, $ids);
        });
    }

    /**
     * Challenge 7: Verify SQL query generated by Eloquent using toRawSql() to confirm boolean parentheses
     */
    public function test_challenge_eloquent_sql_query_has_correct_parentheses_and_boolean_grouping(): void
    {
        $cat = $this->createCategory(['name' => 'Categoria Test']);

        $request = Request::create('/admin/productos', 'GET', [
            'category_id' => $cat->id,
            'q' => 'Buje',
        ]);

        $query = Product::with(['category', 'images'])
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->input('category_id'));
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->string('q') . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->latest();

        $rawSql = $query->toRawSql();

        // 1. Verify category_id filter is present in WHERE clause
        $this->assertStringContainsString('"category_id" = ' . $cat->id, $rawSql);

        // 2. Verify 'where(fn ($q) => ...)' creates enclosing parentheses around the OR group
        // The pattern should be: where "category_id" = ... and ( "name" like ... or "reference" like ... or "description" like ... or exists ( ... ) )
        $this->assertMatchesRegularExpression(
            '/where\s+"category_id"\s*=\s*\d+\s+and\s+\(\s*"?name"?\s+like/i',
            $rawSql,
            'SQL WHERE clause must enclose OR text search conditions inside parentheses following AND category_id'
        );

        // 3. Verify 'latest' adds ORDER BY created_at desc
        $this->assertMatchesRegularExpression('/order by\s+"?created_at"?\s+desc/i', $rawSql);
    }
}
