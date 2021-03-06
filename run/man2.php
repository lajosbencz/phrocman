<?php

require_once __DIR__ . '/../vendor/autoload.php';

$cwd = dirname(__DIR__) . '/test';
$exec = 'php '.$cwd.'/payload.php';

$eventsManager = new \Phrocman\EventsManager;

//$eventsManager->on('tick', function(\DateTime $dateTime) {
//    echo 'tick second ', $dateTime->format('H:i:s.u'), PHP_EOL;
//});
//$eventsManager->on('stdout', function($what, $data) {
//    echo $data;
//});
//$eventsManager->on('stderr', function($what, $data) {
//    echo 'ERROR: ', $data;
//});

$group = new \Phrocman\Group('Travelhood');

$gWs = new \Phrocman\Group('WebSocket', $group);
$gWsTour = new \Phrocman\Group('tour', $gWs);
$wsTourInfo = $gWsTour->addService('info', $exec.' --r -1 --f 2 tour info', $cwd, [], 5);
$wsTourJoin = $gWsTour->addService('join', $exec.' --r -1 --f 5 tour join', $cwd);
$wsTourComment = $gWsTour->addService('comment', $exec.' --r -1 --f 10 tour comment', $cwd);

$gTmax = new \Phrocman\Group('Travelmax', $group);
$gTmax->addTimer('daily', new \Phrocman\Cron('*', '*', '*', '6,7,8', '0', '0'), $exec.' timer tmax daily');
$gTmax->addTimer('second', new \Phrocman\Cron('*', '*', '*', '*', '*', '1/5'), $exec.' timer tmax second');


$manager = new \Phrocman\Manager($group, $eventsManager);

//echo json_encode($manager->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;

$server = new \Phrocman\Http\Server($manager, '0.0.0.0:8080');
$manager->start();



// #2

$descriptor = [
    'name' => 'Travelhood',
    'children' => [
        [
            'name' => 'WebSocket',
            'children' => [
                [
                    'name' => 'tour',
                    'children' => [],
                    'services' => [
                        [
                            'cmd' => 'php payload.php -f',
                            'cwd' => $cwd,
                        ],
                    ],
                ],
            ],
            'services' => [],
            'timers' => [],
        ],
    ],
    'services' => [],
    'timers' => [],
];


// #1

$pg = new \Phrocman\Group('parent group');
$cg1 = new \Phrocman\Group('child 1');
$cg2 = new \Phrocman\Group('child 2');
$pg->addChild($cg1);
$pg->addChild($cg2);


$sd1 = new \Phrocman\Runnable\Service('svc1', $exec. ' --r -1 --c 1', 1, __DIR__, [], []);
$sd2 = new \Phrocman\Runnable\Service('svc2', $exec. ' --r -1 --c 2 -e', 1, __DIR__, [], []);

$s1 = new \Phrocman\Runnable\Service($sd1, 0, $loop);
$cg1->addService($s1);
$s2 = new \Phrocman\Runnable\Service($sd2, 0, $loop);
$cg2->addService($s2);

echo json_encode($pg->toArray(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;

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
