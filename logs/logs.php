<?php
function logs($message) {
    $logFile = __DIR__ . '/../logs/log.txt';
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND); 
}
