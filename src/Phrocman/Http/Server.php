<?php

namespace Phrocman\Http;


use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Phrocman\Manager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\ChildProcess\Process;
use React\Http\Response;
use React\Stream\ThroughStream;

class Server
{
    protected $manager;
    protected $socket;
    protected $http;

    protected static function json(array $data, ?int $options=null): string
    {
        $options = $options ?? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        return json_encode($data, $options);
    }

    public function __construct(Manager $manager, string $uri)
    {
        $this->manager = $manager;

        $this->socket = new \React\Socket\Server($uri, $manager->getLoop());

        $routes = new RouteCollector(new Std(), new GroupCountBased());
        $routes->get('/', [$this, 'index']);
        $routes->get('/stdout/{service:\d+}/{instance:\d+}', [$this, 'stdout']);
        $routes->get('/test', [$this, 'test']);
        $routes->get('/foo', function () {
            return new Response(200, [
                'Content-Type' => 'text/plain'
            ], 'foo');
        });

        $this->http = new \React\Http\Server(new Router($routes));

        $this->http->listen($this->socket);
    }

    public function index()
    {
        $services = [];
        $timers = [];
        $processes = [];
        foreach ($this->manager->getProcesses() as $si => $prcs) {
            $serviceDescriptor = $this->manager->getServiceDescriptors()[$si];
            $instances = [];
            foreach ($prcs as $instance => $process) {
                $instances[] = $process->getPid();
            }

            $services[] = [
                'tag' => $serviceDescriptor->getTag(),
                'cmd' => $serviceDescriptor->getCmd(),
                'cwd' => $serviceDescriptor->getCwd(),
                'instances' => $instances,
            ];
        }
        foreach ($this->manager->getTimerDescriptors() as $timerDescriptor) {
            $timers[] = [
                'tag' => $timerDescriptor->getTag(),
                'cron' => $timerDescriptor->getCron()->__toString(),
                'cmd' => $timerDescriptor->getCmd(),
                'cwd' => $timerDescriptor->getCwd(),
            ];
        }
        /**
         * @var Process $process
         * @var int $instance
         */
        foreach ($this->manager->getProcesses() as $instances) {
            foreach ($instances as $instance => $process) {
                $processes[] = [
                    'pid' => $process->getPid(),
                    'cmd' => $process->getCommand(),
                    'instance' => $instance,
                ];
            }
        }
        $data = [
            'error' => false,
            'payload' => [
                'timers' => $timers,
                'services' => $services,
                'processes' => $processes,
            ],
        ];
        return new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'application/json'
        ], self::json($data));
    }

    public function stdout(ServerRequestInterface $request, $service, $instance)
    {
        $pid = $this->manager->getProcesses()[$service][$instance]->getPid();
        $stream = new ThroughStream();
        $this->manager->on('stdout', function (\Phrocman\Runnable\Service $service, int $instance, \React\ChildProcess\Process $process, string $data) use ($pid, $stream) {
            if ($process->getPid() == $pid) {
                $stream->write($data);
            }
        });

        $this->manager->on('fail', function (\Phrocman\Runnable\Service $service, int $instance, \React\ChildProcess\Process $process, int $code) use ($pid, $stream) {
            if ($process->getPid() == $pid) {
                $stream->close();
            }
        });

        return new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'text/octet-stream'
        ], $stream);
    }

    public function test()
    {
        return new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Content-Type' => 'application/json'
        ], self::json(func_get_args()));
    }

}
