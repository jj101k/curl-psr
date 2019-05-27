<?php
require_once "vendor/autoload.php";
/**
 * This does the main high-level testing
 */
class HandlerTest extends \PHPUnit\Framework\TestCase {
    /**
     * All tests
     */
    public function test() {
        $request = (new \Celery\Request())
            ->withMethod("GET");
        $handler = new \CurlPsr\Handler();
        $responses = $handler->withTimeout(10000)->runSimple(
            $request->withUri(
                $request->getUri()
                    ->withPath("/")
                    ->withHost("example.org")
                    ->withScheme("http")
                    ->withPort(80)
            )
        );
        foreach($responses as $r) {
            $response = $r;
        }
        $this->assertNotEmpty(
            "" . $response->getBody(),
            "Response returned something"
        );
        $this->assertRegExp(
            "#text/html#",
            $response->getHeaderLine("Content-Type"),
            "Response had the expected MIME type"
        );

        $responses = $handler->withTimeout(10000)->runSimple(
            $request->withUri(
                $request->getUri()
                    ->withPath("/")
                    ->withHost("example.org")
                    ->withScheme("http")
                    ->withPort(80)
            ),
            $request->withUri(
                $request->getUri()
                    ->withPath("/")
                    ->withHost("google.com")
                    ->withScheme("http")
                    ->withPort(80)
            )
        );
        $response_objects = [];
        foreach($responses as $k => $r) {
            $response_objects[$k] = $r;
        }
        $this->assertNotEmpty(
            "" . $response_objects[0]->getBody(),
            "Response returned something"
        );
        $this->assertRegExp(
            "#text/html#",
            $response_objects[0]->getHeaderLine("Content-Type"),
            "Response had the expected MIME type"
        );
        $this->assertNotEmpty(
            "" . $response_objects[1]->getBody(),
            "Response returned something"
        );
        $this->assertRegExp(
            "#text/html#",
            $response_objects[1]->getHeaderLine("Content-Type"),
            "Response had the expected MIME type"
        );
        $this->assertNotSame(
            "" . $response_objects[0]->getBody(),
            "" . $response_objects[1]->getBody(),
            "Returned two different responses"
        );
    }
}