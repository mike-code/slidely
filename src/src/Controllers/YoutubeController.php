<?php

namespace App\Controllers;

class YoutubeController
{
    private $video_id;
    private $app;

    function __construct($app)
    {
        $this->app = $app;
    }

    public function parseUri(string $uri)
    {
        $pattern = "/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtu\.be|youtube\.com(?:\/watch\?v=|\/v\/|\/&v=\/))([\w-]{10,12})$/";

        if(!preg_match_all($pattern, $uri, $matches)) throw new \App\LogicException('Invalid youtube URI');

        $this->video_id = $matches[1][0];
    }

    public function getVideoInfo()
    {
        if(!$this->video_id) throw new \App\LogicException('Video ID is null');

        if($this->app->debug && file_exists($this->app->cacheDir . $this->video_id))
        {
            parse_str(file_get_contents($this->app->cacheDir . $this->video_id), $response);
            return $response;
        }

        $response = \Requests::get("https://youtube.com/get_video_info?video_id={$this->video_id}");

        if(!$response->success) throw new \App\LogicException("Could not get video info [{$this->video_id}][{$response->status_code}]");

        parse_str($response->body, $result);

        if(!isset($result['status']) || $result['status'] !== 'ok')
        {
            $status    = $result['status']    ?? 'STATUS_NULL';
            $errorcode = $result['errorcode'] ?? 'ERRORCODE_NULL';

            throw new \App\LogicException("Video status not OK  [{$status}][{$errorcode}]");
        }

        if($this->app->debug)
        {
            file_put_contents($this->app->cacheDir . $this->video_id, $response->body);
        }

        return $result;
    }

    public function parseStreams()
    {
        $video_info = $this->getVideoInfo();

        if(!$video_info['url_encoded_fmt_stream_map']) throw new \App\LogicException('Missing url_encoded_fmt_stream_map key');

        $streams = explode(',', $video_info['url_encoded_fmt_stream_map']);


        array_walk($streams, function(&$s) { $s = urldecode($s); });


        print_r($streams);
    }
}