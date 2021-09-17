<?php

declare(strict_types=1);

namespace LaminasTest\Diactoros;

use InvalidArgumentException;
use Laminas\Diactoros\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

use function count;

class MessageTraitTest extends TestCase
{
    /**
     * @var MessageInterface
     */
    protected $message;

    protected function setUp() : void
    {
        $this->message = new Request(null, null, $this->createMock(StreamInterface::class));
    }

    public function testProtocolHasAcceptableDefault(): void
    {
        $this->assertSame('1.1', $this->message->getProtocolVersion());
    }

    public function testProtocolMutatorReturnsCloneWithChanges(): void
    {
        $message = $this->message->withProtocolVersion('1.0');
        $this->assertNotSame($this->message, $message);
        $this->assertSame('1.0', $message->getProtocolVersion());
    }


    public function invalidProtocolVersionProvider(): array
    {
        return [
            'null'                 => [ null ],
            'true'                 => [ true ],
            'false'                => [ false ],
            'int'                  => [ 1 ],
            'float'                => [ 1.1 ],
            'array'                => [ ['1.1'] ],
            'stdClass'             => [ (object) [ 'version' => '1.0'] ],
            '1-without-minor'      => [ '1' ],
            '1-with-invalid-minor' => [ '1.2' ],
            '1-with-hotfix'        => [ '1.2.3' ],
        ];
    }

    /**
     * @dataProvider invalidProtocolVersionProvider
     */
    public function testWithProtocolVersionRaisesExceptionForInvalidVersion($version): void
    {
        $request = new Request();

        $this->expectException(InvalidArgumentException::class);

        $request->withProtocolVersion($version);
    }

    public function validProtocolVersionProvider(): array
    {
        return [
            '1.0' => [ '1.0' ],
            '1.1' => [ '1.1' ],
            '2'   => [ '2' ],
            '2.0' => [ '2.0' ],
        ];
    }

    /**
     * @dataProvider validProtocolVersionProvider
     */
    public function testWithProtocolVersionDoesntRaiseExceptionForValidVersion(string $version): void
    {
        $request = new Request();

        $request->withProtocolVersion($version);
        $this->addToAssertionCount(1);
    }

    public function testUsesStreamProvidedInConstructorAsBody(): void
    {
        $stream  = $this->createMock(StreamInterface::class);
        $message = new Request(null, null, $stream);
        $this->assertSame($stream, $message->getBody());
    }

    public function testBodyMutatorReturnsCloneWithChanges(): void
    {
        $stream  = $this->createMock(StreamInterface::class);
        $message = $this->message->withBody($stream);
        $this->assertNotSame($this->message, $message);
        $this->assertSame($stream, $message->getBody());
    }

    public function testGetHeaderReturnsHeaderValueAsArray(): void
    {
        $message = $this->message->withHeader('X-Foo', ['Foo', 'Bar']);
        $this->assertNotSame($this->message, $message);
        $this->assertSame(['Foo', 'Bar'], $message->getHeader('X-Foo'));
    }

    public function testGetHeaderLineReturnsHeaderValueAsCommaConcatenatedString(): void
    {
        $message = $this->message->withHeader('X-Foo', ['Foo', 'Bar']);
        $this->assertNotSame($this->message, $message);
        $this->assertSame('Foo,Bar', $message->getHeaderLine('X-Foo'));
    }

    public function testGetHeadersKeepsHeaderCaseSensitivity(): void
    {
        $message = $this->message->withHeader('X-Foo', ['Foo', 'Bar']);
        $this->assertNotSame($this->message, $message);
        $this->assertSame([ 'X-Foo' => [ 'Foo', 'Bar' ] ], $message->getHeaders());
    }

    public function testGetHeadersReturnsCaseWithWhichHeaderFirstRegistered(): void
    {
        $message = $this->message
            ->withHeader('X-Foo', 'Foo')
            ->withAddedHeader('x-foo', 'Bar');
        $this->assertNotSame($this->message, $message);
        $this->assertSame([ 'X-Foo' => [ 'Foo', 'Bar' ] ], $message->getHeaders());
    }

