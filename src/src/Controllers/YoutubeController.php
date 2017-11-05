<?php

namespace App\Controllers;

class YoutubeController extends GenericController
{
    private $video_id;

    public function parseUri(string $uri)
    {
        $pattern = "/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtu\.be|youtube\.com(?:\/watch\?v=|\/v\/|\/&v=\/))([\w-]{10,12})$/";

        preg_match_all($pattern, $uri, $matches) or $this->throwLogicalException('Invalid youtube URI');

        $this->video_id = $matches[1][0];

        return $this->video_id;
    }

    public function download()
    {
        $streams = $this->parseStreams();
        $stream  = $this->getOptimalStream($streams);

        $url  = $stream['url'] ?? $this->throwLogicalException('Missing url parameter in video_response stream object');
        preg_match("/signature=([\w]+\.[\w]+)/", $url, $out);
        $usig = $out[1]        ?? null;
        $csig = $stream['s']   ?? null;

        !$csig && !$usig and $this->throwLogicalException('Missing both ciphered and unciphered signature parameters in video_response stream object');

        $this->isVideoTooLong($stream) and $this->throwLogicalException("Video length cannot exceed {$this->app->maxVideoLength} minutes");

        if(!$usig)
        {
            $jsFilecontent = $this->getBaseJsFileContent();

            $functions = $this->getCipherFunctions($jsFilecontent);
            $t_name    = $this->getTranformationObjectName($functions);
            $map       = $this->getCipherMap($jsFilecontent, $t_name);

            $usig = $this->decipherSignature($csig, $functions, $map);
        }

        $downloadUri = "{$url}&signature={$usig}";

        $videoHash = md5($usig);

        $this->downloadVideo($downloadUri, $videoHash);

        return $videoHash;
    }

    public function convert($id)
    {
        $response = \Requests::post("http://gateway:8080/function/slidely_ffmpeg", [], $id,['timeout' => 600]);

        $response->success or $this->throwLogicalException('Could not convert video to audio', $response->body);
    }

    public function parseStreams()
    {
        $video_info = $this->getVideoInfo();

        $video_info['url_encoded_fmt_stream_map'] ?? $this->throwLogicalException('Missing url_encoded_fmt_stream_map key');

        $streams = explode(',', $video_info['url_encoded_fmt_stream_map']);

        count($streams) or $this->throwLogicalException('No streams found?? Is it even possible??');

        $streams = $this->decodeStringQueryStrings($streams);

        return $streams;
    }

    private function isVideoTooLong($stream)
    {
        preg_match("/dur=([0-9]+)\.?/", $stream['url'], $out) or $this->throwLogicalException('Cannot determine video length');
        $len = $out[1];

        return $len > $this->app->maxVideoLength * 60;
    }

    private function downloadVideo($uri, $id)
    {
        $response = \Requests::get($uri, [], ['filename' => "{$this->app->videoDir}{$id}", 'timeout' => 300000]);

        $response->success or $this->throwLogicalException("Could not download video [{$uri}][{$response->status_code}]");
    }

    private function getOptimalStream($streams)
    {
        // I'm looking for videos with itag 18 or 22 if not found
        // All other itags are not supported rn as 99% of the videos should
        // contain either of those

        foreach($streams as $stream) if($stream['itag'] == 18) return $stream;
        foreach($streams as $stream) if($stream['itag'] == 22) return $stream;

        $this->throwLogicalException('No stream with itag 18/22 found');
    }

    private function decodeStringQueryStrings($streams)
    {
        array_walk($streams, function(&$i){ parse_str($i, $arr); array_walk($arr, function(&$x) { $x = urldecode($x); }); $i = $arr; });

        return $streams;
    }

