<?php

namespace Phrocman\Http;


use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Phrocman\Manager;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Timer\Timer;
use React\Http\Response;
use React\Stream\ThroughStream;

class Server
{
    const URL_PREFIX = '/api';

    protected $manager;
    protected $socket;
    protected $http;

    public function __construct(Manager $manager, string $uri)
    {
        $this->manager = $manager;

        $this->socket = new \React\Socket\Server($uri, $manager->getLoop());

        $routes = new RouteCollector(new Std(), new GroupCountBased());

        $routes->get(self::URL_PREFIX . '', [$this, 'index']);
//        $routes->get(self::URL_PREFIX . '/stdout', [$this, 'index']);
//
//        $routes->get(self::URL_PREFIX . '/service/{uid}/status', [$this, 'serviceStatus']);
//        $routes->get(self::URL_PREFIX . '/service/{uid}/stdout', [$this, 'serviceStdout']);
//        $routes->get(self::URL_PREFIX . '/service/{uid}/stderr', [$this, 'serviceStderr']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/start', [$this, 'serviceStart']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/stop', [$this, 'serviceStop']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/restart', [$this, 'serviceRestart']);
//        $routes->get(self::URL_PREFIX . '/service/{uid}/{instance}/stdout', [$this, 'serviceInstanceStdout']);
//        $routes->get(self::URL_PREFIX . '/service/{uid}/{instance}/stderr', [$this, 'serviceInstanceStderr']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/{instance}/start', [$this, 'serviceInstanceStart']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/{instance}/stop', [$this, 'serviceInstanceStop']);
//        $routes->post(self::URL_PREFIX . '/service/{uid}/{instance}/restart', [$this, 'serviceInstanceRestart']);
//

        $ws = new Wamp\Server($this->manager);
        $this->http = new \React\Http\Server([$ws, new Router($routes)]);

        $this->http->listen($this->socket);
    }

    protected static function json(array $data, ?int $options = null): string
    {
        $options = $options ?? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        return json_encode($data, $options);
    }

    public function index()
    {
        return $this->manager->toArray();
    }

    public function serviceStatus(ServerRequestInterface $request, $uid)
    {
        $service = $this->manager->findService($uid);
        $r = [];
        if ($service) {
            foreach ($service->getInstances() as $instance) {
                $r[] = $instance->toArray();
            }
        }
        return $r;
    }

    public function serviceStdout(ServerRequestInterface $request, $uid)
    {
        $stream = new ThroughStream();

        $this->manager->on('service/' . $uid . '/stdout', function ($instance, $data) use ($stream) {
            $stream->write($data);
        });

        $this->manager->on('service/' . $uid . '/fail', function ($instance, $pid, $code) use ($stream) {
            $stream->close();
        });

        return $stream;
    }

    public function serviceStderr(ServerRequestInterface $request, $uid)
    {
        $stream = new ThroughStream();

        $this->manager->on('service/' . $uid . '/stderr', function ($instance, $error) use ($stream) {
            $stream->write($error);
        });

        $this->manager->on('service/' . $uid . '/fail', function ($i, $pid, $code) use ($stream) {
            $stream->close();
        });

        return $stream;
    }

    public function serviceStart(ServerRequestInterface $request, $uid)
    {
        $this->manager->startService($uid);
        return true;
    }

    public function serviceStop(ServerRequestInterface $request, $uid)
    {
        $this->manager->stopService($uid);
        return true;
    }

    public function serviceRestart(ServerRequestInterface $request, $uid)
    {
        $this->manager->stopService($uid);
        $this->manager->startService($uid);
    }

    public function serviceInstanceStart(ServerRequestInterface $request, $uid, $instance)
    {
        return $this->manager->startServiceInstance($uid, $instance);
    }

    public function serviceInstanceStop(ServerRequestInterface $request, $uid, $instance)
    {
        return $this->manager->stopServiceInstance($uid, $instance);
    }

    public function serviceInstanceRestart(ServerRequestInterface $request, $uid, $instance)
    {
        $this->manager->stopServiceInstance($uid, $instance);
        $this->manager->startServiceInstance($uid, $instance);
    }

    public function serviceInstanceStdout(ServerRequestInterface $request, $uid, $instance)
    {
        $stream = new ThroughStream();

        $this->manager->on('service/' . $uid . '/' . $instance . '/stdout', function (string $data) use ($stream) {
            $stream->write($data);
        });

        $this->manager->on('service/' . $uid . '/fail', function ($i, $pid, $code) use ($instance, $stream) {
            if ($instance === $i) {
                $stream->close();
            }
        });

        return $stream;
    }

    public function serviceInstanceStderr(ServerRequestInterface $request, $uid, $instance)
    {
        $stream = new ThroughStream();

        $this->manager->on('service/' . $uid . '/' . $instance . '/stderr', function (string $data) use ($stream) {
            $stream->write($data);
        });

        $this->manager->on('service/' . $uid . '/fail', function ($i, $pid, $code) use ($instance, $stream) {
            if ($instance === $i) {
                $stream->close();
            }
        });

        return $stream;
    }

}
