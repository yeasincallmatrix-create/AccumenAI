<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditActivityLog;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * SEC-05: audit institute attribution must never come from request input.
 *
 * resolveInstituteId() strict order: TenantContext::id() when enabled,
 * then the session workspace (Workspace::id()), otherwise null. A
 * client-supplied ?institute_id / body institute_id must never land in
 * the audit record (log poisoning).
 */
class AuditActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 1001;

    private const WORKSPACE_ID = 1002;

    private const INJECTED_ID = 987654321;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        session()->forget(Workspace::SESSION_KEY);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        session()->forget(Workspace::SESSION_KEY);

        parent::tearDown();
    }

    /** Request carrying the attack in both query string and body. */
    private function poisonedRequest(): Request
    {
        return Request::create(
            '/students?institute_id='.self::INJECTED_ID,
            'POST',
            ['institute_id' => self::INJECTED_ID]
        );
    }

    private function resolveInstituteId(Request $request): ?int
    {
        $method = new \ReflectionMethod(AuditActivityLog::class, 'resolveInstituteId');
        $method->setAccessible(true);

        return $method->invoke(new AuditActivityLog(), $request);
    }

    public function test_injected_request_institute_id_is_ignored_when_tenant_context_set(): void
    {
        TenantContext::set(self::TENANT_ID);

        $resolved = $this->resolveInstituteId($this->poisonedRequest());

        $this->assertSame(self::TENANT_ID, $resolved);
        $this->assertNotSame(self::INJECTED_ID, $resolved);
    }

    public function test_injected_request_institute_id_is_ignored_in_favour_of_session_workspace(): void
    {
        TenantContext::clear();
        session([Workspace::SESSION_KEY => self::WORKSPACE_ID]);

        $resolved = $this->resolveInstituteId($this->poisonedRequest());

        $this->assertSame(self::WORKSPACE_ID, $resolved);
        $this->assertNotSame(self::INJECTED_ID, $resolved);
    }

    public function test_no_context_resolves_null_and_writes_no_audit_row(): void
    {
        TenantContext::clear();
        session()->forget(Workspace::SESSION_KEY);

        $this->assertNull($this->resolveInstituteId($this->poisonedRequest()));

        // End to end through the middleware: 403 triggers the permission_denied
        // path, but with no tenant the record is skipped (never request input).
        $middleware = new AuditActivityLog();
        $response = $middleware->handle(
            $this->poisonedRequest(),
            function () { return new Response('forbidden', 403); }
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertDatabaseMissing('accounting_audit_trails', [
            'institute_id' => self::INJECTED_ID,
        ]);
    }
}
