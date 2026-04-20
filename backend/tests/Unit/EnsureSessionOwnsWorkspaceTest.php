<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureSessionOwnsWorkspace;
use App\Models\ColumnConversation;
use App\Models\Generation;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\Store;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnsureSessionOwnsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private EnsureSessionOwnsWorkspace $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureSessionOwnsWorkspace();
    }

    private function createRequestWithRoute(string $uri, string $method, array $params): Request
    {
        $request = Request::create($uri, $method);

        $route = new Route([$method], $uri, []);
        $route->bind($request);
        foreach ($params as $key => $value) {
            $route->setParameter($key, $value);
        }

        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        return $request;
    }

    private function setSessionOnRequest(Request $request, string $sessionId): void
    {
        $session = Mockery::mock(Store::class);
        $session->shouldReceive('getId')->andReturn($sessionId);
        $session->shouldReceive('getName')->andReturn('laravel_session');
        $request->setLaravelSession($session);
    }

    #[Test]
    public function allows_access_when_session_owns_workspace(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);

        $request = $this->createRequestWithRoute(
            "/api/workspaces/{$workspace->id}", 'GET',
            ['workspaceId' => $workspace->id],
        );
        $this->setSessionOnRequest($request, 'owner-session-123');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function denies_access_when_session_does_not_own_workspace(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);

        $request = $this->createRequestWithRoute(
            "/api/workspaces/{$workspace->id}", 'GET',
            ['workspaceId' => $workspace->id],
        );
        $this->setSessionOnRequest($request, 'other-session-456');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function denies_access_when_session_does_not_own_column(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);
        $column = ColumnConversation::factory()->create(['workspace_id' => $workspace->id]);

        $request = $this->createRequestWithRoute(
            "/api/columns/{$column->id}/messages", 'POST',
            ['columnId' => $column->id],
        );
        $this->setSessionOnRequest($request, 'other-session-456');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function denies_access_when_session_does_not_own_generation(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);
        $column = ColumnConversation::factory()->create(['workspace_id' => $workspace->id]);
        $generation = Generation::factory()->create(['column_id' => $column->id]);

        $request = $this->createRequestWithRoute(
            "/api/generations/{$generation->id}/cancel", 'POST',
            ['generationId' => $generation->id],
        );
        $this->setSessionOnRequest($request, 'other-session-456');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function allows_access_when_session_owns_column(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);
        $column = ColumnConversation::factory()->create(['workspace_id' => $workspace->id]);

        $request = $this->createRequestWithRoute(
            "/api/columns/{$column->id}/messages", 'POST',
            ['columnId' => $column->id],
        );
        $this->setSessionOnRequest($request, 'owner-session-123');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function allows_access_when_session_owns_generation(): void
    {
        $workspace = Workspace::factory()->create(['session_id' => 'owner-session-123']);
        $column = ColumnConversation::factory()->create(['workspace_id' => $workspace->id]);
        $generation = Generation::factory()->create(['column_id' => $column->id]);

        $request = $this->createRequestWithRoute(
            "/api/generations/{$generation->id}/stream", 'GET',
            ['generationId' => $generation->id],
        );
        $this->setSessionOnRequest($request, 'owner-session-123');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function returns_404_for_nonexistent_workspace(): void
    {
        $request = $this->createRequestWithRoute(
            '/api/workspaces/00000000-0000-0000-0000-000000000000', 'GET',
            ['workspaceId' => '00000000-0000-0000-0000-000000000000'],
        );
        $this->setSessionOnRequest($request, 'any-session');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(404, $response->getStatusCode());
    }

    #[Test]
    public function passes_through_when_no_resource_parameter(): void
    {
        $request = Request::create('/api/config', 'GET');
        $request->setRouteResolver(function () {
            $route = new Route(['GET'], '/api/config', []);
            $request = Request::create('/api/config', 'GET');
            $route->bind($request);

            return $route;
        });
        $this->setSessionOnRequest($request, 'any-session');

        $response = $this->middleware->handle($request, fn () => new \Symfony\Component\HttpFoundation\Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
