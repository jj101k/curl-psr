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
        $response = $handler->withTimeout(10000)->runSimple(
            $request->withUri(
                $request->getUri()
                    ->withPath("/")
                    ->withHost("example.org")
                    ->withScheme("http")
                    ->withPort(80)
            )
        );
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