<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/*.php') as $filename) {
    if (!in_array(basename($filename), ['autoload.php'])) {
        include_once $filename;
    }
}

?>