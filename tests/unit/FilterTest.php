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
}
