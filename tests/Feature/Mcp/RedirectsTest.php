<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * Tests for the Redirects API endpoints:
 * GET /redirects
 * POST /redirects
 * PATCH /redirects/{id}
 * DELETE /redirects/{id}
 * POST /redirects/bulk
 */
class RedirectsTest extends McpTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear redirects for clean tests
        DB::table('redirects')->delete();
    }

    // ─── GET /redirects ───────────────────────────────────────────────────────

    public function test_get_redirects_returns_envelope_and_data(): void
    {
        $this->insertRedirect('/old1', '/new1', 301, 1, 0);
        
        $r = $this->mcp('redirects');
        $r->assertStatus(200);
        
        $r->assertJsonStructure([
            'query',
            'data' => [
                '*' => [
                    'id', 'source_url', 'target_url', 'status_code',
                    'is_regex', 'is_active', 'hit_count', 'last_hit_at',
                    'comment'
                ]
            ],
            'meta' => ['total_rows', 'page', 'per_page']
        ]);
    }

    public function test_get_redirects_supports_pagination(): void
    {
        $this->insertRedirect('/o1', '/n1');
        $this->insertRedirect('/o2', '/n2');
        $this->insertRedirect('/o3', '/n3');

        $r = $this->mcp('redirects', ['page' => 2, 'per_page' => 2]);
        $data = $r->json('data');
        
        $this->assertCount(1, $data);
        $this->assertSame(3, $r->json('meta.total_rows'));
    }

    public function test_get_redirects_search_filter(): void
    {
        $this->insertRedirect('/apple-old', '/apple-new');
        $this->insertRedirect('/banana-old', '/banana-new');

        $r = $this->mcp('redirects', ['search' => 'apple']);
        $data = $r->json('data');
        
        $this->assertCount(1, $data);
        $this->assertSame('/apple-old', $data[0]['source_url']);
    }

    public function test_get_redirects_active_filter(): void
    {
        $this->insertRedirect('/active', '/n1', 301, 1, 0);
        $this->insertRedirect('/inactive', '/n2', 301, 0, 0);

        $r = $this->mcp('redirects', ['is_active' => 1]);
        $data = $r->json('data');
        
        $this->assertCount(1, $data);
        $this->assertSame('/active', $data[0]['source_url']);
    }

    // ─── POST /redirects ──────────────────────────────────────────────────────

    public function test_create_redirect(): void
    {
        $r = $this->postMcp('redirects', [
            'source_url' => '/create-old',
            'target_url' => '/create-new',
            'status_code' => 302,
            'is_regex' => true,
            'is_active' => false,
            'comment' => 'Test comment'
        ]);

        $r->assertStatus(201);
        $data = $r->json('data');
        
        $this->assertSame('/create-old', $data['source_url']);
        $this->assertSame('/create-new', $data['target_url']);
        $this->assertSame(302, $data['status_code']);
        $this->assertTrue($data['is_regex']);
        $this->assertFalse($data['is_active']);
        $this->assertSame('Test comment', $data['comment']);

        $this->assertDatabaseHas('redirects', ['source_url' => '/create-old']);
    }

    public function test_create_redirect_rejects_duplicate_source_url(): void
    {
        $this->insertRedirect('/duplicate', '/target');

        $r = $this->postMcp('redirects', [
            'source_url' => '/duplicate',
            'target_url' => '/another-target',
            'status_code' => 301
        ]);

        $r->assertStatus(422);
    }

    public function test_create_redirect_validates_status_code(): void
    {
        $r = $this->postMcp('redirects', [
            'source_url' => '/invalid-status',
            'target_url' => '/target',
            'status_code' => 404 // Only 301 and 302 allowed usually
        ]);

        $r->assertStatus(422);
    }

    // ─── PATCH /redirects/{id} ────────────────────────────────────────────────

    public function test_update_redirect(): void
    {
        $id = $this->insertRedirect('/to-update', '/target');

        $r = $this->patchJson('/api/mcp/v1/redirects/' . $id, [
            'target_url' => '/new-target',
            'is_active' => false
        ], ['Authorization' => 'Bearer ' . config('mcp.api_token')]);

        $r->assertStatus(200);
        $this->assertSame('/new-target', $r->json('data.target_url'));
        $this->assertFalse($r->json('data.is_active'));
        
        $this->assertDatabaseHas('redirects', [
            'id' => $id,
            'target_url' => '/new-target',
            'is_active' => 0
        ]);
    }

    public function test_update_redirect_404_if_not_found(): void
    {
        $r = $this->patchJson('/api/mcp/v1/redirects/999999', [
            'target_url' => '/new-target'
        ], ['Authorization' => 'Bearer ' . config('mcp.api_token')]);

        $r->assertStatus(404);
    }

    public function test_update_rejects_duplicate_source_url(): void
    {
        $id1 = $this->insertRedirect('/first', '/target');
        $id2 = $this->insertRedirect('/second', '/target');

        $r = $this->patchJson('/api/mcp/v1/redirects/' . $id1, [
            'source_url' => '/second'
        ], ['Authorization' => 'Bearer ' . config('mcp.api_token')]);

        $r->assertStatus(422);
    }

    // ─── DELETE /redirects/{id} ───────────────────────────────────────────────

    public function test_delete_redirect(): void
    {
        $id = $this->insertRedirect('/to-delete', '/target');

        $r = $this->deleteJson('/api/mcp/v1/redirects/' . $id, [], [
            'Authorization' => 'Bearer ' . config('mcp.api_token')
        ]);

        $r->assertStatus(200);
        $this->assertDatabaseMissing('redirects', ['id' => $id]);
    }

    public function test_delete_redirect_404_if_not_found(): void
    {
        $r = $this->deleteJson('/api/mcp/v1/redirects/999999', [], [
            'Authorization' => 'Bearer ' . config('mcp.api_token')
        ]);

        $r->assertStatus(404);
    }

    // ─── POST /redirects/bulk ─────────────────────────────────────────────────

    public function test_bulk_upsert_redirects(): void
    {
        // One existing row to be updated
        $this->insertRedirect('/existing', '/old-target', 301, 1, 0, 'old comment');

        $r = $this->postMcp('redirects/bulk', [
            'redirects' => [
                [
                    'source_url' => '/existing',
                    'target_url' => '/new-target',
                    'status_code' => 302,
                    'is_regex' => false,
                    'is_active' => true,
                    'comment' => 'updated comment'
                ],
                [
                    'source_url' => '/brand-new',
                    'target_url' => '/brand-new-target',
                    'status_code' => 301
                ]
            ]
        ]);

        $r->assertStatus(200);
        
        $this->assertDatabaseHas('redirects', [
            'source_url' => '/existing',
            'target_url' => '/new-target',
            'status_code' => 302,
            'is_regex' => 0,
            'is_active' => 1,
            'comment' => 'updated comment'
        ]);

        $this->assertDatabaseHas('redirects', [
            'source_url' => '/brand-new',
            'target_url' => '/brand-new-target',
            'status_code' => 301
        ]);
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_endpoints_require_token(): void
    {
        $this->getJson('/api/mcp/v1/redirects')->assertStatus(401);
        $this->postJson('/api/mcp/v1/redirects')->assertStatus(401);
        $this->postJson('/api/mcp/v1/redirects/bulk')->assertStatus(401);
        $this->patchJson('/api/mcp/v1/redirects/1')->assertStatus(401);
        $this->deleteJson('/api/mcp/v1/redirects/1')->assertStatus(401);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function insertRedirect(
        string $source, 
        string $target, 
        int $statusCode = 301, 
        int $isActive = 1, 
        int $isRegex = 0,
        string $comment = null
    ): int {
        return DB::table('redirects')->insertGetId([
            'source_url' => $source,
            'target_url' => $target,
            'status_code' => $statusCode,
            'is_active' => $isActive,
            'is_regex' => $isRegex,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
