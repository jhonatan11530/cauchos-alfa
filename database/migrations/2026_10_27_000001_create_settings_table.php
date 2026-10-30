<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical studio photography prompt for Gemini image generation.
     */
    public const CANONICAL_PROMPT = 'Mejora la imagen aplicando un estilo de fotografía de estudio en primer plano y de alta calidad sobre un fondo blanco puro y continuo, utilizando iluminación natural con enfoque nítido y sombras suaves en la base para asentar el objeto de forma realista. Es indispensable lograr una alineación geométrica absoluta: el producto debe quedar perfectamente recto, nivelado y con 0 grados de inclinación de extremo a extremo, respetando su orientación natural (estrictamente horizontal o vertical) y quedando totalmente paralelo a los bordes del encuadre, sin ladeos, desniveles ni caídas accidentales hacia ningún lado. Asimismo, aplica una corrección de perspectiva rigurosa para obtener una vista frontal plana y ortogonal, eliminando por completo cualquier fuga diagonal, distorsión de lente o ángulo de cámara que haga que una parte se vea más cercana o distorsionada, asegurando una simetría y estabilidad visual perfectas. Finalmente, conserva con absoluta fidelidad la forma, proporciones, materiales y texturas originales del objeto, centrándolo en la composición con un espacio blanco equilibrado alrededor, sin marcas de agua ni elementos adicionales, para entregar un acabado hiperrealista, nítido y profesional ideal para un catálogo online.';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        $initialPrompt = env('PROMPT_GEMINI') ? trim((string) env('PROMPT_GEMINI')) : self::CANONICAL_PROMPT;
        if (empty($initialPrompt)) {
            $initialPrompt = self::CANONICAL_PROMPT;
        }

        DB::table('settings')->insert([
            'key' => 'PROMPT_GEMINI',
            'value' => $initialPrompt,
            'description' => 'Prompt canónico de fotografía de estudio para generación con Gemini',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
