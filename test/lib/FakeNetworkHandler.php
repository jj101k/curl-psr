<?php
class FakeNetworkHandler extends \CurlPsr\Handler {

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
        foreach($requests as $k => $request) {
            yield $k => "HTTP/1.1 200 OK\r\n" .
                "Content-Type: " . $request->getHeaderLine("Content-Type") . "\r\n" .
                "Content-Length: " . $request->getBody()->getSize() . "\r\n" .
                "\r\n";
        }
        foreach($requests as $k => $request) {
            yield $k => "" . $request->getBody();
            yield $k => "";
        }
    }
}