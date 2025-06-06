[![Build Status](https://app.travis-ci.com/Neuron-PHP/routing.svg?token=F8zCwpT7x7Res7J2N4vF&branch=master)](https://app.travis-ci.com/Neuron-PHP/routing)
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

# More Information

You can read more about the Neuron components at [neuronphp.com](http://neuronphp.com)
