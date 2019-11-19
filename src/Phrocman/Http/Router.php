<?php

namespace Phrocman\Http;


use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Stream\ThroughStream;

final class Router
{
    private $dispatcher;

    public function __construct(RouteCollector $routes)
    {
        $this->dispatcher = new GroupCountBased($routes->getData());
    }

    public function __invoke(ServerRequestInterface $request, callable $next = null)
    {
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($routeInfo[0]) {
            default:
                throw new \LogicException('Something wrong with routing');
            case Dispatcher::NOT_FOUND:
                $response = new Response(404, [
                    'Content-Type' => 'text/plain',
                    'Access-Control-Allow-Origin' => '*',
                ], 'Not found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = new Response(405, [
                    'Content-Type' => 'text/plain',
                    'Access-Control-Allow-Origin' => '*',
                ], 'Method not allowed');
                break;
            case Dispatcher::FOUND:
                $action = $routeInfo[1];
                $params = $routeInfo[2];
                try {
                    $response = $action($request, ...array_values($params));
                    if ($response instanceof ThroughStream) {
                        $response = new Response(200, [
                            'Content-Type' => 'text/octet-stream',
                            'Access-Control-Allow-Origin' => '*',
                        ], $response);
                    }
                    if (!$response instanceof ResponseInterface) {
                        $response = new Response(200, [
                            'Content-Type' => 'application/json',
                            'Access-Control-Allow-Origin' => '*',
                        ], json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    }
                } catch (\Throwable $e) {
                    $error = [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace(),
                    ];
                    $response = new Response(500, [
                        'Content-Type' => 'application/json',
                        'Access-Control-Allow-Origin' => '*',
                    ], json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
                break;
        }

        $response->withHeader('Access-Control-Allow-Origin', '*');

        return $response;
    }
}