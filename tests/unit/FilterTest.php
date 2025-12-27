<?php

use PHPUnit\Framework\TestCase;
use Neuron\Routing;

class FilterTest extends PHPUnit\Framework\TestCase
{
	public $Router;

	protected function setUp() : void
	{
		$this->Router = new Routing\Router();
	}

	public function testRoutePreFilter()
	{
		$Filter = false;
		$Name = '';

		$this->Router->registerFilter(
			'PreFilter',
			new Routing\Filter(
				function( Routing\RouteMap $Route ) use ( &$Filter, &$Name )
				{
					$Filter = true;
					$Name = $Route->Path;
				}
			)
		);

		$this->Router->get(
			'/test',
			function(){},
			'PreFilter'
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test'
		);

		$this->assertNotEmpty( $Route );

		$this->Router->dispatch( $Route );

		$this->assertTrue( $Filter );
		$this->assertEquals( '/test', $Name );
	}

	public function testRouteNoFilter()
	{
		$Filter = false;
		$Name = '';

		$this>$this->expectException(\Exception::class);
		$this->Router->getFilter( 'NoFilter' );
	}

	public function testRoutePostFilter()
	{
		$Filter = false;

		$this->Router->registerFilter(
			'PostFilter',
			new Routing\Filter(
				null,
				function() use ( &$Filter ) { $Filter = true; }
			)
		);

		$this->Router->get(
			'/test',
			function(){},
			'PostFilter'
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test'
		);

		$this->Router->dispatch( $Route );

		$this->assertTrue( $Filter );
	}

	public function testGlobalPreFilter()
	{
		$Filter = false;

		$this->Router->registerFilter(
			'PreFilter',
			new Routing\Filter(
				function() use ( &$Filter ) { $Filter = true; }
			)
		);

		$this->Router->addFilter( 'PreFilter' );
		$this->Router->get(
			'/test',
			function(){}
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'test'
		);

		$this->assertNotEmpty( $Route );

		$this->Router->dispatch( $Route );

		$this->assertTrue( $Filter );
	}

	public function testGlobalPostFilter()
	{
		$Filter = false;

		$this->Router->registerFilter(
			'PostFilter',
			new Routing\Filter(
				null,
				function() use ( &$Filter ) { $Filter = true; }
			)
		);

		$this->Router->addFilter( 'PostFilter' );

		$this->Router->get(
			'/test',
			function(){}
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'test'
		);

		$this->Router->dispatch( $Route );

		$this->assertTrue( $Filter );
	}

	public function testMultipleFilters()
	{
		$executionOrder = [];

		$this->Router->registerFilter(
			'FilterOne',
			new Routing\Filter(
				function() use ( &$executionOrder ) { $executionOrder[] = 'one-pre'; }
			)
		);

		$this->Router->registerFilter(
			'FilterTwo',
			new Routing\Filter(
				function() use ( &$executionOrder ) { $executionOrder[] = 'two-pre'; }
			)
		);

		$this->Router->get(
			'/test',
			function() use ( &$executionOrder ) { $executionOrder[] = 'route'; },
			[ 'FilterOne', 'FilterTwo' ]
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test'
		);

		$this->Router->dispatch( $Route );

		$this->assertEquals( [ 'one-pre', 'two-pre', 'route' ], $executionOrder );
	}

	public function testMultipleFiltersWithPost()
	{
		$executionOrder = [];

		$this->Router->registerFilter(
			'FilterOne',
			new Routing\Filter(
				function() use ( &$executionOrder ) { $executionOrder[] = 'one-pre'; },
				function() use ( &$executionOrder ) { $executionOrder[] = 'one-post'; }
			)
		);

		$this->Router->registerFilter(
			'FilterTwo',
			new Routing\Filter(
				function() use ( &$executionOrder ) { $executionOrder[] = 'two-pre'; },
				function() use ( &$executionOrder ) { $executionOrder[] = 'two-post'; }
			)
		);

		$this->Router->get(
			'/test',
			function() use ( &$executionOrder ) { $executionOrder[] = 'route'; return 'result'; },
			[ 'FilterOne', 'FilterTwo' ]
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test'
		);

		$this->Router->dispatch( $Route );

		// Pre-filters execute in order, route executes, post-filters execute in reverse (LIFO)
		$this->assertEquals(
			[ 'one-pre', 'two-pre', 'route', 'two-post', 'one-post' ],
			$executionOrder
		);
	}

	public function testBackwardCompatibilityWithStringFilter()
	{
		$Filter = false;

		$this->Router->registerFilter(
			'TestFilter',
			new Routing\Filter(
				function() use ( &$Filter ) { $Filter = true; }
			)
		);

		// Test that string filter still works (backward compatibility)
		$this->Router->get(
			'/test',
			function(){},
			'TestFilter'
		);

		$Route = $this->Router->getRoute(
			Routing\RequestMethod::GET,
			'/test'
		);

		$this->Router->dispatch( $Route );

		$this->assertTrue( $Filter );
	}
}
