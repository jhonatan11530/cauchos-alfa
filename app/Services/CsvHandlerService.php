<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvHandlerService
{
    /**
     * Parsea un archivo CSV subido detectando delimitador y normalizando encabezados.
     *
     * @return array{headers: array<string>, rows: array<int, array<string, string>>}
     */
    public function parse(string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new \InvalidArgumentException('No se pudo leer el archivo especificado.');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Error al abrir el archivo CSV.');
        }

        // Leer primera línea para detectar delimitador y limpiar BOM
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return ['headers' => [], 'rows' => []];
        }

        // Remover BOM UTF-8 si existe
        if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
            $firstLine = substr($firstLine, 3);
        }

        // Detectar si usa ';' o ','
        $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

        // Rebobinar y procesar con fgetcsv
        rewind($handle);

        // Volver a omitir BOM si estaba al inicio
        $bomCheck = fread($handle, 3);
        if ($bomCheck !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);

            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map([$this, 'normalizeHeader'], $rawHeaders);

        $rows = [];
        $rowNumber = 2; // Fila 1 eran los encabezados
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Ignorar líneas vacías
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                $rowNumber++;

                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
                }
            }
            $row['_row_number'] = $rowNumber;
            $rows[] = $row;
            $rowNumber++;
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Normaliza los encabezados para indexación (minúsculas, sin tildes, alfanumérico y guiones bajos).
     */
    public function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = mb_strtolower($header, 'UTF-8');

        $unwanted = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $header = strtr($header, $unwanted);

        // Reemplazar espacios y caracteres no alfanuméricos por guiones bajos
        $header = preg_replace('/[^a-z0-9_]/', '_', $header);
        $header = preg_replace('/_+/', '_', $header);

        return trim($header, '_');
    }

    /**
     * Retorna una respuesta en streaming para descarga de CSV compatible con Excel.
     *
     * @param  array<string>  $headers
     * @param  iterable<array<mixed>>  $rows
     */
    public function streamDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel abra con tildes y caracteres correctos
            fwrite($handle, "\xEF\xBB\xBF");

            // Encabezados con separador ';'
            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                $sanitizedRow = array_map([$this, 'sanitizeCellForCsv'], $row);
                fputcsv($handle, $sanitizedRow, ';');
            }

            fclose($handle);
        }, 200, $responseHeaders);
    }

    /**
     * Sanitiza celdas para evitar inyección de fórmulas en Excel (=, +, -, @).
     */
    public function sanitizeCellForCsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;

        // Si comienza con caracteres peligrosos que Excel interpreta como fórmulas
        if (preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }
}
