<?php

namespace Phrocman\Http\Wamp;


use Phrocman\Manager;
use Psr\Http\Message\ServerRequestInterface;
use Thruway\Middleware;
use Thruway\Peer\Router;

class Server
{
    protected $manager;
    protected $wamp;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        $loop = $manager->getLoop();
        $router = new Router($loop);
        $client = new Client($manager, 'public');
        $router->addInternalClient($client);
        $router->start(false);
        $this->wamp = new Middleware(['/ws'], $loop, $router);
        $manager->getEventsManager()->on('stdout', function(...$args) use($client) {
            $client->getSession()->publish('stdout', $args);
        });
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        return $this->wamp->__invoke($request, $next);
    }
}
