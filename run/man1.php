<?php

require_once __DIR__ . '/../vendor/autoload.php';

$exec = 'php '.dirname(__DIR__).'/test/payload.php';

$man = new \Phrocman\Manager;
$httpServer = new \Phrocman\Http\Server($man, '0.0.0.0:8080');
$man->addService($exec.' --c 3 --r -1 --f 1 foo', 2);
$man->addService($exec.' -e --c 256 --r -1 --f 2 BAR!', 3);
$man->addTimer(new \Phrocman\Cron('*', '*', '*', '*', '*', '1/10'), $exec.' --f 0 timed!');

$man->on('tick', function(DateTime $dateTime) {
    //echo 'tick ', $dateTime->format('Y-m-d H:i:s'), PHP_EOL;
});

$man->on('cron', function(\Phrocman\Runnable\Timer $timer, DateTime $dateTime) {
    echo 'triggering timer: ', $timer->getCron(), PHP_EOL, $dateTime->format('Y-m-d H:i:s'), PHP_EOL, $timer->getCmd(), PHP_EOL;
});

$man->on('cron_fail', function(Phrocman\Runnable\Timer $timer, int $code, DateTime $dateTime) {
    echo 'timer failed with code #',$code,': ', $timer->getCmd(), PHP_EOL;
});

//$man->on('stdout', function(\Phrocman\Runnable\Service $service, int $instance, \React\ChildProcess\Process $process, string $data) {
//    echo 'service #', $process->getPid(), ' instance #', $instance, ' says: ', PHP_EOL, $data, PHP_EOL;
//});

//$man->on('stderr', function(\Phrocman\Runnable\Service $service, int $instance, \React\ChildProcess\Process $process, string $error) {
//    echo 'service #', $process->getPid(), ' instance #', $instance, ' error: ', PHP_EOL, $error, PHP_EOL;
//});

$man->on('fail', function(\Phrocman\Runnable\Service $service, int $instance, \React\ChildProcess\Process $process, int $code) {
    echo 'service #', $process->getPid(), ' instance #', $instance, ' failed with code: ', $code, PHP_EOL;
});

$man->on('restart', function(\Phrocman\Runnable\Service $service, int $instance) {
    echo 'service #', $service->getCmd(), ' instance #', $instance, ' restarted', PHP_EOL;
});

$man->run();
