#!/usr/bin/env php
<?php
/**
 * Process Swagger JSON - normalize, sort, inventory, metadata
 * Reads JSON from stdin, writes result to stdout
 * Usage: cat swagger.json | docker exec -i <container> php /var/www/scripts/process-swagger.php [command]
 * Commands:
 *   normalize  - Remove host, sort keys (default)
 *   inventory  - Output TSV: METHOD<TAB>PATH<TAB>OPERATION_ID<TAB>TAGS
 *   metadata   - Output JSON with path_count, operation_count, tag_count, definition_count, type
 *   all        - Output JSON with normalized swagger, inventory, and metadata
 */

$command = $argv[1] ?? 'normalize';

$input = file_get_contents('php://stdin');
if ($input === false || trim($input) === '') {
    fwrite(STDERR, "ERROR: No input provided\n");
    exit(1);
}

$swagger = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "ERROR: Invalid JSON input: " . json_last_error_msg() . "\n");
    exit(1);
}

function normalizeSwagger(array $swagger): array {
    // Only remove host (environment-dependent)
    unset($swagger['host']);
    // Do NOT remove schemes - keep if present
    // Do NOT modify info.* - preserve contractual data
    return $swagger;
}

function sortKeysRecursive(array $data): array {
    ksort($data);
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = sortKeysRecursive($value);
        }
    }
    return $data;
}

function generateInventory(array $swagger): array {
    $inventory = [];
    $httpMethods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch'];

    if (!isset($swagger['paths']) || !is_array($swagger['paths'])) {
        return $inventory;
    }

    foreach ($swagger['paths'] as $path => $pathItem) {
        if (!is_array($pathItem)) continue;

        foreach ($pathItem as $method => $operation) {
            if (!in_array(strtolower($method), $httpMethods, true)) {
                continue; // Skip non-HTTP method keys like 'parameters', '$ref'
            }
            if (!is_array($operation)) continue;

            $operationId = $operation['operationId'] ?? 'no-opid';
            $tags = $operation['tags'] ?? [];
            if (!is_array($tags)) $tags = [];

            $inventory[] = [
                'method' => strtoupper($method),
                'path' => $path,
                'operationId' => $operationId,
                'tags' => implode(',', $tags)
            ];
        }
    }

    // Sort deterministically: by method, then path
    usort($inventory, function($a, $b) {
        $cmp = strcmp($a['method'], $b['method']);
        if ($cmp !== 0) return $cmp;
        return strcmp($a['path'], $b['path']);
    });

    return $inventory;
}

