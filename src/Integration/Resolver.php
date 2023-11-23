<?php

namespace Tuples\Integration;

use Tuples\Container\Container;
use Tuples\Exception\HttpNotFoundException;
use Tuples\Http\Request;
use Tuples\Http\Response;
use Tuples\Http\Route;

/**
 * Class to resolve the Request LifeCycle
 */
class Resolver
{
    protected Container $container;

    public function __construct(protected Request $request, protected Response $response)
    {
        $this->container = Container::instance();
    }

    public function execute(Route|false $route, array $params = []): Response
    {
        try {
            if (!$route) {
                throw new HttpNotFoundException;
            }

            $this->request->setRoute($route);
            $this->request->setRouteParams($params);

            list($controller, $method) = $route->getAction();

            // Register controller on Container as callabale (instance every time it is called)
            $this->container->callable($controller, $controller);

            /************************************************************
            | Define the chain, route action + all middlewares
             ************************************************************/

            // Convert the route action to a \Closure; this will be the last command of the chain
            $next = function () use ($controller, $method, $params) {

                // Execute this on the last command of the chain only
                // If content type is not set yet in the request, match with the client request "Accept" header
                $this->response->matchRequestContent($this->request);

                return $this->handle($controller, $method, $params);
            };
            // Loop through middlewares in reverse order
            // and redefine $next as middleware \Closure in the chain
            $middlewares = $route->getMiddlewares();
            for ($i = count($middlewares) - 1; $i >= 0; $i--) {

                $middleware = $middlewares[$i];

                // Register middleware on Container as callabale (instance every time it is called)
                $this->container->callable($middleware, $middleware);

                $next = function () use ($middleware, $next) {
                    return $this->handle($middleware, 'handle', ['next' => $next]);
                };
            }

            // Execute the chain
            $result = $next();

            // Detect programer error: for example all the lifecycle returns a \Closure
            if (!$result instanceof Response) {
                throw new \Error("The request lifecycle doesnt provide a result compatible with \Tuples\Http\Response");
            }

            return $result;
        } catch (\Throwable $exception) {
            /** @var \Tuples\Exception\Contracts\ExceptionHandler $handler */
            $handler = $this->container->resolve("ExceptionHandler", ["error" => $exception]);
            return $handler->response();
        }
    }

    /**
     * Execute Route Action detecting it from Request
     *
     * @return Response
     */
    public function resolve(): Response
    {
        return $this->resolvePath($this->request->method(), $this->request->path());
    }

    /**
     * Execute Route Action detecting it from $method and $path
     *
     * @return Response
     */
    public function resolvePath(string $method, string $path): Response
    {
        list($route, $params) = router()->lookup($method, $path);
        return $this->execute($route, $params);
    }

    /**
     * Execute the chain-action and return \Closure or \Tuples\Http\Response (cast result on response object if is needed)
     *
     * @param string $callback
     * @param string $method
     * @param array $args
     * @return Response|\Closure
     */
    private function handle(string $depedency, string $method, array $args): Response|\Closure
    {
        $value = $this->container->resolveAndExecute($depedency, $method, $args);

        // If it is a \Closure (next() function in the chain)
        // or if it is already a response, return it unmodified
        if ($value instanceof \Closure || $value instanceof Response) {
            return $value;
        }

        // Otherwise, return the response instance with the result as the body
        return $this->response->body($value);
    }
}
