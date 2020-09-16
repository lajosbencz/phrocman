<?php

require_once __DIR__ . '/../vendor/autoload.php';

$exec = 'php '.dirname(__DIR__).'/test/payload.php';

$root = new \Phrocman\Group('phrocman');
$root->addService('foo is successful',$exec.' --c 3 --r -1 --f 1 foo', 2);
$root->addService('bar process failing',$exec.' -e --c 256 --r -1 --f 2 BAR!', 3);
$root->addTimer('infinite eleven', new \Phrocman\Cron('*', '*', '*', '*', '*', '1/10'), $exec.' --f 0 timed!');

$root->on('tick', function(DateTime $dateTime) {
    //echo 'tick ', $dateTime->format('Y-m-d H:i:s'), PHP_EOL;
});

$root->on('cron', function(\Phrocman\Runnable\Timer $timer, DateTime $dateTime) {
    echo 'triggering timer: ', $timer->getCron(), PHP_EOL, $dateTime->format('Y-m-d H:i:s'), PHP_EOL, $timer->getCmd(), PHP_EOL;
});

$root->on('cron_fail', function(Phrocman\Runnable\Timer $timer, int $code, DateTime $dateTime) {
    echo 'timer failed with code #',$code,': ', $timer->getCmd(), PHP_EOL;
});

$root->on('stdout', function(\Phrocman\Runnable $runnable, $instance, $data) {
    //var_dump($args);
    //echo 'service #', $runnable->getCmd(), ' group path:', $runnable->getGroup()->getPath(), ' says: ', PHP_EOL, $data, PHP_EOL;
    echo $data;
});

$root->on('stderr', function(\Phrocman\Runnable $runnable, $instance, $error) {
    //echo 'service #', $runnable->getCmd(), ' group path:', $runnable->getGroup()->getPath(), ' error: ', PHP_EOL, $error, PHP_EOL;
    echo $error;
});

$root->on('fail', function(Phrocman\Runnable $runnable, int $code) {
    echo 'service #', $runnable->getCmd(), ' instance #', $runnable->getGroup()->getPath(), ' failed with code: ', $code, PHP_EOL;
});

$root->on('restart', function(\Phrocman\Runnable\Service $service, int $instance) {
    echo 'service #', $service->getCmd(), ' instance #', $instance, ' restarted', PHP_EOL;
});

$man = new \Phrocman\Manager($root);
$httpServer = new \Phrocman\Http\Server($man, '0.0.0.0:8080');
$man->start();
