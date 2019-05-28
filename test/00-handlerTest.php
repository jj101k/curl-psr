<?php
require_once "vendor/autoload.php";
/**
 * This does the main mid-level testing
 */
class HandlerTest extends \PHPUnit\Framework\TestCase {
    /**
     * General tests
     */
    public function test() {
        require_once "test/lib/FakeNetworkHandler.php";
        $request = (new \Celery\Request())
            ->withMethod("POST")
            ->withHeader("Content-Type", "text/html");
        $handler = new \FakeNetworkHandler();
        $a = new \Celery\Body();
        $a->write("ewfsdfdafddsf");
        $b = new \Celery\Body();
        $b->write("dsfsdfd");
        $responses = $handler->withTimeout(10000)->runSimple(
            $request->withUri(
                $request->getUri()
                    ->withPath("/" . rand())
                    ->withHost("localhost")
                    ->withScheme("http")
                    ->withPort(80)
            )->withBody($a),
            $request->withUri(
                $request->getUri()
                    ->withPath("/" . rand())
                    ->withHost("localhost")
                    ->withScheme("http")
                    ->withPort(80)
            )->withBody($b)
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