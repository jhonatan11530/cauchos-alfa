<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $categories = Category::where('is_active', true)->get();

        $content = view('site.sitemap', compact('categories'))->render();

        return response($content)->header('Content-Type', 'text/xml');
    }
}
