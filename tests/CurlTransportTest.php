<?php

declare(strict_types=1);

namespace Domotron\CloudAuth\Tests;

use Domotron\CloudAuth\Exception\TransportException;
use Domotron\CloudAuth\Http\CurlTransport;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real transport against PHP's built-in server (tests/echo-server.php).
 */
final class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        $port = 18090 + random_int(0, 500);
        self::$baseUrl = "http://127.0.0.1:$port";
        $command = sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg(__DIR__ . '/echo-server.php'));
        $server = proc_open($command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($server)) {
            self::markTestSkipped('cannot start the built-in server');
        }
        self::$server = $server;

        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }
        self::markTestSkipped('built-in server did not come up');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function testRequestIsSentAsIs(): void
    {
        $transport = new CurlTransport(5, 2, 'ua-test');
        $response = $transport->request('POST', self::$baseUrl . '/api/v1/user/session/abc?x=1', ['Authorization' => 'Bearer t', 'Content-Type' => 'application/json'], '{"a":1}');

        self::assertSame(201, $response->status);
        $echo = $response->json();
        self::assertSame('POST', $echo['method']);
        self::assertSame('/api/v1/user/session/abc?x=1', $echo['uri']);
        self::assertSame('Bearer t', $echo['headers']['authorization']);
        self::assertSame('application/json', $echo['headers']['content-type']);
        self::assertSame('ua-test', $echo['headers']['user-agent']);
        self::assertSame('{"a":1}', $echo['body']);
    }

    public function testStatusCodesAreReturnedNotThrown(): void
    {
        $transport = new CurlTransport();
        self::assertSame(404, $transport->request('GET', self::$baseUrl . '/status/404')->status);
        self::assertSame(500, $transport->request('DELETE', self::$baseUrl . '/status/500')->status);
    }

    public function testConnectionFailureIsATransportException(): void
    {
        $this->expectException(TransportException::class);
        (new CurlTransport(1, 1))->request('GET', 'http://127.0.0.1:1/');
    }
}
