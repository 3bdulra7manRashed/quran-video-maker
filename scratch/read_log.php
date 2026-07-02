<?php
$lines = file('storage/logs/laravel.log');
$lastLines = array_slice($lines, -50);
echo implode("", $lastLines);
