<?php

use Phrocman\CliArgs;

require_once __DIR__ . '/../vendor/autoload.php';

array_shift($argv);
$args = new CliArgs($argv);

if($args->hasFlag('help')) {
    die("Flags:
-e      Write message to STDERR
Options:
--r     Repeat message this number of times, -1 for infinite (1 by default)
--f     Repeat message at this frequency in seconds (1 by default)
--c     Exit code to return (0 by default)
");
}

$repeat = intval($args->getOption(['r', 'repeat'], 1));
$freq = floatval($args->getOption(['f', 'freq'], 1));
$exitCode = intval($args->getOption(['c', 'code'], 0));
$message = 'echo';
if($args->countArguments() > 0) {
    $message = join(' ', $args->getArguments());
}

//print_r(getenv());

$i = 0;
while($repeat < 0 || $i < $repeat) {
    usleep($freq * 1000 * 1000);
    $now = DateTime::createFromFormat('U.u', microtime(true));
    $line = '[' . $now->format('Y-m-d H:i:s.u') . '] #' . ($i + 1) . ': ' . trim($message) . PHP_EOL;
    if($args->hasFlag('e')) {
        fwrite(STDERR, $line);
    } else {
        fwrite(STDOUT, $line);
    }
    $i++;
}

exit($exitCode);
