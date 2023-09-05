<?php


require '../vendor/autoload.php';

use NikolaDev\ImagineCode\Logger;
$l = new Logger('foo', 'foo.log', Logger::ALL);
$l2 = new Logger('bar', 'bar.log', Logger::ALL);

$l2->attach_to($l);
$l2->error('foo');