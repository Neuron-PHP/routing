<?php
require_once '../vendor/autoload.php';

Route::get( '/',
		function()
		{
			echo 'Home Page';
		}
	);

Route::get( '/about',
		function()
		{
			echo 'About Page';
		}
	);

Route::get( '/test/:name',
		function( $parameters )
		{
			echo "Name = $parameters[name]";
		}
	);

Route::get( '/404',
		function( $parameters )
		{
			echo "No route found for $parameters[route]";
		}
	);

$Get    = new \Neuron\Data\Filter\Get();
$Server = new \Neuron\Data\Filter\Server();

Route::dispatch(
	[
		'route' => $Get->filterScalar( 'route' ),
		'type'  => $Server->filterScalar( 'METHOD' )
	]
);
