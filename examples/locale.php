<?php


require '../vendor/autoload.php';

use NikolaDev\ImagineCode\Locale;

try {
    Locale::init(dirname(__FILE__), 'en_GB');
} catch (Exception $e) {
}

Locale::get("Hello %s %.2f!", 'Gee', 3000.4124);