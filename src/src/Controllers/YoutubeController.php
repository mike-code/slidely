<?php

namespace App\Controllers;

class YoutubeController
{
    private $video_id;
    private $app;
    private $logger;

    function __construct($app, $logger)
    {
        $this->app    = $app;
        $this->logger = $logger;
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

        $response = \Requests::get("https://youtube.com/watch?v={$this->video_id}");

        if(!$response->success) throw new \App\LogicException("Could not get video page of [{$this->video_id}][{$response->status_code}]");

        if(!preg_match("/\W['\"]?t['\"]?: ?['\"](.+?)['\"]/", $response->body, $out))
            throw new \App\LogicException("Could not get some-unknown-boolean [{$this->video_id}][{$response->status_code}]");

        $unknown = $out[1];

        $watchurl = urlencode("https://www.youtube.com/watch?v={$this->video_id}");

        $response = \Requests::get("https://youtube.com/get_video_info?video_id={$this->video_id}&el=\$el&ps=default&hl=en_US&eurl={$watchurl}&t={$unknown}");

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

        //array_walk($streams, function(&$s) { $s = urldecode($s); });

        return $streams;
    }

    public function dl()
    {
        $streams = $this->parseStreams();

        parse_str($streams[0], $result);

        echo 'URL:', PHP_EOL;
        echo $result['url'], PHP_EOL;
        echo 'SIG:', PHP_EOL;
        $result['s'] = '9B2F8B22A54C6E6AC09CEB01D905AA3D494FBEAD.1F321FEC9F8A56CDD0E27D7822E828F532C22760760';
        echo $result['s'], PHP_EOL;
        // echo $MY_S, PHP_EOL;

        $jsFilecontent = $this->getBaseJsFileContent();

        file_put_contents($this->app->cacheDir . "js.js", $jsFilecontent);

        $functions = $this->getCipherFunctions($jsFilecontent);
        $t_name    = $this->getTranformationObjectName($functions);
        $map       = $this->getCipherMap($jsFilecontent, $t_name);

        $sig = $this->decipherSignature($result['s'], $functions, $map);

        echo 'FULL:', PHP_EOL;
        echo $result['url'] . '&signature=' . $sig, PHP_EOL;
    }

    private function decipherSignature($cipheredSignature, $cipherFunctions, $map)
    {
        $a = str_split($cipheredSignature);

        foreach($cipherFunctions as $function)
        {
            if(!preg_match("/\.([a-zA-Z0-9]+?)\((?:[a-zA-Z0-9]+?)\,([0-9]+?)\)/", $function, $out))
                throw new \App\LogicException('Could not parse function call');

            $functionId    = $out[1];
            $functionParam = $out[2];

            $this->{"_{$map[$functionId]}"}($a, $functionParam);
        }

        return implode('', $a);
    }

    private function getBaseJsFileContent()
    {
        $response = \Requests::get("https://youtube.com/watch?v={$this->video_id}");

        if(!$response->success) throw new \App\LogicException("Could not get video page of [{$this->video_id}][{$response->status_code}]");

        //<script src="/yts/jsbin/player-vflg6eF8s/en_US/base.js"  name="player/base" ></script>
        $pattern = "/\/yts\/.+?player.+?base\.js/m";

        if(!preg_match_all($pattern, $response->body, $matches)) throw new \App\LogicException('Could not find base.js file location');

        $response = \Requests::get("https://youtube.com{$matches[0][0]}");

        if(!$response->success) throw new \App\LogicException("Could not get base.js file [{$matches[0][0]}][{$response->status_code}]");

        return $response->body;
    }

    private function getCipherFunctions($script)
    {
        // Get primary function name
        if(!preg_match("/\"signature\",\s?([a-zA-Z0-9$]+)\(/", $script, $out))
            throw new \App\LogicException('Could not find primary decipher function');

        $pFunc = $out[1];

        // Get cipher transformation functions
        if(!preg_match("/{$pFunc}=function\(\w\){[a-z=\.\(\"\)]*;(.*);(?:.+)}/", $script, $out))
            throw new \App\LogicException('Could not get transformation functions');

        $transformationFunctions = explode(';', $out[1]);

        return $transformationFunctions;
    }

    private function getTranformationObjectName($transformationFunctions)
    {
        // Get transformation object name
        if(!preg_match("/([a-zA-Z0-9$]+?)\./", $transformationFunctions[0], $out))
            throw new \App\LogicException('Could not get transformation object name');

        $tObjName = $out[1];

        return $tObjName;
    }

    private function getCipherMap($script, $transformation_object_name)
    {
        // Get tranformation object (that contains declarations for those functions)
        if(!preg_match("/var {$transformation_object_name}=\{(.+?)\};/ms", $script, $out))
            throw new \App\LogicException('Could not get transformation object');

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

            if(!isset($map[$fName]))
                throw new \App\LogicException('Unrecognized transformation function');
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
