<?php

namespace Phrocman\Http\Wamp;


use Phrocman\Manager;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\Promise\Deferred;
use Thruway\ClientSession;
use Thruway\Peer\Client as ThruwayClient;

class Client extends ThruwayClient
{
    protected $manager;

    public function __construct(Manager $manager, string $realm)
    {
        $this->manager = $manager;
        parent::__construct($realm, $manager->getLoop());

        $this->on('open', function(ClientSession $session) use($manager) {

            $session->getLoop()->addPeriodicTimer(60, function() use($session) {
                static $x = 0;
                $session->publish('heartbeat', [$x++]);
            });

            foreach([
                'index',
                'services',
                'serviceCreate',
                'serviceDelete',
                'serviceEdit',
                'serviceStatus',
                'serviceStart',
                'serviceStop',
                'serviceRestart',
                'serviceInstanceStart',
                'serviceInstanceStop',
                'serviceInstanceRestart',
                        ] as $method) {
                $session->register($method, function($args, $kvArgs, $details) use($method) {
                    $deferred = new Deferred();
                    $this->getLoop()->addTimer(Timer::MIN_INTERVAL, function () use ($args, $kvArgs, $details, $method, $deferred) {
                        try {
                            $result = call_user_func([$this, $method], $args, $kvArgs, $details, $deferred);
                            if ($result !== $deferred) {
                                $deferred->resolve($result);
                                return;
                            }
                        }
                        catch (\Throwable $e) {
                            echo $e->getMessage(), PHP_EOL, $e->getTraceAsString(), PHP_EOL;
                            $deferred->reject($e);
                        }
                    });
                    return $deferred->promise();
                });
            }

            $manager->on('service.start', function ($uid, $instance=null, $pid=null) use($session) {
                $session->publish('service.start', [], ['uid'=>$uid, 'instance'=>$instance, 'pid'=>$pid]);
            });
            $manager->on('service.stop', function ($uid, $instance=null, $pid=null) use($session) {
                $session->publish('service.stop', [], ['uid'=>$uid, 'instance'=>$instance, 'pid'=>$pid]);
            });
            $manager->on('service.fail', function ($uid, $instance) use($session) {
                $session->publish('service.fail', [], ['uid'=>$uid, 'instance'=>$instance]);
            });

        });
    }

    public function index($args, $kvArgs, $details, Deferred $deferred)
    {
        $services = [];
        $timers = [];
        $processes = [];

        try {
            foreach ($this->manager->getServices() as $index => $service) {
                $serviceDescriptor = $this->manager->getServiceDescriptors()[$index];
                $instances = [];
                foreach ($service as $instance => $process) {
                    if($process) {
                        $instances[] = $process->getPid();
                    } else {
                        $instances[] = false;
                    }
                }
                $services[] = [
                    'uid' => $serviceDescriptor->getUid(),
                    'tag' => $serviceDescriptor->getTag(),
                    'cmd' => $serviceDescriptor->getCmd(),
                    'cwd' => $serviceDescriptor->getCwd(),
                    'processes' => $instances,
                ];
            }
        } catch (\Throwable $e) {
            echo $e->getMessage(), PHP_EOL;
            echo $e->getFile(), '#', $e->getLine(), PHP_EOL;
        }

        foreach ($this->manager->getTimerDescriptors() as $timerDescriptor) {
            $timers[] = [
                'uid' => $timerDescriptor->getUid(),
                'tag' => $timerDescriptor->getTag(),
                'cron' => $timerDescriptor->getCron()->__toString(),
                'cmd' => $timerDescriptor->getCmd(),
                'cwd' => $timerDescriptor->getCwd(),
            ];
        }

        foreach ($this->manager->getProcesses() as $process) {
            $pid = false;
            if ($process['process']) {
                $pid = $process['process']->getPid();
            }
            $processes[] = [
                'uid' => $process['uid'],
                'instance' => $process['instance'],
                'pid' => $pid,
            ];
        }

        return [
            'error' => false,
            'payload' => [
                'timers' => $timers,
                'services' => $services,
                'processes' => $processes,
            ],
        ];
    }


    public function services($args, $kvArgs, $details, Deferred $deferred)
    {
        $list = [];
        foreach ($this->manager->getServices() as $si => $prcs) {
            $serviceDescriptor = $this->manager->getServiceDescriptors()[$si];
            $instances = [];
            foreach ($prcs as $instance => $process) {
                $instances[] = $process->getPid();
            }
            $list[] = [
                'uid' => $serviceDescriptor->getUid(),
                'tag' => $serviceDescriptor->getTag(),
                'cmd' => $serviceDescriptor->getCmd(),
                'cwd' => $serviceDescriptor->getCwd(),
                'instances' => $instances,
            ];
        }
        return $list;
    }

    public function serviceCreate($args, $kvArgs, $details, Deferred $deferred)
    {
    }

    public function serviceDelete($args, $kvArgs, $details, Deferred $deferred)
    {
    }

    public function serviceEdit($args, $kvArgs, $details, Deferred $deferred)
    {
    }

    public function serviceStatus($args, $kvArgs, $details, Deferred $deferred)
    {
        $r = [];
        $service = $this->manager->getService($kvArgs->uid);
        if ($service) {
            foreach ($service as $instance => $process) {
                $pid = false;
                if ($process) {
                    $pid = $process->getPid();
                }
                $r[$instance] = $pid;
            }
        }
        return $r;
    }

    public function serviceStart($args, $kvArgs, $details, Deferred $deferred)
    {
        $this->manager->startService($kvArgs->uid);
        return true;
    }

    public function serviceStop($args, $kvArgs, $details, Deferred $deferred)
    {
        $this->manager->stopService($kvArgs->uid);
        return true;
    }

    public function serviceRestart($args, $kvArgs, $details, Deferred $deferred)
    {
        $this->manager->stopService($kvArgs->uid);
        $this->manager->getLoop()->addTimer(3, function () use($kvArgs, $deferred) {
            $this->manager->startService($kvArgs->uid);
            $deferred->resolve([true]);
        });
        return $deferred;
    }

    public function serviceInstanceStart($args, $kvArgs, $details, Deferred $deferred)
    {
        return $this->manager->startServiceInstance($kvArgs->uid, $kvArgs->instance);
    }

    public function serviceInstanceStop($args, $kvArgs, $details, Deferred $deferred)
    {
        return $this->manager->stopServiceInstance($kvArgs->uid, $kvArgs->instance);
    }

    public function serviceInstanceRestart($args, $kvArgs, $details, Deferred $deferred)
    {
        $this->manager->stopServiceInstance($kvArgs->uid, $kvArgs->instance);
        $this->manager->getLoop()->addTimer(3, function () use($kvArgs, $deferred) {
            $this->manager->startServiceInstance($kvArgs->uid, $kvArgs->instance);
            $deferred->resolve([true]);
        });
        return $deferred;
    }

}
