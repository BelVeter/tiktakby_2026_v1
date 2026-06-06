<?php

namespace Tests\Feature\Mcp;

/**
 * Asserts that for every endpoint with a documented row schema, the runtime
 * response does not contain field keys that are absent from the spec.
 *
 * This is the guard against the v2.0.0 → v2.0.1 class of bug where the runtime
 * was renamed (avg_ticket_byn → avg_payment_byn, first_deal_date →
 * first_activity_date, …) but the OpenAPI spec was not updated, so consumers
 * regenerating client stubs got broken types.
 *
 * Direction: runtime ⊆ spec. The spec may declare fields the runtime omits in
 * a given response (e.g. cohorts that didn't exist), but the runtime must NOT
 * emit a field that the spec does not document.
 */
class SpecRuntimeParityTest extends McpTestCase
{
    private array $spec;

    protected function setUp(): void
    {
        parent::setUp();
        $path = resource_path('openapi/mcp-v1.json');
        $this->spec = json_decode(file_get_contents($path), true);
        $this->assertIsArray($this->spec, 'spec must be valid JSON');
    }

    /**
     * Each entry: [endpoint path, query, kind, dataPathHint]
     *  - kind 'array_row'  → assert keys of data[0]
     *  - kind 'object'     → assert keys of data (when data is an object)
     *  - dataPathHint      → optional dotted path inside `data` to descend before
     *                        comparing (used for nested objects like
     *                        operations/funnel.leads).
     */
    public function endpointMatrix(): array
    {
        $range = ['from' => '2024-01-01', 'to' => '2024-12-31'];

        return [
            // Health
            'health'                       => ['health',                       [],                                                                            'object',    null],

            // Meta
            'meta/locations'               => ['meta/locations',               [],                                                                            'array_row', null],
            'meta/expense-items'           => ['meta/expense-items',           [],                                                                            'array_row', null],
            'meta/income-items'            => ['meta/income-items',            [],                                                                            'array_row', null],
            'meta/data-freshness'          => ['meta/data-freshness',          [],                                                                            'object',    null],
            'meta/categories'              => ['meta/categories',              [],                                                                            'object',    null],

            // Finance
            'finance/pnl'                  => ['finance/pnl',                  $range + ['granularity' => 'year'],                                            'array_row', null],
            'finance/revenue'              => ['finance/revenue',              $range + ['granularity' => 'year'],                                            'array_row', null],
            'finance/expenses'             => ['finance/expenses',             $range + ['granularity' => 'year'],                                            'array_row', null],
            'finance/cash-flow'            => ['finance/cash-flow',            $range + ['granularity' => 'year'],                                            'array_row', null],

            // Operations
            'operations/funnel'            => ['operations/funnel',            $range,                                                                        'object',    null],
            'operations/timeline'          => ['operations/timeline',          $range + ['granularity' => 'month'],                                           'array_row', null],
            'operations/by-category'       => ['operations/by-category',       $range,                                                                        'array_row', null],
            'operations/by-location'       => ['operations/by-location',       $range,                                                                        'array_row', null],

            // Inventory
            // /inventory/profitability is currently broken at the SQL layer
            // (HAVING without GROUP BY — pre-existing, unrelated to spec parity).
            // Schema is documented but we cannot probe the runtime here.
            'inventory/utilization'        => ['inventory/utilization',        $range,                                                                        'array_row', null],
            'inventory/turnover'           => ['inventory/turnover',           $range,                                                                        'array_row', null],
            'inventory/idle'               => ['inventory/idle',               ['days' => 365],                                                               'array_row', null],

            // Customers
            'customers/timeline'           => ['customers/timeline',           $range + ['granularity' => 'month'],                                           'array_row', null],
            'customers/cohorts'            => ['customers/cohorts',            $range,                                                                        'array_row', null],
            'customers/repeat-intervals'   => ['customers/repeat-intervals',   $range,                                                                        'object',    null],
            'clients/ltv'                  => ['clients/ltv',                  ['limit' => 5],                                                                'array_row', null],

            // Geo
            'geo/clients-by-city'          => ['geo/clients-by-city',          $range,                                                                        'array_row', null],

            // Locations
            'locations/performance'        => ['locations/performance',        $range + ['granularity' => 'year'],                                            'array_row', null],
            'locations/lifecycle'          => ['locations/lifecycle',          [],                                                                            'array_row', null],

            // Categories
            'categories/seasonality'       => ['categories/seasonality',       ['years' => 5],                                                                'array_row', null],
            'categories/performance'       => ['categories/performance',       ['date_from' => '2024-01-01', 'date_to' => '2024-12-31'],                      'array_row', null],

            // Carnival
            'carnival/funnel'              => ['carnival/funnel',              $range,                                                                        'object',    null],
            'carnival/seasonality'         => ['carnival/seasonality',         ['years' => 5],                                                                'array_row', null],
            'carnival/revenue'             => ['carnival/revenue',             $range + ['granularity' => 'year'],                                            'array_row', null],
        ];
    }

