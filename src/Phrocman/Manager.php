<?php

namespace Phrocman;


use DateTime;
use Evenement\EventEmitterTrait;
use Phrocman\Runnable\Service;
use Phrocman\Runnable\Timer;
use React\ChildProcess\Process;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class Manager
{
    use EventEmitterTrait;

    const ENV_PHROCMAN_INSTANCE = 'PHROCMAN_INSTANCE';

    /** @var int */
    public $restartTimeout = 3;

    /** @var LoopInterface */
    protected $loop;

    /** @var Runnable\Timer[] */
    protected $timerDescriptors = [];

    /** @var Runnable\Service[] */
    protected $serviceDescriptors = [];

    /** @var array */
    protected $services = [];

    protected function findServiceIndex(string $uid): int
    {
        foreach ($this->serviceDescriptors as $index => $svc) {
            if ($svc->getUid() === $uid) {
                return $index;
            }
        }
        return -1;
    }

    protected function tickSecond(DateTime $dateTime)
    {
        //echo 'tick second ', $dateTime->format('H:i:s.u'), PHP_EOL;
        $this->emit('tick', [$dateTime]);

        foreach ($this->timerDescriptors as $td) {
            $cron = $td->getCron();
            $cmd = $td->getCmd();
            $cwd = $td->getCwd();
            $env = $td->getEnv();
            if ($cron->check($dateTime)) {
                $this->emit('cron', [$td, $dateTime]);
                //echo 'triggering timer: ', $cron, PHP_EOL, $cmd, PHP_EOL;
                $start = microtime(true);
                $process = new Process($cmd, $cwd, $env);
                $process->start($this->loop);
                $process->stdout->on('data', function ($data) use ($process) {
                    //echo 'timer #', $process->getPid(), ' says:', PHP_EOL, trim($data), PHP_EOL;
                });
                $process->stderr->on('data', function ($error) use ($process) {
                    //echo 'timer #', $process->getPid(), ' error:', PHP_EOL, trim($error), PHP_EOL;
                });
                $process->on('exit', function ($code) use ($process, $start, $cron, $td, $dateTime) {
                    $took = microtime(true) - $start;
                    //echo 'timer #', $process->getPid(), ' exited with code ', $code, ' after ', $took, ' seconds', PHP_EOL;
                    if ($code !== 0) {
                        $this->emit('cron_fail', [$td, $code, $dateTime]);
                    }
                });
            }
        }
    }

    public function __construct()
    {
        $this->loop = Factory::create();
    }

    public function run()
    {
        $lastTime = 0;
        $this->loop->addPeriodicTimer(0.05, function () use (&$lastTime) {
            $time = microtime(true);
            $timeSec = floor($time);
            if ($timeSec > $lastTime) {
                $now = \DateTime::createFromFormat('U.u', sprintf('%0.6f', $time));
                if (!$now) {
                    throw new \RuntimeException('failed to create DateTime from microtime: ' . $time);
                }
                $this->tickSecond($now);
                $lastTime = $timeSec;
            }
        });

        foreach ($this->serviceDescriptors as $sd) {
            for ($i = 0; $i < $sd->getCnt(); $i++) {
                $this->startServiceInstance($sd->getUid(), $i);
            }
        }

        $this->loop->run();
    }

    public function stop()
    {
        $this->loop->stop();
    }

    #region SERVICES

    public function addService(string $tag, string $cmd, int $cnt = 1, ?string $cwd = null, ?array $env = null): Runnable\Service
    {
        $svc = new Runnable\Service($tag, $cmd, $cnt, $cwd, $env);
        $this->serviceDescriptors[] = $svc;
        $this->services[] = [];
        $this->setServiceInstanceCount($svc->getUid(), $cnt);
        return $svc;
    }

    public function removeService(string $uid): bool
    {
        $index = $this->findServiceIndex($uid);
        if ($index < 0) {
            return false;
        }
        /**
         * @var int $instance
         * @var Process $process
         */
        foreach ($this->services[$index] as $instance => $process) {
            $this->stopServiceInstance($uid, $instance);
        }
        array_splice($this->serviceDescriptors, $index, 1);
        array_splice($this->services, $index, 1);
        return true;
    }

    public function startService(string $uid): void
    {
        $this->emit('service/start', [$uid]);
        $index = $this->findServiceIndex($uid);
        $serviceDescriptor = $this->serviceDescriptors[$index];
        for($instance=0; $instance<$serviceDescriptor->getCnt(); $instance++) {
            $this->startServiceInstance($uid, $instance);
        }
        $this->setServiceInstanceCount($uid, $serviceDescriptor->getCnt());
    }

    public function stopService(string $uid): void
    {
        $this->emit('service/stop', [$uid]);
        foreach($this->services as $index => $processes) {
            foreach($processes as $instance => $process) {
                $this->stopServiceInstance($uid, $instance);
            }
        }
    }

    public function startServiceInstance(string $uid, int $instance): bool
    {
        $index = $this->findServiceIndex($uid);
        if ($index < 0) {
            return false;
        }

        if(isset($this->services[$index][$instance]) && $this->services[$index][$instance]) {
            return false;
        }

        $service = $this->serviceDescriptors[$index];
        $process = new Process($service->getCmd(), $service->getCwd(), $service->getEnv([self::ENV_PHROCMAN_INSTANCE => $instance]));
        $process->start($this->loop);
        $pid = $process->getPid();

        $this->services[$index][$instance] = $process;
        $this->emit('service/' . $uid . '/start', [$instance, $pid]);

        $process->stdout->on('data', function ($data) use ($uid, $instance) {
            $this->emit('service/stdout', [$uid, $instance, $data]);
            $this->emit('service/' . $uid . '/stdout', [$instance, $data]);
            $this->emit('service/' . $uid . '/' . $instance . '/stdout', [$data]);
        });

        $process->stderr->on('data', function ($error) use ($uid, $instance) {
            $this->emit('service/stderr', [$uid, $instance, $error]);
            $this->emit('service/' . $uid . '/stderr', [$instance, $error]);
            $this->emit('service/' . $uid . '/' . $instance . '/stderr', [$error]);
        });

        $process->on('exit', function ($code) use ($uid, $instance, $index, $pid) {
            $this->emit('service/fail', [$uid, $instance, $pid, $code]);
            $this->emit('service/' . $uid . '/fail', [$instance, $pid, $code]);
            $this->services[$index][$instance] = false;
//            $this->getLoop()->addTimer($this->restartTimeout, function () use ($instance, $uid) {
//                $this->startServiceInstance($uid, $instance);
//            });
        });

        return true;
    }

    public function stopServiceInstance(string $uid, int $instance): bool
    {
        $index = $this->findServiceIndex($uid);
        if ($index < 0) {
            return false;
        }
        if(!isset($this->services[$index][$instance])) {
            return false;
        }
        /** @var Process $process */
        $process = $this->services[$index][$instance];
        if ($process) {
            $pid = $process->getPid();
            foreach ($process->pipes as $pipe) {
                $pipe->close();
            }
            $t = $process->terminate();
            $this->services[$index][$instance] = null;
            $this->emit('service/' . $uid . '/stop', [$instance, $pid]);
            return $t;
        }
        return false;
    }

    public function getServiceInstanceCount(string $uid): int
    {
        $index = $this->findServiceIndex($uid);
        return count($this->services[$index]);
    }

    public function setServiceInstanceCount(string $uid, int $count): void
    {
        $index = $this->findServiceIndex($uid);
        $n = count($this->services[$index]);
        $d = $count - $n;
        if ($d === 0) {
            return;
        }
        $this->serviceDescriptors[$index]->setCnt($count);
        if ($d > 0) {
            for ($i = $n; $i < $n + $d; $i++) {
                $this->startServiceInstance($uid, $i);
            }
        } else {
            for ($i = $n - 1; $i >= $count; $i--) {
                $this->stopServiceInstance($uid, $i);
            }
        }
    }

    #endregion

    #region TIMERS

    public function addTimer(string $tag, Cron $cron, string $cmd, ?string $cwd = null, ?array $env = null): int
    {
        $timer = new Runnable\Timer($tag, $cron, $cmd, $cwd, $env);
        $this->timerDescriptors[] = $timer;
        return count($this->timerDescriptors) - 1;
    }

    #endregion

    #region GET / SET

    /**
     * @return LoopInterface
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * @return Service[]
     */
    public function getServiceDescriptors(): array
    {
        return $this->serviceDescriptors;
    }

    /**
     * @return Timer[]
     */
    public function getTimerDescriptors(): array
    {
        return $this->timerDescriptors;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @param string $uid
     * @return array|null
     */
    public function getService(string $uid): ?array
    {
        $index = $this->findServiceIndex($uid);
        if($index < 0) {
            return null;
        }
        return $this->services[$index];
    }

    /**
     * @return array
     */
    public function getProcesses(): array
    {
        $list = [];
        foreach ($this->services as $serviceIndex => $processes) {
            foreach ($processes as $instanceIndex => $process) {
                $pid = false;
                if ($process) {
                    $pid = $process->getPid();
                }
                $list[] = [
                    'uid' => $this->serviceDescriptors[$serviceIndex]->getUid(),
                    'instance' => $instanceIndex,
                    'pid' => $pid,
                    'process' => $process,
                ];
            }
        }
        return $list;
    }

    #endregion

}
