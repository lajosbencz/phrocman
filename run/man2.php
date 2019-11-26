<?php

require_once __DIR__ . '/../vendor/autoload.php';

$exec = 'php '.dirname(__DIR__).'/test/payload.php';

$loop = \React\EventLoop\Factory::create();

$pg = new \Phrocman\Group('parent group');
$cg1 = new \Phrocman\Group('child 1');
$cg2 = new \Phrocman\Group('child 2');
$pg->addChild($cg1);
$pg->addChild($cg2);


$sd1 = new \Phrocman\Descriptor\Service('svc1', $exec. ' --r -1 --c 1', 1, __DIR__, [], []);
$sd2 = new \Phrocman\Descriptor\Service('svc2', $exec. ' --r -1 --c 2 -e', 1, __DIR__, [], []);

$s1 = new \Phrocman\Runnable\Service($sd1, 0, $loop);
$cg1->addService($s1);
$s2 = new \Phrocman\Runnable\Service($sd2, 0, $loop);
$cg2->addService($s2);

$pg->on('stdout', function($data, \Phrocman\Runnable $item, \Phrocman\Group $group) {
    echo $group->getName(), ' : ', $item->getUid(), ' : ', $data;
});
$pg->on('stderr', function($data, \Phrocman\Runnable $item, \Phrocman\Group $group) {
    echo $group->getName(), ' : ', $item->getUid(), ' : ', $data;
});
$pg->on('exit', function($code, \Phrocman\Runnable\Service $item, \Phrocman\Group $group) {
    echo $group->getName(), ' : ', $item->getUid(), ' : ', $item->getProcess()->getPid(), ' exited with code ', $code, PHP_EOL;
});
$pg->on('fail', function($code, \Phrocman\Runnable\Service $item, \Phrocman\Group $group) {
    echo $group->getName(), ' : ', $item->getUid(), ' : ', $item->getProcess()->getPid(), ' failed with code ', $code, PHP_EOL;
});


$loop->addTimer(3, function() use($pg) {
    $pg->start();
});

$loop->addPeriodicTimer(10, function() use($cg1) {
    $cg1->stop();
});

$loop->run();
