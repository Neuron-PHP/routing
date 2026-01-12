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

Add controller paths to your `config/routing.yaml`:

```yaml
# config/routing.yaml
controller_paths:
  - path: 'src/Controllers'
    namespace: 'App\Controllers'
  - path: 'src/Admin/Controllers'
    namespace: 'App\Admin\Controllers'
```

For backward compatibility, controller paths can also be configured in `config/neuron.yaml`:

```yaml
routing:
  controller_paths:
    - path: 'src/Controllers'
      namespace: 'App\Controllers'
```

**Note:** If both files exist, `routing.yaml` takes precedence.

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

## URL Rewrites

URL rewrites provide transparent URL rewriting before route matching. Unlike HTTP redirects (301/302), rewrites are internal and invisible to the client—the browser URL stays the same while the application routes to a different path.

### Use Cases

- **Override Package Routes**: Applications can override default routes from packages (e.g., CMS homepage)
- **Legacy URL Support**: Support old URLs without creating duplicate routes
- **Clean URLs**: Map user-friendly URLs to internal route structures
- **Environment-Specific Routing**: Different rewrites for dev/staging/production

### Configuration

Add rewrites to `config/routing.yaml`:

```yaml
# config/routing.yaml
rewrites:
  '/': '/home'                    # Root goes to custom homepage
  '/index': '/home'               # Legacy URL support
  '/index.php': '/home'           # Handle old PHP URLs
  '/blog': '/posts'               # URL aliasing
  '/about-us': '/company/about'  # Clean URL to internal structure
```

### How It Works

```text
1. Client requests: http://example.com/
2. Router receives: /
3. Rewrite applied: / → /home
4. Route matching: Finds route for /home
5. Response sent to client
6. Browser still shows: http://example.com/
```

### Example: CMS Homepage Override

**Problem:** CMS defines `GET /` but you want a custom homepage.

**Solution:**
```yaml
# config/routing.yaml
rewrites:
  '/': '/custom/landing'  # Rewrite root to your controller

controller_paths:
  - path: 'app/Controllers'        # Your controllers first
    namespace: 'App\Controllers'
  - path: 'vendor/neuron-php/cms/src/Cms/Controllers'
    namespace: 'Neuron\Cms\Controllers'
```

```php
// app/Controllers/Landing.php
class Landing extends Controller
{
    #[Get('/custom/landing', name: 'landing')]
    public function index()
    {
        return $this->renderHtml(OK, [], 'custom-home');
    }
}
```

Now requests to `/` are transparently routed to your custom landing page without any HTTP redirect.

### Rewrite Rules

- **Exact Match Only**: Rewrites use exact string matching, no wildcards or regex
- **Applied Before Route Matching**: Rewrites happen before the router looks for routes
- **Original URL Preserved**: Client never sees the rewritten URL
- **Logging**: Rewrites are logged at debug level for troubleshooting

### vs. HTTP Redirects

| Feature | URL Rewrite | HTTP Redirect |
|---------|-------------|---------------|
| **Client Visible** | No | Yes |
| **HTTP Request Count** | 1 | 2 |
| **Performance** | Fast | Slower |
| **SEO Impact** | None | Can affect SEO |
| **Use Case** | Internal routing | Moved content |

## Duplicate Route Detection

The router includes strict duplicate route detection to catch configuration errors early and prevent hard-to-debug routing issues.

### What Gets Detected

#### 1. Duplicate Method + Path

```php
// ❌ ERROR: Duplicate route
#[Get('/users')]
public function index() { }

#[Get('/users')]  // Same method + path
public function list() { }
```

**Error Message:**
```text
Duplicate route detected: GET /users
  First:  App\Controllers\UserController@index
  Second: App\Controllers\UserController@list
Suggestion: Use different paths, different HTTP methods, or combine into one controller method.
```

#### 2. Duplicate Route Names

```php
// ❌ ERROR: Duplicate name
#[Get('/users', name: 'users')]
public function index() { }

#[Post('/users/create', name: 'users')]  // Same name
public function store() { }
```

**Error Message:**
```text
Duplicate route name detected: 'users'
  First:  GET /users → App\Controllers\UserController@index
  Second: POST /users/create → App\Controllers\UserController@store
Suggestion: Use different route names or remove one of the routes.
```

### What's Allowed

#### Different HTTP Methods (RESTful)

```php
// ✅ ALLOWED: Same path, different methods
#[Get('/users', name: 'users.index')]
public function index() { }

#[Post('/users', name: 'users.store')]
public function store() { }
```

This is standard RESTful routing and is fully supported.

#### Multiple Attributes on Same Method (Aliases)

```php
// ✅ ALLOWED: Multiple routes to same handler
#[Get('/user/:id')]
#[Get('/profile/:id')]
#[Get('/member/:id')]
public function show($id)
{
    // Backward compatibility or URL aliasing
}
```

### Configuration

Strict mode is **enabled by default**. To disable (not recommended):

```php
$router = Router::instance();
$router->setStrictMode(false);  // Allow duplicates (first match wins)
```

### Benefits

- **Catches Errors Early**: Fails at application boot, not during user requests
- **Clear Error Messages**: Shows both conflicting routes with file locations
- **Prevents Production Bugs**: No silent overwrites or unexpected behavior
- **Developer-Friendly**: Suggests solutions in error messages

### Why This Matters

**Without duplicate detection:**
- Same route defined twice? Second silently ignored, first wins
- Same name used twice? URL generation picks random route
- Debugging nightmare when routes mysteriously don't work

**With duplicate detection:**
- Application fails to start with clear error
- Developer fixes the conflict immediately
- Production deployments are safer

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
