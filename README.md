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
    $response = \CurlPsr\Handler::run(
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

Functionality which *may* be added in future includes:

- Streaming PUT requests

Functionality which is not currently intended to be added includes:

- Early abort (client)
- Early abort (server)
- Non-PSR-7 responses
- Non-PSR-7 requests