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

    /** @var array */
    protected $processes = [];

    public function __construct()
    {
        $this->loop = Factory::create();
    }

    public function addService(string $cmd, int $cnt = 1, ?string $cwd = null, ?array $env = null): Runnable\Service
    {
        $svc = new Runnable\Service($cmd, $cnt, $cwd, $env);
        $this->serviceDescriptors[] = $svc;
        return $svc;
    }

    public function addTimer(Cron $cron, string $cmd, ?string $cwd = null, ?array $env = null): Runnable\Timer
    {
        $timer = new Runnable\Timer($cron, $cmd, $cwd, $env);
        $this->timerDescriptors[] = $timer;
        return $timer;
    }

    public function run()
    {
        $lastTime = 0;
        $this->loop->addPeriodicTimer(0.05, function () use (&$lastTime) {
            $time = microtime(true);
            $timeSec = floor($time);
            if ($timeSec > $lastTime) {
                $now = \DateTime::createFromFormat('U.u', sprintf('%0.6f',$time));
                if(!$now) {
                    throw new \RuntimeException('failed to create DateTime from microtime: '.$time);
                }
                $this->tickSecond($now);
                $lastTime = $timeSec;
            }
        });

        foreach ($this->serviceDescriptors as $sd) {
            for ($i = 0; $i < $sd->getCnt(); $i++) {
                $this->runService($i, $sd);
            }
        }

        $this->loop->run();
    }

    public function tickSecond(DateTime $dateTime)
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

    protected function runService(int $instance, Runnable\Service $service)
    {
        $process = new Process($service->getCmd(), $service->getCwd(), $service->getEnv([self::ENV_PHROCMAN_INSTANCE => $instance]));
        $process->start($this->loop);
        $process->stdout->on('data', function ($data) use ($service, $process, $instance) {
            //echo 'service #', $process->getPid(), ' instance #', $instance, ' says:', PHP_EOL, $data, PHP_EOL;
            $this->emit('stdout', [$service, $instance, $process, $data]);
        });
        $process->stderr->on('data', function ($error) use ($service, $process, $instance) {
            //echo 'service #', $process->getPid(), ' instance #', $instance, ' error:', PHP_EOL, $error, PHP_EOL;
            $this->emit('stderr', [$service, $instance, $process, $error]);
        });
        $process->on('exit', function ($code) use ($service, $process, $instance) {
            //echo 'service #', $process->getPid(), ' exited with code ', $code, PHP_EOL;
            $this->emit('fail', [$service, $instance, $process, $code]);
            $si = array_search([$process, $instance], $this->services);
            if ($si === false) {
                throw new \RuntimeException('failed to clean up service pid #' . $process->getPid());
            }
            array_splice($this->services, $si, 1);
            $si = array_search($service, $this->serviceDescriptors);
            if ($si === false) {
                throw new \RuntimeException('failed to clean up process pid #' . $process->getPid());
            }
            unset($this->processes[$si][$instance]);
            $this->getLoop()->addTimer($this->restartTimeout, function () use ($instance, $service) {
                //echo 'restarting service instance #', $instance, ' : ', $service->getCmd(), PHP_EOL;
                $this->emit('restart', [$service, $instance]);
                $this->runService($instance, $service);
            });
        });
        $this->services[] = [$process, $instance];
        $si = array_search($service, $this->serviceDescriptors);
        if($si === false) {
            throw new \RuntimeException('failed to determine serviceDescriptor index');
        }
        $this->processes[$si][$instance] = $process;
    }

    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    public function stop()
    {
        $this->loop->stop();
    }

    #region GET ? SET

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * @return array
     */
    public function getProcesses(): array
    {
        return $this->processes;
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

    #endregion

}
