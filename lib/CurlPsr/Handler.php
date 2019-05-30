<?php
namespace CurlPsr;
/**
 * Lets you call Curl via a PSR-7 request object, returning a PSR-7 response
 * object.
 */
class Handler {
    /**
     * Runs the request, returning a response object.
     *
     * The request is intended to just be a PSR-7 Request object, but a PSR-7
     * ServerRequest object will typically work fine too.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param bool $verify If false, TLS peer verification will be turned off
     * @param int $timeout_ms
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function run(
        \Psr\Http\Message\RequestInterface $request,
        bool $verify = true,
        int $timeout_ms = 2000
    ): \Psr\Http\Message\ResponseInterface {
        $handler = new self();
        $responses = $handler
            ->withTLSVerification($verify)
            ->withTimeout($timeout_ms)
            ->runSimple($request);
        foreach($responses as $r) {
            $response = $r;
        }
        return $response;
    }

    /**
     * @property int
     */
    private $timeout = 2000;

    /**
     * @property bool
     */
    private $tlsVerification = true;

    /**
     * Runs the request, returning an iterator which will first emit the headers
     * (from HTTP/1.1 200 OK to the final empty line) and then zero or more body chunks.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return iterable<string>
     */
    protected function runIterator(
        \Psr\Http\Message\RequestInterface ...$requests
    ): iterable {
        $body_contents = [];
        $header_contents = [];
        $headers_finished = [];
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_MAX_TOTAL_CONNECTIONS, 5);
        foreach($requests as $k => $request) {
            $ch = curl_init($request->getUri());
            $response = new \Celery\Response();

            $headers_finished[$k] = false;
            $header_contents[$k] = "";
            $body_contents[$k] = "";
            curl_setopt_array($ch, [
                CURLOPT_SSL_VERIFYPEER => $this->tlsVerification,
                CURLOPT_TIMEOUT_MS => $this->timeout,
                CURLOPT_ENCODING => $request->getHeaderLine("Accept-Encoding"),
                CURLOPT_HTTPHEADER => array_merge(["Expect:"], array_map(
                    function($name) use ($request) {
                        return "{$name}: " . $request->getHeaderLine($name);
                    },
                    array_keys($request->getHeaders())
                )),
                CURLOPT_HEADERFUNCTION => function(
                    $ch,
                    $header_data
                ) use (
                    &$header_contents,
                    $k
                ) {
                    if($this->debug) {
                        error_log("Header chunk on {$k}");
                    }
                    $header_contents[$k] .= $header_data;
                    return strlen($header_data);
                },
                CURLOPT_PRIVATE => $k,
                CURLOPT_WRITEFUNCTION => function(
                    $ch,
                    $data
                ) use (
                    &$headers_finished,
                    &$body_contents,
                    $k
                ) {
                    if($this->debug) {
                        error_log("Body chunk on {$k}");
                    }
                    if(!$headers_finished[$k]) {
                        $headers_finished[$k] = true;
                    }
                    $body_contents[$k] .= $data;
                    return strlen($data);
                },
            ]);
            switch($request->getMethod()) {
                case "HEAD":
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    // Fall through
                case "GET":
                    // Do nothing
                    break;
                case "POST":
                    // Fall through
                case "PUT":
                    curl_setopt($ch, CURLOPT_POSTFIELDS, "" . $request->getBody());
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
                    break;
                default:
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
            }
            curl_multi_add_handle($mh, $ch);
        }
        do {
            $curl_code = curl_multi_exec($mh, $still_running);
        } while($curl_code == CURLM_CALL_MULTI_PERFORM);

