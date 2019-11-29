<?php

namespace Phrocman\Http\Wamp;


use Phrocman\Exception;
use Phrocman\Group;
use Phrocman\Manager;
use Phrocman\Runnable;
use Phrocman\RunnableInterface;
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
                'groupInfo',
                'groupStart',
                'groupStop',
                'groupRestart',
                'serviceInfo',
                'timerInfo',
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

            $manager->on('start', function(RunnableInterface $runnable, Group $group) use($session) {
                if($runnable instanceof Runnable\Service) $type = 'service';
                elseif($runnable instanceof Runnable\Timer) $type = 'timer';
                else $type = 'group';
                $session->publish('start', [], ['type'=>$type, 'uid'=>$runnable]);
            });

            $manager->on('stop', function(RunnableInterface $runnable, Group $group) use($session) {
                if($runnable instanceof Runnable\Service) $type = 'service';
                elseif($runnable instanceof Runnable\Timer) $type = 'timer';
                else $type = 'group';
                $session->publish('stop', [], ['type'=>$type, 'uid'=>$runnable]);
            });

            $manager->on('fail', function(RunnableInterface $runnable, Group $group) use($session) {
                if($runnable instanceof Runnable\Service) $type = 'service';
                elseif($runnable instanceof Runnable\Timer) $type = 'timer';
                else $type = 'group';
                $session->publish('fail', [], ['type'=>$type, 'uid'=>$runnable]);
            });

//            $manager->on('service.start', function ($uid, $instance=null, $pid=null) use($session) {
//                $session->publish('service.start', [], ['uid'=>$uid, 'instance'=>$instance, 'pid'=>$pid]);
//            });
//            $manager->on('service.stop', function ($uid, $instance=null, $pid=null) use($session) {
//                $session->publish('service.stop', [], ['uid'=>$uid, 'instance'=>$instance, 'pid'=>$pid]);
//            });
//            $manager->on('service.fail', function ($uid, $instance) use($session) {
//                $session->publish('service.fail', [], ['uid'=>$uid, 'instance'=>$instance]);
//            });

        });
    }

    public function index($args, $kvArgs, $details, Deferred $deferred)
    {
        return [$this->manager->toArray()];
    }

    public function groupInfo($args, $kvArgs, $details, Deferred $deferred)
    {
        if($group = $this->manager->findGroup($kvArgs->uid)) {
            return [$group->toArray()];
        }
        throw new Exception('invalid group: '.$kvArgs->uid);
    }

    public function groupStart($args, $kvArgs, $details, Deferred $deferred)
    {
        if($group = $this->manager->findGroup($kvArgs->uid)) {
            $group->start();
        }
        return [true];
    }

    public function groupStop($args, $kvArgs, $details, Deferred $deferred)
    {
        if($group = $this->manager->findGroup($kvArgs->uid)) {
            $group->stop();
        }
        return [true];
    }

    public function groupRestart($args, $kvArgs, $details, Deferred $deferred)
    {
        if($group = $this->manager->findGroup($kvArgs->uid)) {
            $group->restart();
        }
        return [true];
    }

    public function serviceInfo($args, $kvArgs, $details, Deferred $deferred)
    {
        if($service = $this->manager->findService($kvArgs->uid)) {
            return [$service->toArray()];
        }
        throw new Exception('invalid service: '.$kvArgs->uid);
    }

    public function timerInfo($args, $kvArgs, $details, Deferred $deferred)
    {
        if($timer = $this->manager->findTimer($kvArgs->uid)) {
            return [$timer->toArray()];
        }
        throw new Exception('invalid timer: '.$kvArgs->uid);
    }

}