function computeMetadata(array $swagger): array {
    $inventory = generateInventory($swagger);

    $pathCount = isset($swagger['paths']) && is_array($swagger['paths']) ? count($swagger['paths']) : 0;
    $operationCount = count($inventory);

    $tags = [];
    foreach ($inventory as $op) {
        if ($op['tags'] !== '') {
            foreach (explode(',', $op['tags']) as $tag) {
                $tags[$tag] = true;
            }
        }
    }
    $tagCount = count($tags);

    $definitionCount = 0;
    if (isset($swagger['definitions']) && is_array($swagger['definitions'])) {
        $definitionCount = count($swagger['definitions']);
    } elseif (isset($swagger['components']['schemas']) && is_array($swagger['components']['schemas'])) {
        $definitionCount = count($swagger['components']['schemas']);
    }

    $type = $swagger['swagger'] ?? $swagger['openapi'] ?? 'unknown';

    // Extract basePath from Swagger descriptor
    $basePath = $swagger['basePath'] ?? '';

    // Compute SHA-256 of the normalized swagger for drift detection
    $swagger_sha256 = hash('sha256', json_encode($swagger, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return [
        'type' => $type,
        'base_path' => $basePath,
        'path_count' => $pathCount,
        'operation_count' => $operationCount,
        'tag_count' => $tagCount,
        'definition_count' => $definitionCount,
        'swagger_sha256' => $swagger_sha256,
    ];
}

function generateTechnicalReference(array $swagger): array {
    // Read all inputs from environment
    $dolibarr_version = getenv('DOLIBARR_VERSION');
    $source_commit = getenv('SOURCE_COMMIT');
    $image = getenv('DOLIBARR_IMAGE');
    $db_engine = getenv('DB_ENGINE');
    $db_version = getenv('DB_VERSION');
    $db_image = getenv('DB_IMAGE');
    $charset = getenv('DB_CHARSET');
    $collation = getenv('DB_COLLATION');
    $table_count = (int)getenv('DB_TABLE_COUNT');
    $config_charset = getenv('CONFIG_CHARSET');
    $config_collation = getenv('CONFIG_COLLATION');
    $config_prefix = getenv('CONFIG_PREFIX');
    $company_name = getenv('COMPANY_NAME');
    $company_country_raw = getenv('COMPANY_COUNTRY_RAW');
    $country_id = getenv('COUNTRY_ID');
    $country_code = getenv('COUNTRY_CODE');
    $country_label = getenv('COUNTRY_LABEL');
    $modules_json = getenv('MODULES_JSON');
    $api_config_json = getenv('API_CONFIG_JSON');
    $swagger_type = getenv('SWAGGER_TYPE');
    $base_path = getenv('SWAGGER_BASE_PATH');
    $path_count = (int)getenv('PATH_COUNT');
    $operation_count = (int)getenv('OPERATION_COUNT');
    $tag_count = (int)getenv('TAG_COUNT');
    $definition_count = (int)getenv('DEFINITION_COUNT');
    $swagger_sha256 = getenv('SWAGGER_SHA256');

    // Build modules array
    $modules = [];
    if ($modules_json !== '') {
        $modules = array_map('trim', explode(',', $modules_json));
        // Handle JSON array format
        if (str_starts_with($modules_json, '[')) {
            $decoded = json_decode($modules_json, true);
            if (is_array($decoded)) {
                $modules = $decoded;
            }
        }
    }

    // Build API config object from JSON
    $api_config = [];
    if ($api_config_json !== '' && $api_config_json !== '{}') {
        $decoded = json_decode($api_config_json, true);
        if (is_array($decoded)) {
            $api_config = $decoded;
        }
    }

    // Compute metadata from swagger
    $metadata = computeMetadata($swagger);
    if ($path_count === 0) $path_count = $metadata['path_count'];
    if ($operation_count === 0) $operation_count = $metadata['operation_count'];
    if ($tag_count === 0) $tag_count = $metadata['tag_count'];
    if ($definition_count === 0) $definition_count = $metadata['definition_count'];
    if ($swagger_type === 'unknown') $swagger_type = $metadata['type'];
    if ($base_path === '') $base_path = $metadata['base_path'] ?? '';
    if ($swagger_sha256 === '') $swagger_sha256 = $metadata['swagger_sha256'];

    // Build country object
    $country = [
        'raw' => $company_country_raw,
    ];
    if ($country_id !== '') {
        $country['id'] = (int)$country_id;
    }
    if ($country_code !== '') {
        $country['code'] = $country_code;
    }
    if ($country_label !== '') {
        $country['label'] = $country_label;
    }

    $result = [
        "profile" => "baseline",
        "dolibarr" => [
            "version" => $dolibarr_version,
            "source_commit" => $source_commit,
            "image" => $image
        ],
        "database" => [
            "engine" => $db_engine,
            "version" => $db_version,
            "image" => $db_image,
            "observed" => [
                "character_set" => $charset,
                "collation" => $collation,
                "table_count" => $table_count
            ],
            "dolibarr_configuration" => [
                "character_set" => $config_charset,
                "collation" => $config_collation,
                "prefix" => $config_prefix
            ]
        ],
        "entity" => [
            "company_name" => $company_name,
            "country" => $country
        ],
        "modules" => [
            "enabled" => $modules
        ],
        "api_configuration" => $api_config,
        "descriptor" => [
            "type" => $swagger_type,
            "base_path" => $base_path,
            "path_count" => $path_count,
            "operation_count" => $operation_count,
            "tag_count" => $tag_count,
            "definition_count" => $definition_count,
            "swagger_sha256" => $swagger_sha256
        ]
    ];

    // Deterministic JSON output with sorted keys
    return $result;
}

switch ($command) {
    case 'normalize':
        $normalized = normalizeSwagger($swagger);
        $sorted = sortKeysRecursive($normalized);
        echo json_encode($sorted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'inventory':
        $inventory = generateInventory($swagger);
        foreach ($inventory as $row) {
            echo implode("\t", $row) . "\n";
        }
        break;

    case 'metadata':
        $metadata = computeMetadata($swagger);
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'all':
        $normalized = normalizeSwagger($swagger);
        $sorted = sortKeysRecursive($normalized);
        $inventory = generateInventory($sorted);
        $metadata = computeMetadata($sorted);
        $metadata['swagger_sha256'] = hash('sha256', json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $result = [
            'swagger' => $sorted,
            'inventory' => $inventory,
            'metadata' => $metadata
        ];
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    case 'generate-tr':
        $normalized = normalizeSwagger($swagger);
        $sorted = sortKeysRecursive($normalized);
        $result = generateTechnicalReference($sorted);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        break;

    default:
        fwrite(STDERR, "ERROR: Unknown command: $command\n");
        exit(1);
}