<?php

namespace Tests\Feature;

use App\Models\Product;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSystemData;
use Tests\TestCase;

class SiteSeoAndFeedTest extends TestCase
{
    use CreatesSystemData;
    use RefreshDatabase;

    public function test_home_page_has_single_title_and_single_h1(): void
    {
        $response = $this->get(route('site.home'));
        $response->assertOk();

        $content = $response->getContent();

        // Load into DOMDocument
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $titles = $xpath->query('//title');
        $this->assertSame(1, $titles->length, 'Home page must have exactly 1 <title> tag.');

        $h1s = $xpath->query('//h1');
        $this->assertSame(1, $h1s->length, 'Home page must have exactly 1 <h1> heading.');
    }

    public function test_product_jsonld_escapes_quotes_and_newlines_safely(): void
    {
        $category = $this->createCategory(['name' => 'Llantas MTB']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Llanta 26" x 2.10" Chaoyang "Pro"',
            'reference' => 'REF-"ABC-123"',
            'description' => "Descripción con \"comillas dobles\",\n saltos de línea y 'comillas simples'.",
            'price' => 85000,
            'availability' => 'disponible',
            'is_active' => true,
        ]);

        $response = $this->get(route('site.product', [
            'slug' => 'llanta-26-x-210-chaoyang-pro',
            'id' => $product->id,
        ]));

        $response->assertOk();
        $content = $response->getContent();

        // Extract JSON-LD script block
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Product page must contain a JSON-LD script block.');

        $jsonString = trim($matches[1]);
        $data = json_decode($jsonString, true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD must be syntactically valid JSON: '.json_last_error_msg());
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Llanta 26" x 2.10" Chaoyang "Pro"', $data['name']);
        $this->assertSame('REF-"ABC-123"', $data['sku']);
        $this->assertStringContainsString('Descripción con "comillas dobles"', $data['description']);
        $this->assertSame('0', $data['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function test_product_jsonld_prevents_script_tag_breakout(): void
    {
        $category = $this->createCategory(['name' => 'Accesorios']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Llanta </script><script>alert(1)</script>',
            'reference' => 'XSS-TEST-001',
            'description' => 'Descripción segura </script>',
            'price' => 120000,
            'availability' => 'disponible',
            'is_active' => true,
        ]);

        $response = $this->get(route('site.product', [
            'slug' => 'llanta',
            'id' => $product->id,
        ]));

        $response->assertOk();
        $content = $response->getContent();

        // Extract JSON-LD script block from the rendered HTML
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Product page must contain a valid JSON-LD script block.');

        $jsonString = trim($matches[1]);
        $data = json_decode($jsonString, true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD must parse cleanly without breakout: '.json_last_error_msg());
        $this->assertIsArray($data);
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Llanta </script><script>alert(1)</script>', $data['name']);
        // Verify raw HTML output does NOT contain literal unescaped closing script tag inside the JSON string
        $this->assertStringNotContainsString('</script><script>', $jsonString, 'JSON-LD must escape tag delimiters to prevent XSS breakout.');
    }

    public function test_sitemap_returns_valid_xml_headers_and_trimmed_locs(): void
    {
        $category = $this->createCategory(['name' => 'Llantas Tubeless']);

        $response = $this->get('/sitemap.xml');
        $response->assertOk();

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/xml', $contentType);
        $this->assertStringContainsString('UTF-8', $contentType);

        $content = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($content));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertNotFalse($xml, 'Sitemap XML must be well-formed: '.json_encode($errors));

        // Check loc elements have no surrounding whitespace
        foreach ($xml->url as $url) {
            $loc = (string) $url->loc;
            $this->assertSame(trim($loc), $loc, "Sitemap <loc> contains leading/trailing whitespace: '{$loc}'");
            $this->assertStringNotContainsString("\n", $loc, "Sitemap <loc> contains newlines: '{$loc}'");
        }
    }

    public function test_feed_returns_valid_xml_headers_and_canonical_product_links(): void
    {
        $category = $this->createCategory(['name' => 'Neumáticos']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Neumático 700x25c Válvula Presta',
            'reference' => 'NEU-700-25',
            'description' => 'Neumático resistente para ruta.',
            'availability' => 'disponible',
            'is_active' => true,
        ]);

        $response = $this->get('/feed.xml');
        $response->assertOk();

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/xml', $contentType);
        $this->assertStringContainsString('UTF-8', $contentType);

        $content = $response->getContent();
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', trim($content));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertNotFalse($xml, 'Feed XML must be well-formed: '.json_encode($errors));

        $item = $xml->channel->item[0];
        $this->assertNotNull($item);
        $this->assertSame('Neumático 700x25c Válvula Presta', (string) $item->title);

        $canonicalUrl = route('site.product', ['slug' => 'neumatico-700x25c-valvula-presta', 'id' => $product->id]);
        $this->assertSame($canonicalUrl, (string) $item->link);

        $gNamespaces = $item->children('http://base.google.com/ns/1.0');
        $this->assertSame($canonicalUrl, (string) $gNamespaces->link);
        $this->assertSame('in stock', (string) $gNamespaces->availability);

        // Verify price formatting when product has a price
        $product->price = 25000.5;
        $viewContent = view('site.feed', ['products' => collect([$product])])->render();
        $customXml = simplexml_load_string($viewContent);
        $customItemG = $customXml->channel->item[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('25000.50 COP', (string) $customItemG->price);
    }
}