    public function testHasHeaderReturnsFalseIfHeaderIsNotPresent(): void
    {
        $this->assertFalse($this->message->hasHeader('X-Foo'));
    }

    public function testHasHeaderReturnsTrueIfHeaderIsPresent(): void
    {
        $message = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('X-Foo'));
    }

    public function testAddHeaderAppendsToExistingHeader(): void
    {
        $message  = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $message2 = $message->withAddedHeader('X-Foo', 'Bar');
        $this->assertNotSame($message, $message2);
        $this->assertSame('Foo,Bar', $message2->getHeaderLine('X-Foo'));
    }

    public function testCanRemoveHeaders(): void
    {
        $message = $this->message->withHeader('X-Foo', 'Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('x-foo'));
        $message2 = $message->withoutHeader('x-foo');
        $this->assertNotSame($this->message, $message2);
        $this->assertNotSame($message, $message2);
        $this->assertFalse($message2->hasHeader('X-Foo'));
    }

    public function testHeaderRemovalIsCaseInsensitive(): void
    {
        $message = $this->message
            ->withHeader('X-Foo', 'Foo')
            ->withAddedHeader('x-foo', 'Bar')
            ->withAddedHeader('X-FOO', 'Baz');
        $this->assertNotSame($this->message, $message);
        $this->assertTrue($message->hasHeader('x-foo'));

        $message2 = $message->withoutHeader('x-foo');
        $this->assertNotSame($this->message, $message2);
        $this->assertNotSame($message, $message2);
        $this->assertFalse($message2->hasHeader('X-Foo'));

        $headers = $message2->getHeaders();
        $this->assertSame(0, count($headers));
    }

    public function invalidGeneralHeaderValues(): array
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'array'  => [[ 'foo' => [ 'bar' ] ]],
            'object' => [(object) [ 'foo' => 'bar' ]],
        ];
    }

    /**
     * @dataProvider invalidGeneralHeaderValues
     */
    public function testWithHeaderRaisesExceptionForInvalidNestedHeaderValue($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');

        $this->message->withHeader('X-Foo', [ $value ]);
    }

    public function invalidHeaderValues(): array
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'object' => [(object) [ 'foo' => 'bar' ]],
        ];
    }

    /**
     * @dataProvider invalidHeaderValues
     */
    public function testWithHeaderRaisesExceptionForInvalidValueType($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header value');

        $this->message->withHeader('X-Foo', $value);
    }

    public function testWithHeaderReplacesDifferentCapitalization(): void
    {
        $this->message = $this->message->withHeader('X-Foo', ['foo']);
        $new = $this->message->withHeader('X-foo', ['bar']);
        $this->assertSame(['bar'], $new->getHeader('x-foo'));
        $this->assertSame(['X-foo' => ['bar']], $new->getHeaders());
    }

    /**
     * @dataProvider invalidGeneralHeaderValues
     */
    public function testWithAddedHeaderRaisesExceptionForNonStringNonArrayValue($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a string');

        $this->message->withAddedHeader('X-Foo', $value);
    }

    public function testWithoutHeaderDoesNothingIfHeaderDoesNotExist(): void
    {
        $this->assertFalse($this->message->hasHeader('X-Foo'));
        $message = $this->message->withoutHeader('X-Foo');
        $this->assertNotSame($this->message, $message);
        $this->assertFalse($message->hasHeader('X-Foo'));
    }

    public function testHeadersInitialization(): void
    {
        $headers = ['X-Foo' => ['bar']];
        $message = new Request(null, null, 'php://temp', $headers);
        $this->assertSame($headers, $message->getHeaders());
    }

    public function testGetHeaderReturnsAnEmptyArrayWhenHeaderDoesNotExist(): void
    {
        $this->assertSame([], $this->message->getHeader('X-Foo-Bar'));
    }

    public function testGetHeaderLineReturnsEmptyStringWhenHeaderDoesNotExist(): void
    {
        $this->assertEmpty($this->message->getHeaderLine('X-Foo-Bar'));
    }

    public function headersWithInjectionVectors(): array
    {
        return [
            'name-with-cr'           => ["X-Foo\r-Bar", 'value'],
            'name-with-lf'           => ["X-Foo\n-Bar", 'value'],
            'name-with-crlf'         => ["X-Foo\r\n-Bar", 'value'],
            'name-with-2crlf'        => ["X-Foo\r\n\r\n-Bar", 'value'],
            'value-with-cr'          => ['X-Foo-Bar', "value\rinjection"],
            'value-with-lf'          => ['X-Foo-Bar', "value\ninjection"],
            'value-with-crlf'        => ['X-Foo-Bar', "value\r\ninjection"],
            'value-with-2crlf'       => ['X-Foo-Bar', "value\r\n\r\ninjection"],
            'array-value-with-cr'    => ['X-Foo-Bar', ["value\rinjection"]],
            'array-value-with-lf'    => ['X-Foo-Bar', ["value\ninjection"]],
            'array-value-with-crlf'  => ['X-Foo-Bar', ["value\r\ninjection"]],
            'array-value-with-2crlf' => ['X-Foo-Bar', ["value\r\n\r\ninjection"]],
        ];
    }

    /**
     * @dataProvider headersWithInjectionVectors
     * @group ZF2015-04
     */
    public function testDoesNotAllowCRLFInjectionWhenCallingWithHeader($name, $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->message->withHeader($name, $value);
    }

    /**
     * @dataProvider headersWithInjectionVectors
     * @group ZF2015-04
     */
    public function testDoesNotAllowCRLFInjectionWhenCallingWithAddedHeader($name, $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->message->withAddedHeader($name, $value);
    }

    public function testWithHeaderAllowsHeaderContinuations(): void
    {
        $message = $this->message->withHeader('X-Foo-Bar', "value,\r\n second value");
        $this->assertSame("value,\r\n second value", $message->getHeaderLine('X-Foo-Bar'));
    }

    public function testWithAddedHeaderAllowsHeaderContinuations(): void
    {
        $message = $this->message->withAddedHeader('X-Foo-Bar', "value,\r\n second value");
        $this->assertSame("value,\r\n second value", $message->getHeaderLine('X-Foo-Bar'));
    }

    public function numericHeaderValuesProvider(): array
    {
        return [
            'integer' => [ 123 ],
            'float'   => [ 12.3 ],
        ];
    }

    /**
     * @dataProvider numericHeaderValuesProvider
     * @group 99
     */
    public function testWithHeaderShouldAllowIntegersAndFloats(float $value): void
    {
        $message = $this->message
            ->withHeader('X-Test-Array', [ $value ])
            ->withHeader('X-Test-Scalar', $value);

        $this->assertSame([
            'X-Test-Array'  => [ (string) $value ],
            'X-Test-Scalar' => [ (string) $value ],
        ], $message->getHeaders());
    }

    public function invalidHeaderValueTypes(): array
    {
        return [
            'null'   => [null],
            'true'   => [true],
            'false'  => [false],
            'object' => [(object) ['header' => ['foo', 'bar']]],
        ];
    }

    public function invalidArrayHeaderValues(): array
    {
        $values = $this->invalidHeaderValueTypes();
        $values['array'] = [['INVALID']];
        return $values;
    }

    /**
     * @dataProvider invalidArrayHeaderValues
     * @group 99
     */
    public function testWithHeaderShouldRaiseExceptionForInvalidHeaderValuesInArrays($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('header value type');

        $this->message->withHeader('X-Test-Array', [ $value ]);
    }

    /**
     * @dataProvider invalidHeaderValueTypes
     * @group 99
     */
    public function testWithHeaderShouldRaiseExceptionForInvalidHeaderScalarValues($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('header value type');

        $this->message->withHeader('X-Test-Scalar', $value);
    }
}
