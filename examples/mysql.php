<?php

require '../vendor/autoload.php';

use NikolaDev\ImagineCode\Logger;
use NikolaDev\ImagineCode\MySQL;

$logger = new Logger('mysql', dirname(__FILE__) . '/mysql.log');
$db = new MySQL('192.168.1.35', 'nikola', 'nikola', 'stedisa', $logger, true);
$db->logger->set_level(Logger::VERBOSE);
$db->print_results($db->select("SHOW TABLESd"));