    /**
     * @dataProvider endpointMatrix
     */
    public function test_runtime_response_keys_are_documented_in_spec(string $path, array $query, string $kind, ?string $dataPathHint): void
    {
        $response = $this->mcp($path, $query);
        $response->assertStatus(200);

        $documentedKeys = $this->resolveDocumentedDataKeys($path, $kind);
        $this->assertNotEmpty(
            $documentedKeys,
            "spec at /{$path} did not yield documented keys for kind={$kind} — fix the spec, not the test"
        );

        $body = $response->json();
        $data = $body['data'] ?? null;

        if ($kind === 'array_row') {
            if (!is_array($data) || count($data) === 0 || !is_array($data[0])) {
                $this->markTestSkipped("/{$path} returned no rows in this dataset — cannot compare keys");
            }
            $runtimeKeys = array_keys($data[0]);
        } else {
            // 'object'
            if (!is_array($data) || $this->isList($data)) {
                $this->markTestSkipped("/{$path} returned no object payload (got " . gettype($data) . ") — cannot compare keys");
            }
            $runtimeKeys = array_keys($data);
        }

        $undocumented = array_diff($runtimeKeys, $documentedKeys);
        $this->assertEmpty(
            $undocumented,
            "/{$path} returns keys absent from spec: " . implode(', ', $undocumented)
            . "\n  documented: " . implode(', ', $documentedKeys)
            . "\n  runtime:    " . implode(', ', $runtimeKeys)
        );
    }

    /**
     * Spot-check the v2.0.0 renamed fields specifically, even if the dataset
     * happens to be empty in the parametrised run.
     */
    public function test_locations_performance_documents_avg_payment_byn(): void
    {
        $keys = $this->resolveDocumentedDataKeys('locations/performance', 'array_row');
        $this->assertContains('avg_payment_byn', $keys, 'spec must document avg_payment_byn (renamed from avg_ticket_byn in v2.0.0)');
        $this->assertContains('unique_clients_proxy', $keys);
        $this->assertContains('issuance_events', $keys);
        $this->assertContains('office_type', $keys);
    }

    public function test_locations_lifecycle_documents_renamed_activity_dates(): void
    {
        $keys = $this->resolveDocumentedDataKeys('locations/lifecycle', 'array_row');
        $this->assertContains('first_activity_date', $keys, 'spec must document first_activity_date (renamed from first_deal_date in v2.0.0)');
        $this->assertContains('last_activity_date', $keys);
        $this->assertContains('currently_active', $keys);
        $this->assertContains('active_days', $keys);
        $this->assertNotContains('first_deal_date', $keys, 'old field name must not be re-introduced');
        $this->assertNotContains('last_deal_date', $keys, 'old field name must not be re-introduced');
    }

