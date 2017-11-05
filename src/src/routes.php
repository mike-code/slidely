<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Routes

$app->get('/api/youtube2mp3', function (Request $request, Response $response, array $args)
{
    try
    {
        $uri = $request->getQueryParam('url');

        if(!$uri) throw new \App\LogicException('Url not specified');

        $youtube = new \App\Controllers\YoutubeController($this->application, $this->logger);

        $videoId = $youtube->parseUri($uri);
        $hash    = $youtube->download();
        $youtube->convert($hash);

        return $response->withJson(['id' => $videoId, 'url' => "http://{$request->getUri()->getHost()}/downloads/audio/{$hash}.m4a"]);
    }
    catch(\App\LogicException $e)
    {
        return $response->withJson(["message" => $e->getMessage()]);
    }
});

$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

