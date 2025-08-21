<?php
class Logger {
    private static $logFile;
    
    public static function init() {
        self::$logFile = __DIR__ . "/../logs/app.log";
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0777, true);
        }
    }
    
    public static function log($level, $message, $context = []) {
        if (!self::$logFile) {
            self::init();
        }
        
        $timestamp = date("Y-m-d H:i:s");
        $contextStr = !empty($context) ? " " . json_encode($context) : "";
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console
        echo "  [{$level}] {$message}\n";
    }
    
    public static function info($message, $context = []) {
        self::log("INFO", $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log("WARNING", $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log("ERROR", $message, $context);
    }
    
    public static function success($message, $context = []) {
        self::log("SUCCESS", $message, $context);
    }
}