        $sent = [];
        $yield_complete = [];
        $receive_complete = [];
        while($still_running and $curl_code == CURLM_OK) {
            if($this->debug) {
                error_log("While top");
            }
            if(curl_multi_select($mh) != -1) {
                do {
                    $curl_code = curl_multi_exec($mh, $still_running);
                } while($curl_code == CURLM_CALL_MULTI_PERFORM);
            } else {
                $curl_code = curl_multi_exec($mh, $still_running);
            }
            foreach($requests as $k => $request) {
                if(in_array($k, $yield_complete)) {
                    continue;
                }
                if($headers_finished[$k] or !$still_running) {
                    if(!in_array($k, $sent)) {
                        if($this->debug) {
                            error_log("Headers on {$k}");
                        }
                        $sent[] = $k;
                        if(curl_error($ch)) {
                            throw new \Exception(curl_error($ch));
                        }
                        $first = false;
                        yield $k => $header_contents[$k];
                    }
                    if($body_contents[$k] != "") {
                        if($this->debug) {
                            error_log("Body on {$k}");
                        }
                        yield $k => $body_contents[$k];
                        $body_contents[$k] = "";
                    }
                    if(in_array($k, $receive_complete)) {
                        if($this->debug) {
                            error_log("Yield complete on {$k}");
                        }
                        $yield_complete[] = $k;
                        yield $k => "";
                    }
                }
            }
            do {
                $info = curl_multi_info_read($mh);
                if($info and $info["msg"] == CURLMSG_DONE) {
                    $k = curl_getinfo($info["handle"], CURLINFO_PRIVATE);
                    if($this->debug) {
                        error_log("Receive complete on {$k}");
                    }
                    $receive_complete[] = $k;
                    if(!$still_running) {
                        if($this->debug) {
                            error_log("Yield complete on {$k}");
                        }
                        $yield_complete[] = $k;
                        yield $k => "";
                    }
                }
            } while($info !== false);
        }
        if(curl_error($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
    }

    /**
     * @property bool
     */
    public $debug = false;

    /**
     * Runs the request, returning a response object.
     *
     * The request is intended to just be a PSR-7 Request object, but a PSR-7
     * ServerRequest object will typically work fine too.
     *
     * @param array<\Psr\Http\Message\RequestInterface> $requests
     * @return iterable<mixed, \Psr\Http\Message\ResponseInterface>
     */
    public function runMap(
        array $requests
    ): iterable {
        $responses = [];
        $write_bodies = [];
        foreach($this->runIterator(...$requests) as $k => $content) {
            if(array_key_exists($k, $responses)) {
                $write_bodies[$k]->write($content);
            } else {
                $responses[$k] = new \Celery\Response($content);
                $write_bodies[$k] = clone($responses[$k]->getBody());
            }
            if(!strlen($content)) {
                $write_bodies[$k]->seek(0, SEEK_END);
                $responses[$k]->getBody()->setSize(
                    $write_bodies[$k]->tell()
                );
            }
            yield $k => $responses[$k];
        }
        foreach(array_keys($responses) as $k) {
            $write_bodies[$k]->seek(0, SEEK_END);
            $responses[$k]->getBody()->setSize(
                $write_bodies[$k]->tell()
            );
        }
    }

    /**
     * Runs the request, returning a response object.
     *
     * The request is intended to just be a PSR-7 Request object, but a PSR-7
     * ServerRequest object will typically work fine too.
     *
     * @param \Psr\Http\Message\RequestInterface $requests,...
     * @return iterable<\Psr\Http\Message\ResponseInterface>
     */
    public function runSimple(
        \Psr\Http\Message\RequestInterface ...$requests
    ): iterable {
        return $this->runMap($requests);
    }

    /**
     * @param int $timeout Timeout in milliseconds
     * @return self
     */
    public function withTimeout(int $timeout): self {
        $new = clone($this);
        $new->timeout = $timeout;
        return $new;
    }

    /**
     * @param bool $verify If false, TLS peer verification will be turned off
     * @return self
     */
    public function withTLSVerification(bool $verify): self {
        $new = clone($this);
        $new->tlsVerification = $verify;
        return $new;
    }
}
