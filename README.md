# Overview

This lets you run Curl requests with PSR-7 objects. This has a few benefits:

1. It keeps you away from Curl syntax, which is complicated when you're doing
   more than just downloading a file
2. If you're proxying, this requires less code as PSR-7 ServerRequest is
   compliant with PSR-7 Request - in other words, a modified version of your
   `$request` would usually work fine.
3. Sending the response back is simpler
4. Streaming responses is significantly more straightwarward

# Usage

```
    $handler = new \CurlPsr\Handler();
    $response = $handler->run(
        $request->withUri(
            $request->getUri()
                ->withPath("/")
                ->withHost("example.org")
                ->withScheme("http")
                ->withPort(80)
        ),
        true,
        10000
    );
```

The primary aim of this is to make JSON API proxies simpler.

# Limitations

This does not attempt to support every possible Curl feature nor everything that
can be expressed in PSR-7 requests/responses. Key functionality that should
always work includes:

- HEAD requests with properly retained headers
- GET requests to JSON endpoints which may or may not stream
- JSON-RPC request and response passthrough via POST
- DELETE requests
- OPTIONS requests
- PUT requests for short JSON resources

# Advanced Usage

If you want to do two or more requests in parallel, you can. To do this, you use:

```
    $handler = new \CurlPsr\Handler();
    $responses = $handler->withTimeout(10000)->runSimple(
        $request1,
        $request2
    );
    foreach($responses as $k => $response) {
        if($response->getBody()->getSize()) {
            echo "" . $response->getUri();
            echo "" . $response->getBody();
        }
    }
```

The return to `runSimple()` is in fact an iterator which will run over the same
objects multiple times: once for the headers, once or more for body chunks, and
once more when the transfer is known to be complete. The body attached to the
response will always be up-to-date with the latest packet, but won't report a
size until it's done.

If you want specific keys to make it easier to tell which response is which, you
can use `runMap()`:

```
    $handler = new \CurlPsr\Handler();
    $responses = $handler->withTimeout(10000)->runMap([
        "a" => $request1,
        "b" => $request2
    ]);
    foreach($responses as $k => $response) {
        if($response->getBody()->getSize()) {
            echo "" . $response->getUri();
            echo "" . $response->getBody();
        }
    }
```