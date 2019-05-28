<?php
require_once "vendor/autoload.php";
/**
 * This does the main high-level testing
 */
class OnlineHandlerTest extends \PHPUnit\Framework\TestCase {
    /**
     * General tests
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
        if(false) {
            // This doesn't work because example.org actually sends the wrong
            // Content-Length currently
            $this->assertSame(
                +$response->getHeaderLine("Content-Length"),
                $response->getBody()->getSize(),
                "Response size is set correctly"
            );
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
    }
}