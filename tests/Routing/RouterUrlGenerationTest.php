<?php

namespace Tests\Routing;

use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Router URL generation methods.
 * 
 * Tests the new URL generation functionality added to the Router class
 * including route lookup by name and URL generation with parameters.
 */
class RouterUrlGenerationTest extends TestCase
{
	private Router $router;

	protected function setUp(): void
	{
		parent::setUp();

		// Create a fresh router instance
		$this->router = new Router();

		// Set up Registry for URL generation
		$registry = Registry::getInstance();
		$registry->set( 'Base.Url', 'https://test.example.com' );
	}

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testGetRouteByNameFindsExistingRoute(): void
	{
		// Arrange
		$route = $this->router->get( '/users/:id', function() { return 'test'; } )
		                       ->setName( 'user_profile' );

		// Act
		$foundRoute = $this->router->getRouteByName( 'user_profile' );

		// Assert
		$this->assertNotNull( $foundRoute );
		$this->assertEquals( '/users/:id', $foundRoute->getPath() );
		$this->assertEquals( 'user_profile', $foundRoute->getName() );
	}

	public function testGetRouteByNameReturnsNullForMissingRoute(): void
	{
		// Act
		$route = $this->router->getRouteByName( 'nonexistent_route' );

		// Assert
		$this->assertNull( $route );
	}

	public function testGetRouteByNameSearchesAllHttpMethods(): void
	{
		// Arrange
		$getRoute = $this->router->get( '/users', function() { return 'index'; } )
		                          ->setName( 'users_index' );
		$postRoute = $this->router->post( '/users', function() { return 'create'; } )
		                           ->setName( 'users_create' );
		$putRoute = $this->router->put( '/users/:id', function() { return 'update'; } )
		                          ->setName( 'users_update' );
		$deleteRoute = $this->router->delete( '/users/:id', function() { return 'destroy'; } )
		                             ->setName( 'users_destroy' );

		// Act & Assert
		$this->assertSame( $getRoute, $this->router->getRouteByName( 'users_index' ) );
		$this->assertSame( $postRoute, $this->router->getRouteByName( 'users_create' ) );
		$this->assertSame( $putRoute, $this->router->getRouteByName( 'users_update' ) );
		$this->assertSame( $deleteRoute, $this->router->getRouteByName( 'users_destroy' ) );
	}

	public function testGenerateUrlCreatesRelativeUrl(): void
	{
		// Arrange
		$this->router->get( '/users/:id', function() { return 'test'; } )
		              ->setName( 'user_profile' );

		// Act
		$url = $this->router->generateUrl( 'user_profile', ['id' => 123], false );

		// Assert
		$this->assertEquals( '/users/123', $url );
	}

	public function testGenerateUrlCreatesAbsoluteUrl(): void
	{
		// Arrange
		$this->router->get( '/users/:id', function() { return 'test'; } )
		              ->setName( 'user_profile' );

		// Act
		$url = $this->router->generateUrl( 'user_profile', ['id' => 123], true );

		// Assert
		$this->assertEquals( 'https://test.example.com/users/123', $url );
	}

	public function testGenerateUrlReturnsNullForMissingRoute(): void
	{
		// Act
		$url = $this->router->generateUrl( 'nonexistent_route', ['id' => 123] );

		// Assert
		$this->assertNull( $url );
	}

	public function testGenerateUrlWithMultipleParameters(): void
	{
		// Arrange
		$this->router->get( '/users/:user_id/posts/:post_id', function() { return 'test'; } )
		              ->setName( 'user_posts_show' );

		// Act
		$url = $this->router->generateUrl( 'user_posts_show', [
			'user_id' => 456,
			'post_id' => 789
		]);

		// Assert
		$this->assertEquals( '/users/456/posts/789', $url );
	}

	public function testGenerateUrlWithoutParameters(): void
	{
		// Arrange
		$this->router->get( '/about', function() { return 'about'; } )
		              ->setName( 'about_page' );

		// Act
		$url = $this->router->generateUrl( 'about_page' );

		// Assert
		$this->assertEquals( '/about', $url );
	}

	public function testGenerateUrlAbsoluteWithoutBaseUrl(): void
	{
		// Arrange - Temporarily override Base.Url with null
		$registry = Registry::getInstance();
		$originalBaseUrl = $registry->get( 'Base.Url' );
		$registry->set( 'Base.Url', null );

		$this->router->get( '/users/:id', function() { return 'test'; } )
		              ->setName( 'user_profile' );

		// Act
		$url = $this->router->generateUrl( 'user_profile', ['id' => 123], true );

		// Restore original value
		$registry->set( 'Base.Url', $originalBaseUrl );

		// Assert - Should return relative URL when no base URL available
		$this->assertEquals( '/users/123', $url );
	}

	public function testGetAllNamedRoutesReturnsCorrectFormat(): void
	{
		// Arrange
		$this->router->get( '/users', function() { return 'index'; } )
		              ->setName( 'users_index' );
		$this->router->post( '/users', function() { return 'create'; } );  // Unnamed route
		$this->router->put( '/users/:id', function() { return 'update'; } )
		              ->setName( 'users_update' );

		// Act
		$namedRoutes = $this->router->getAllNamedRoutes();

		// Assert
		$this->assertCount( 2, $namedRoutes ); // Only named routes
		$this->assertEquals( [
			[
				'name' => 'users_index',
				'method' => 'GET',
				'path' => '/users'
			],
			[
				'name' => 'users_update',
				'method' => 'PUT',
				'path' => '/users/:id'
			]
		], $namedRoutes );
	}

	public function testGetAllNamedRoutesReturnsEmptyArrayWhenNoNamedRoutes(): void
	{
		// Arrange
		$this->router->get( '/users', function() { return 'index'; } ); // Unnamed
		$this->router->post( '/posts', function() { return 'create'; } ); // Unnamed

		// Act
		$namedRoutes = $this->router->getAllNamedRoutes();

		// Assert
		$this->assertEmpty( $namedRoutes );
	}

	public function testGenerateUrlHandlesComplexParameterSubstitution(): void
	{
		// Arrange
		$this->router->get( '/api/v1/users/:user_id/organizations/:org_id/projects/:project_id', function() { return 'test'; } )
		              ->setName( 'api_user_org_project' );

		// Act
		$url = $this->router->generateUrl( 'api_user_org_project', [
			'user_id' => 11,
			'org_id' => 22,
			'project_id' => 33
		]);

		// Assert
		$this->assertEquals( '/api/v1/users/11/organizations/22/projects/33', $url );
	}

	public function testGenerateUrlIgnoresExtraParameters(): void
	{
		// Arrange
		$this->router->get( '/users/:id', function() { return 'test'; } )
		              ->setName( 'user_show' );

		// Act
		$url = $this->router->generateUrl( 'user_show', [
			'id' => 123,
			'extra_param' => 'ignored',
			'another_param' => 'also_ignored'
		]);

		// Assert
		$this->assertEquals( '/users/123', $url );
	}

	public function testGenerateUrlWithMissingParameters(): void
	{
		// Arrange
		$this->router->get( '/users/:id/posts/:post_id', function() { return 'test'; } )
		              ->setName( 'user_post' );

		// Act - Missing post_id parameter
		$url = $this->router->generateUrl( 'user_post', ['id' => 123] );

		// Assert - Should leave parameter placeholder in URL
		$this->assertEquals( '/users/123/posts/:post_id', $url );
	}
}