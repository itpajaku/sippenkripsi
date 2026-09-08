<?php

namespace SippEnkripsi\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SippEnkripsi\SippEnkripsi;

class SippEnkripsiTest extends TestCase
{
    private string $defaultKey = 'sipp_secret_key_12345';

    public function testEncodeAndDecodeBasic(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);
        $perkaraId = '12345';

        $encoded = $enc->encode($perkaraId);
        $this->assertNotEmpty($encoded);
        $this->assertNotEquals($perkaraId, $encoded);

        $decoded = $enc->decode($encoded);
        $this->assertEquals($perkaraId, $decoded);
    }

    public function testEncodeWithMethodParameterKey(): void
    {
        $enc = new SippEnkripsi();
        $perkaraId = '987654';
        $customKey = 'another_custom_key';

        $encoded = $enc->encode($perkaraId, $customKey);
        $decoded = $enc->decode($encoded, $customKey);

        $this->assertEquals($perkaraId, $decoded);
    }

    public function testMissingKeyThrowsException(): void
    {
        $enc = new SippEnkripsi();

        $this->expectException(InvalidArgumentException::class);
        $enc->encode('12345');
    }

    public function testSetKeyFluent(): void
    {
        $enc = new SippEnkripsi();
        $enc->setKey('my_dynamic_key');

        $encoded = $enc->encode('54321');
        $decoded = $enc->decode($encoded);

        $this->assertEquals('54321', $decoded);
    }

    public function testMultipleEncryptionsProduceDifferentCiphertextDueToRandomIV(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);
        $perkaraId = '1001';

        $encoded1 = $enc->encode($perkaraId);
        $encoded2 = $enc->encode($perkaraId);
        $encoded3 = $enc->encode($perkaraId);

        // Ciphertexts must be different because of random IV and noise
        $this->assertNotEquals($encoded1, $encoded2);
        $this->assertNotEquals($encoded2, $encoded3);
        $this->assertNotEquals($encoded1, $encoded3);

        // But all must decode back to the same perkara_id
        $this->assertEquals($perkaraId, $enc->decode($encoded1));
        $this->assertEquals($perkaraId, $enc->decode($encoded2));
        $this->assertEquals($perkaraId, $enc->decode($encoded3));
    }

    public function testEncodeAndDecodeUrlSafe(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);
        $perkaraId = '12345';

        $encodedUrl = $enc->encodeUrlSafe($perkaraId);

        // Must not contain +, /, or =
        $this->assertStringNotContainsString('+', $encodedUrl);
        $this->assertStringNotContainsString('/', $encodedUrl);
        $this->assertStringNotContainsString('=', $encodedUrl);

        $decoded = $enc->decodeUrlSafe($encodedUrl);
        $this->assertEquals($perkaraId, $decoded);
    }

    public function testDecodeInvalidBase64ReturnsFalse(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);

        $this->assertFalse($enc->decode('not!valid@base64#'));
    }

    public function testDecodeWithWrongKeyDoesNotMatchOriginal(): void
    {
        $enc1 = new SippEnkripsi('correct_key');
        $enc2 = new SippEnkripsi('wrong_key');

        $encoded = $enc1->encode('12345');
        $decoded = $enc2->decode($encoded);

        $this->assertNotEquals('12345', $decoded);
    }

    public function testExactBlockSizeStrings(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);

        // Exactly 32 bytes (1 block)
        $string32 = str_repeat('A', 32);
        $encoded32 = $enc->encode($string32);
        $this->assertEquals($string32, $enc->decode($encoded32));

        // Exactly 64 bytes (2 blocks)
        $string64 = str_repeat('B', 64);
        $encoded64 = $enc->encode($string64);
        $this->assertEquals($string64, $enc->decode($encoded64));
    }

    public function testEmptyString(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);

        $encoded = $enc->encode('');
        $this->assertEquals('', $enc->decode($encoded));
    }

    public function testAliasesCompatibility(): void
    {
        $enc = new SippEnkripsi();
        $enc->set_key('key_alias');

        $this->assertEquals(md5('key_alias'), $enc->get_key());

        $encoded = $enc->encrypt('alias_test');
        $decoded = $enc->decrypt($encoded);

        $this->assertEquals('alias_test', $decoded);
    }

    public function testDecodeKnownCiphertextVector(): void
    {
        $key = 'my-fixed-sipp-key';
        $knownCiphertext = '3Kve3dupsKqv27Gw3K7art6xq7CqqLGsrN2s3anesNq7WErKfZPqmHyun7gt/vTAQQEsPvUOHlJ3Gne1E2NkvA==';

        $enc = new SippEnkripsi($key);
        $decoded = $enc->decode($knownCiphertext);

        $this->assertEquals('778899', $decoded);
    }

    public function testBase64WrappedEncodingAndDecoding(): void
    {
        $enc = new SippEnkripsi($this->defaultKey);
        $perkaraId = '35512';

        $wrapped = $enc->encodeBase64Wrapped($perkaraId);
        $this->assertNotEmpty($wrapped);

        // It must decode back to the same ID
        $decoded = $enc->decodeBase64Wrapped($wrapped);
        $this->assertEquals($perkaraId, $decoded);

        // Test alias
        $aliasWrapped = $enc->encodeBase64($perkaraId);
        $this->assertEquals($perkaraId, $enc->decodeBase64($aliasWrapped));
    }
}
