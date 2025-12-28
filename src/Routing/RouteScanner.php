<?php

namespace Neuron\Routing;

use Neuron\Routing\Attributes\Route;
use Neuron\Routing\Attributes\RouteGroup;
use ReflectionClass;
use ReflectionMethod;

/**
 * Route scanner that discovers routes defined via PHP attributes.
 *
 * This class scans controller classes for route attributes and converts them
 * into RouteDefinition objects that can be registered with the Router.
 *
 * @package Neuron\Routing
 */
class RouteScanner
{
	private array $_scannedClasses = [];

	/**
	 * Scan a controller class for route attributes
	 *
	 * @param string $className Fully qualified class name
	 * @return array Array of RouteDefinition objects
	 * @throws \ReflectionException
	 */
	public function scanClass( string $className ): array
	{
		if( !class_exists( $className ) )
		{
			return [];
		}

		// Avoid scanning the same class twice
		if( isset( $this->_scannedClasses[ $className ] ) )
		{
			return $this->_scannedClasses[ $className ];
		}

		$reflection = new ReflectionClass( $className );
		$routes = [];

		// Get class-level RouteGroup attribute if present
		$groupAttributes = $reflection->getAttributes( RouteGroup::class );
		$routeGroup = null;

		if( count( $groupAttributes ) > 0 )
		{
			$routeGroup = $groupAttributes[0]->newInstance();
		}

		// Scan all public methods for route attributes
		foreach( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method )
		{
			$methodRoutes = $this->scanMethod( $method, $className, $routeGroup );
			$routes = array_merge( $routes, $methodRoutes );
		}

		$this->_scannedClasses[ $className ] = $routes;

		return $routes;
	}

	/**
	 * Scan multiple controller classes
	 *
	 * @param array $classNames Array of fully qualified class names
	 * @return array Array of RouteDefinition objects
	 * @throws \ReflectionException
	 */
	public function scanClasses( array $classNames ): array
	{
		$allRoutes = [];

		foreach( $classNames as $className )
		{
			$routes = $this->scanClass( $className );
			$allRoutes = array_merge( $allRoutes, $routes );
		}

		return $allRoutes;
	}

	/**
	 * Scan a directory for controller classes and extract routes
	 *
	 * @param string $directory Directory to scan
	 * @param string $namespace Base namespace for controllers
	 * @return array Array of RouteDefinition objects
	 * @throws \ReflectionException
	 */
	public function scanDirectory( string $directory, string $namespace ): array
	{
		if( !is_dir( $directory ) )
		{
			return [];
		}

		$classes = $this->findClassesInDirectory( $directory, $namespace );
		return $this->scanClasses( $classes );
	}

	/**
	 * Scan a method for route attributes
	 *
	 * @param ReflectionMethod $method Method to scan
	 * @param string $className Controller class name
	 * @param RouteGroup|null $routeGroup Optional route group from class
	 * @return array Array of RouteDefinition objects
	 */
	protected function scanMethod(
		ReflectionMethod $method,
		string $className,
		?RouteGroup $routeGroup
	): array
	{
		$routes = [];

		// Get all Route attributes (including subclasses like Get, Post, etc.)
		$routeAttributes = $method->getAttributes( Route::class, \ReflectionAttribute::IS_INSTANCEOF );

		foreach( $routeAttributes as $attribute )
		{
			/** @var Route $routeAttr */
			$routeAttr = $attribute->newInstance();

			// Apply route group settings if present
			$path = $routeAttr->getPath();
			$filters = $routeAttr->getFilters();

			if( $routeGroup )
			{
				$path = $routeGroup->applyPrefix( $path );
				$filters = $routeGroup->mergeFilters( $filters );
			}

			$routes[] = new RouteDefinition(
				path: $path,
				method: $routeAttr->getMethod(),
				controller: $className,
				action: $method->getName(),
				name: $routeAttr->getName(),
				filters: $filters
			);
		}

		return $routes;
	}

	/**
	 * Find all PHP classes in a directory
	 *
	 * @param string $directory Directory to scan
	 * @param string $namespace Base namespace
	 * @return array Array of fully qualified class names
	 */
	protected function findClassesInDirectory( string $directory, string $namespace ): array
	{
		$classes = [];
		$directory = rtrim( $directory, '/\\' );
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory )
		);

		foreach( $iterator as $file )
		{
			if( $file->isFile() && $file->getExtension() === 'php' )
			{
				$relativePath = str_replace(
					$directory . DIRECTORY_SEPARATOR,
					'',
					$file->getPathname()
				);
				$relativePath = str_replace( '.php', '', $relativePath );
				$className = $namespace . '\\' . str_replace( DIRECTORY_SEPARATOR, '\\', $relativePath );

				if( class_exists( $className ) )
				{
					$classes[] = $className;
				}
			}
		}

		return $classes;
	}

	/**
	 * Clear the scanned classes cache
	 */
	public function clearCache(): void
	{
		$this->_scannedClasses = [];
	}
}
