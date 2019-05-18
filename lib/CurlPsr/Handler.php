<?php
namespace CurlPsr;
/**
 * Lets you call Curl via a PSR-7 request object, returning a PSR-7 response
 * object.
 */
class Handler {
    /**
     * @var int
     */
    const DEFAULT_TIMEOUT_MS = 2000;

    /**
     * Runs the request, returning an iterator which will first emit the headers
     * (from HTTP/1.1 200 OK to the final empty line) and then zero or more body chunks.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param bool $verify If false, TLS peer verification will be turned off
     * @param int $timeout_ms
     * @return iterable<string>
     */
    private static function runIterator(
        \Psr\Http\Message\RequestInterface $request,
        bool $verify = true,
        int $timeout_ms = DEFAULT_TIMEOUT_MS
    ): iterable {
        $ch = curl_init($request->getUri());
        $response = new \Celery\Response();

        $headers_finished = false;
        $header_content = "";
        $body_content = "";
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_TIMEOUT_MS => $timeout_ms,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_ENCODING => $request->getHeaderLine("Accept-Encoding"),
            CURLOPT_HTTPHEADER => array_map(
                function($name) use ($request) {
                    return $request->getHeaderLine($name);
                },
                array_keys($request->getHeaders())
            ),
            CURLOPT_POSTFIELDS => "" . $request->getBody(),
            CURLOPT_HEADERFUNCTION => function(
                $ch,
                $header_data
            ) use (
                &$header_content
            ) {
                $header_content .= $header_data;
                return strlen($header_data);
            },
            CURLOPT_WRITEFUNCTION => function(
                $ch,
                $data
            ) use (
                &$headers_finished,
                &$body_content
            ) {
                if(!$headers_finished) {
                    $headers_finished = true;
                }
                $body_content .= $data;
                return strlen($data);
            },
        ]);
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);
        do {
            $curl_code = curl_multi_exec($mh, $still_running);
        } while($curl_code == CURLM_CALL_MULTI_PERFORM);

        while($still_running and $curl_code == CURLM_OK) {
            if(curl_multi_select($mh) != -1) {
                do {
                    $curl_code = curl_multi_exec($mh, $still_running);
                } while($curl_code == CURLM_CALL_MULTI_PERFORM);
            } else {
                $curl_code = curl_multi_exec($mh, $still_running);
            }
            if($headers_finished or !$still_running) {
                static $first = true;
                if($first) {
                    $first = false;
                    yield $header_content;
                }
                yield $body_content;
            }
        }
        if(curl_error($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
    }

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
        int $timeout_ms = DEFAULT_TIMEOUT_MS
    ): \Psr\Http\Message\ResponseInterface {
        return new \Celery\Response(
            self::runIterator($request, $verify, $timeout_ms)
        );
    }
}
