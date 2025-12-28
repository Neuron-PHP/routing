[![CI](https://github.com/neuron-php/routing/actions/workflows/ci.yml/badge.svg)](https://github.com/neuron-php/routing/actions)
[![codecov](https://codecov.io/gh/Neuron-PHP/routing/branch/develop/graph/badge.svg)](https://codecov.io/gh/Neuron-PHP/routing)
# Neuron-PHP Routing

## Overview

The neuron router is a lightweight router/dispatcher is the vein of Ruby's Sinatra
or Python's Flask. It allows for a very quick method for creating an app
using restful routes or to add them to an existing application.

* Easily map restful http requests to functions.
* Extract one or many variables from routes using masks.
* Create custom 404 responses.


## Installation

Install php composer from https://getcomposer.org/

Install the neuron routing component:

    composer require neuron-php/routing


## .htaccess
This example .htaccess file shows how to get and pass the route
to the example application.

    RewriteEngine on
    
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    
    RewriteRule ^(.*)$ index.php?route=$1 [L,QSA]

## Example App
Here is an example of a fully functional application that processes
several routes including one with a variable.

    <?php
    require_once 'vendor/autoload.php';
    
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

If present, the extra element is merged into the parameters array
before it is passed to the routes closure.

## Attribute-Based Routing

Modern PHP 8+ attribute-based routing allows you to define routes directly on controller methods using PHP attributes, providing a modern alternative to YAML configuration files.

### Basic Usage

#### Simple Route

```php
use Neuron\Routing\Attributes\Get;

class HomeController
{
    #[Get('/')]
    public function index()
    {
        return 'Hello World';
    }
}
```

#### HTTP Method Attributes

```php
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\Delete;

class UsersController
{
    #[Get('/users')]
    public function index() { }

    #[Get('/users/:id')]
    public function show(int $id) { }

    #[Post('/users')]
    public function store() { }

    #[Put('/users/:id')]
    public function update(int $id) { }

    #[Delete('/users/:id')]
    public function destroy(int $id) { }
}
```

#### Route Names and Filters

```php
#[Get('/admin/users', name: 'admin.users.index', filters: ['auth'])]
public function index() { }

#[Post('/admin/users', name: 'admin.users.store', filters: ['auth', 'csrf'])]
public function store() { }
```

#### Route Groups

Apply common settings to all routes in a controller:

```php
use Neuron\Routing\Attributes\RouteGroup;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class AdminController
{
    #[Get('/dashboard')]  // Becomes /admin/dashboard with 'auth' filter
    public function dashboard() { }

    #[Post('/users', filters: ['csrf'])]  // Becomes /admin/users with ['auth', 'csrf'] filters
    public function createUser() { }
}
```

#### Multiple Routes on Same Method

```php
#[Get('/api/v1/users')]
#[Get('/api/v2/users')]
public function getUsers()
{
    // Handle both API versions
}
```

### Configuration

#### Enable Attribute Routing in MVC Application

Add controller paths to your `config/neuron.yaml`:

```yaml
routing:
  controller_paths:
    - path: 'src/Controllers'
      namespace: 'App\Controllers'
    - path: 'src/Admin/Controllers'
      namespace: 'App\Admin\Controllers'
```

#### Hybrid Approach (YAML + Attributes)

You can use both YAML routes and attribute routes together:

- **YAML routes**: Legacy routes, package-provided routes
- **Attribute routes**: New application routes

The MVC Application will load both automatically.

### Benefits

- **Co-location**: Routes live with controller logic
- **Type Safety**: IDE autocomplete and validation
- **Refactor-Friendly**: Routes update when controllers change
- **No Sync Issues**: Can't have orphaned routes
- **Modern Standard**: Used by Symfony, Laravel, ASP.NET, Spring Boot
- **Self-Documenting**: Route definition IS the documentation

### Performance

Route scanning uses PHP Reflection, which could be slow. For production:

1. Routes are scanned once during application initialization
2. The Router caches RouteMap objects in memory
3. No reflection happens during request handling
4. Future: Add route caching to file for zero-cost production routing

### Migration from YAML

**Before (YAML):**
```yaml
# routes.yaml
home:
  method: GET
  route: /
  controller: App\Controllers\Home@index
```

**After (Attributes):**
```php
class Home
{
    #[Get('/', name: 'home')]
    public function index() { }
}
```

See `tests/unit/RouteScannerTest.php` for working examples of basic route definition, route groups with prefixes, filter composition, and multiple routes per method.

## Rate Limiting

The routing component includes a powerful rate limiting system with multiple storage backends and flexible configuration options.

### Basic Usage

```php
use Neuron\Routing\Router;
use Neuron\Routing\Filters\RateLimitFilter;
use Neuron\Routing\RateLimit\RateLimitConfig;

$router = Router::instance();

// Create rate limit configuration
$config = new RateLimitConfig([
    'enabled' => true,
    'storage' => 'redis',  // Options: redis, file, memory (testing only)
    'requests' => 100,      // Max requests per window
    'window' => 3600        // Time window in seconds (1 hour)
]);

// Create and register the filter
$rateLimitFilter = new RateLimitFilter($config);
$router->registerFilter('rate_limit', $rateLimitFilter);

// Apply globally to all routes
$router->addFilter('rate_limit');

// Or apply to specific routes
$router->get('/api/data', $handler, 'rate_limit');
```

### Configuration Options

```php
// Array configuration
$config = new RateLimitConfig([
    'enabled' => true,
    'storage' => 'redis',
    'requests' => 100,
    'window' => 3600,
    'redis_host' => '127.0.0.1',
    'redis_port' => 6379,
    'file_path' => 'cache/rate_limits'
]);
```

### Storage Backends

#### Redis (Recommended for Production)
Best for distributed systems and high-traffic applications:

```php
$config = new RateLimitConfig([
    'storage' => 'redis',
    'redis_host' => '127.0.0.1',
    'redis_port' => 6379,
    'redis_database' => 0,
    'redis_prefix' => 'rate_limit_',
    'redis_auth' => 'password',  // Optional
    'redis_persistent' => true    // Use persistent connections
]);
```

#### File Storage
Simple solution for single-server deployments:

```php
$config = new RateLimitConfig([
    'storage' => 'file',
    'file_path' => 'cache/rate_limits'  // Directory for rate limit files
]);
```

#### Memory Storage (Testing Only)
For unit tests and development:

```php
$config = new RateLimitConfig([
    'storage' => 'memory'  // Data lost when PHP process ends
]);
```

#### Whitelisting and Blacklisting

```php
$filter = new RateLimitFilter(
    $config,
    'ip',
    ['192.168.1.100', '10.0.0.1'],  // Whitelist - no limits
    ['45.67.89.10']                  // Blacklist - stricter limits (1/10th)
);
```

#### Custom Responses
Rate limit exceeded responses include appropriate headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining
- `X-RateLimit-Reset`: Unix timestamp when limit resets
- `Retry-After`: Seconds until retry allowed

The response format (JSON/HTML) is automatically determined from the Accept header.

### Example: API Rate Limiting

```php
// Different limits for different endpoints
$publicConfig = new RateLimitConfig([
    'enabled' => true,
    'storage' => 'redis',
    'requests' => 10,
    'window' => 60  // 10 requests per minute
]);

$apiConfig = new RateLimitConfig([
    'enabled' => true,
    'storage' => 'redis',
    'requests' => 1000,
    'window' => 3600  // 1000 requests per hour
]);

$router->registerFilter('public_limit', new RateLimitFilter($publicConfig));
$router->registerFilter('api_limit', new RateLimitFilter($apiConfig));

// Apply different limits
$router->get('/public/search', $searchHandler, 'public_limit');
$router->get('/api/users', $usersHandler, 'api_limit');
```

# More Information

You can read more about the Neuron components at [neuronphp.com](http://neuronphp.com)
