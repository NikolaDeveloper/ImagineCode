<?php


require '../vendor/autoload.php';

use NikolaDev\ImagineCode\Events;

//Event
Events::attach_event('init', function() {
    echo "Triggered on init" . PHP_EOL;
});

Events::invoke('init');

//Filter
Events::attach_filter('modify_foo', function($original_value) {
    return 'modified';
});
$foo = 'bar';
echo "Pre-filtered foo: $foo" . PHP_EOL;

$foo = Events::filter('modify_foo', $foo);
echo "Filtered foo: $foo" . PHP_EOL;