    private function getVideoInfo()
    {
        $this->video_id or $this->throwLogicalException('Video ID is null');

        if($this->app->debug && file_exists($this->app->cacheDir . $this->video_id))
        {
            parse_str(file_get_contents($this->app->cacheDir . $this->video_id), $response);
            return $response;
        }

        $watchurl = urlencode("https://www.youtube.com/watch?v={$this->video_id}");

        $fullurl = "https://youtube.com/get_video_info?video_id={$this->video_id}&ps=default&eurl=&gl=US&hl=en&eurl={$watchurl}";

        $response = \Requests::get($fullurl);

        $response->success or $this->throwLogicalException("Could not get video info [{$this->video_id}][{$response->status_code}]");

        parse_str($response->body, $result);

        if(!isset($result['status']) || $result['status'] !== 'ok')
        {
            $status    = $result['status']    ?? 'STATUS_NULL';
            $errorcode = $result['errorcode'] ?? 'ERRORCODE_NULL';

            $this->throwLogicalException("Video status not OK  [{$status}][{$errorcode}]");
        }

        if($this->app->debug)
        {
            file_put_contents($this->app->cacheDir . $this->video_id, $response->body);
        }

        return $result;
    }

    private function decipherSignature($cipheredSignature, $cipherFunctions, $map)
    {
        $a = str_split($cipheredSignature);

        foreach($cipherFunctions as $function)
        {
            preg_match("/\.([a-zA-Z0-9]+?)\((?:[a-zA-Z0-9]+?)\,([0-9]+?)\)/", $function, $out) or $this->throwLogicalException('Could not parse function call');

            $functionId    = $out[1];
            $functionParam = $out[2];

            $this->{"_{$map[$functionId]}"}($a, $functionParam);
        }

        return implode('', $a);
    }

    private function getBaseJsFileContent()
    {
        $response = \Requests::get("https://youtube.com/watch?v={$this->video_id}");

        $response->success or $this->throwLogicalException("Could not get video page of [{$this->video_id}][{$response->status_code}]");

        //<script src="/yts/jsbin/player-vflg6eF8s/en_US/base.js"  name="player/base" ></script>
        $pattern = "/\/yts\/.+?player.+?base\.js/m";

        preg_match_all($pattern, $response->body, $matches) or $this->throwLogicalException('Could not find base.js file location');

        $response = \Requests::get("https://youtube.com{$matches[0][0]}");

        $response->success or $this->throwLogicalException("Could not get base.js file [{$matches[0][0]}][{$response->status_code}]");

        return $response->body;
    }

    private function getCipherFunctions($script)
    {
        // Get primary function name
        preg_match("/\"signature\",\s?([a-zA-Z0-9$]+)\(/", $script, $out)
            or $this->throwLogicalException('Could not find primary decipher function');

        $pFunc = $out[1];

        // Get cipher transformation functions
        preg_match("/{$pFunc}=function\(\w\){[a-z=\.\(\"\)]*;(.*);(?:.+)}/", $script, $out)
            or $this->throwLogicalException('Could not get transformation functions');

        $transformationFunctions = explode(';', $out[1]);

        return $transformationFunctions;
    }

    private function getTranformationObjectName($transformationFunctions)
    {
        // Get transformation object name
        preg_match("/([a-zA-Z0-9$]+?)\./", $transformationFunctions[0], $out)
            or $this->throwLogicalException('Could not get transformation object name');

        $tObjName = $out[1];

        return $tObjName;
    }

    private function getCipherMap($script, $transformation_object_name)
    {
        // Get tranformation object (that contains declarations for those functions)
        preg_match("/var {$transformation_object_name}=\{(.+?)\};/ms", $script, $out)
            or $this->throwLogicalException('Could not get transformation object');

        $tObj = explode(',_n_', trim(str_replace("\n", '_n_', $out[1])));

        $map = [];

        foreach($tObj as $tFunc)
        {
            $x = explode(':', $tFunc);
            $fName = $x[0];
            $fBody = $x[1];

            // Extremly vulnerable -- it will likely stop working in the future
            preg_match("/length/", $fBody) and $map[$fName] = 'swap';
            preg_match("/splice|slice/", $fBody) and $map[$fName] = 'splice';
            preg_match("/reverse/", $fBody) and $map[$fName] = 'reverse';

            $map[$fName] ?? $this->throwLogicalException('Unrecognized transformation function');
        }

        return $map;
    }

    private function _swap(&$a, $b)
    {
        $c = $a[0]; $a[0] = $a[$b % count($a)]; $a[$b] = $c;
    }

    private function _reverse(&$a, $_)
    {
        $a = array_reverse($a);
    }

    private function _splice(&$a, $b)
    {
        array_splice($a, 0, $b);
    }
}