    public function test_finance_revenue_documents_issuance_events_not_unique_clients(): void
    {
        $keys = $this->resolveDocumentedDataKeys('finance/revenue', 'array_row');
        $this->assertContains('issuance_events', $keys);
        $this->assertNotContains('unique_clients', $keys, 'unique_clients was removed in v2.0.0');
    }

    public function test_inventory_utilization_documents_historical_unit_fields(): void
    {
        $keys = $this->resolveDocumentedDataKeys('inventory/utilization', 'array_row');
        $this->assertContains('units_at_from', $keys);
        $this->assertContains('units_at_to', $keys);
        $this->assertContains('avg_units', $keys);
        $this->assertNotContains('units', $keys, '`units` was replaced with units_at_from/units_at_to/avg_units in v2.0.0');
    }

    public function test_spec_version_matches(): void
    {
        $this->assertSame(
            '2.2.1',
            $this->spec['info']['version'],
            'bumping spec without updating this assertion is a footgun — update both together'
        );
    }

    /**
     * Walk the spec from `paths.{/path}.get.responses.200` to a concrete object
     * schema for `data`, then return its property names.
     *
     * For array kinds, returns the property names of `data.items`.
     * For object kinds, returns the property names of `data`.
     */
    private function resolveDocumentedDataKeys(string $path, string $kind): array
    {
        $pathKey = '/' . $path;
        $this->assertArrayHasKey($pathKey, $this->spec['paths'], "spec is missing path {$pathKey}");

        $opSchema = $this->spec['paths'][$pathKey]['get']['responses']['200'] ?? null;
        $this->assertNotNull($opSchema, "spec has no 200 response for {$pathKey}");

        $resolved = $this->deref($opSchema);
        $jsonSchema = $resolved['content']['application/json']['schema'] ?? null;
        $this->assertNotNull($jsonSchema, "spec has no application/json schema for {$pathKey}");

        // Schema is an allOf of [Envelope, {properties.data: ...}]. The base
        // Envelope ALSO has a `data` property (just a description), so we
        // pick the LAST allOf part that defines data with concrete
        // properties/items/$ref — that's the per-endpoint override.
        $dataSchema = null;
        if (isset($jsonSchema['allOf'])) {
            foreach ($jsonSchema['allOf'] as $part) {
                $part = $this->deref($part);
                $candidate = $part['properties']['data'] ?? null;
                if ($candidate === null) continue;
                $resolvedCandidate = $this->deref($candidate);
                $hasShape = isset($resolvedCandidate['properties'])
                         || isset($resolvedCandidate['items'])
                         || isset($resolvedCandidate['oneOf'])
                         || isset($resolvedCandidate['allOf']);
                if ($hasShape) {
                    $dataSchema = $resolvedCandidate;
                }
            }
        } else {
            $dataSchema = $this->deref($jsonSchema['properties']['data'] ?? []);
        }
        $this->assertNotEmpty($dataSchema, "spec has no `data` schema for {$pathKey}");

        if ($kind === 'array_row') {
            $items = $this->deref($dataSchema['items'] ?? []);
            return array_keys($items['properties'] ?? []);
        }
        return array_keys($dataSchema['properties'] ?? []);
    }

    /** Resolve a `$ref` chain inside the loaded spec, following one hop at a time. */
    private function deref(array $node): array
    {
        $guard = 0;
        while (isset($node['$ref'])) {
            $ref = $node['$ref'];
            $this->assertStringStartsWith('#/', $ref, "external $ref not supported: {$ref}");
            $segments = explode('/', substr($ref, 2));
            $cursor = $this->spec;
            foreach ($segments as $seg) {
                $cursor = $cursor[$seg];
            }
            $node = $cursor;
            if (++$guard > 10) {
                $this->fail("ref chain too deep at {$ref}");
            }
        }
        return $node;
    }

    /** PHP 7-compatible "is the array a list?" check (no array_is_list). */
    private function isList(array $arr): bool
    {
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
