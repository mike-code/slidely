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
            'controllersDir' => APP_PATH . '/Controllers/',
            'debug'          => true,
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
    ],
];
