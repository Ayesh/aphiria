<?php
use Opulence\Router\Builders\RouteBuilderRegistry;
use Opulence\Router\Matchers\RouteMatcher;
use Opulence\Router\Router;
use Opulence\Router\UriTemplates\Parsers\RegexUriTemplateParser;

// Add an ordinary route
$routeBuilderRegistry = new RouteBuilderRegistry(new RegexUriTemplateParser());
$routeBuilderRegistry->map('GET', 'users/:userId')
    ->toMethod('UserController', 'getUser')
    ->withName('GetUser');

// Add a route with rules
$routeBuilderRegistry->map('GET', 'books/archives/:year(int,min(1987))[/:month(int,min(1),max(12)]')
    ->toMethod('BookController', 'getBooksFromArchives')
    ->withName('GetBooksFromArchives');

// Get the matched route
$router = new Router($routeBuilderRegistry->buildAll(), new RouteMatcher());
$matchedRoute = $router->route($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

// Use your library/framework of choice to dispatch $matchedRoute...
