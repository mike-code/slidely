<?php
defined('BASE_PATH') || define('BASE_PATH', realpath(dirname(__FILE__) . '/..'));
defined('APP_PATH') || define('APP_PATH', BASE_PATH . '/src');

return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        'application' => [
            'cacheDir'       => BASE_PATH . '/cache/',
            'logsDir'        => BASE_PATH . '/logs/',
            'videoDir'       => BASE_PATH . '/public/downloads/video/',
            'audioDir'       => BASE_PATH . '/public/downloads/audio/',
            'controllersDir' => APP_PATH . '/Controllers/',
            'debug'          => false,
            'maxVideoLength' => 60, // in minutes
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
