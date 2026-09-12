<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSystemData;

    public function test_tiene_muchos_productos(): void
    {
        $category = $this->createCategory();
        $this->createProduct($category);
        $this->createProduct($category);

        $this->assertCount(2, $category->products);
    }

    public function test_is_active_se_castea_a_booleano(): void
    {
        $category = $this->createCategory(['is_active' => 1]);

        $this->assertTrue($category->is_active);
        $this->assertFalse($this->createCategory(['is_active' => 0])->is_active);
    }
}
