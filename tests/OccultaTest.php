<?php

namespace Code16\Occulta\Tests;

use Aws\Kms\KmsClient;
use Aws\Result;
use Code16\Occulta\Occulta;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class OccultaTest extends TestCase
{
    #[Test]
    public function it_can_encrypt_a_value()
    {
        // Mock the KMS client
        $mockClient = Mockery::mock(KmsClient::class);
        $mockClient->shouldReceive('encrypt')
            ->once()
            ->with([
                'KeyId' => 'test-key-id',
                'Plaintext' => serialize('test-value'),
                'EncryptionContext' => ['app' => 'testing'],
            ])
            ->andReturn(new Result(['CiphertextBlob' => 'encrypted-data']));

        // Replace the KMS client in the Occulta instance
        $occulta = new Occulta();
        $reflectionClass = new \ReflectionClass($occulta);
        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($occulta, $mockClient);

        // Test the encrypt method
        $result = $occulta->encrypt('test-value');

        $this->assertEquals(base64_encode('encrypted-data'), $result);
    }

    #[Test]
    public function it_can_encrypt_a_file()
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_env_');
        file_put_contents($tempFile, 'TEST_KEY=test_value');

        // Mock the KMS client
        $mockClient = Mockery::mock(KmsClient::class);
        $mockClient->shouldReceive('generateDataKey')
            ->once()
            ->with([
                'KeyId' => 'test-key-id',
                'KeySpec' => 'AES_256',
            ])
            ->andReturn([
                'Plaintext' => random_bytes(32), // 256 bits key
                'CiphertextBlob' => 'encrypted-key-data',
            ]);

        // Replace the KMS client in the Occulta instance
        $occulta = new Occulta();
        $reflectionClass = new \ReflectionClass($occulta);
        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($occulta, $mockClient);

        // Test the encryptFile method
        $result = $occulta->encryptFile($tempFile);

        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals($tempFile.'.encrypted', $result['file']);
        $this->assertEquals($tempFile.'.key.encrypted', $result['key']);
        $this->assertFileExists($result['file']);
        $this->assertFileExists($result['key']);

        // Clean up
        unlink($tempFile);
        unlink($result['file']);
        unlink($result['key']);
    }

    #[Test]
    public function it_can_decrypt_a_file()
    {
        // Create a temporary file with encrypted content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_encrypted_env_');
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $key = random_bytes(32); // 256 bits key
        $encryptedContent = openssl_encrypt('TEST_KEY=test_value', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        file_put_contents($tempFile, $iv.$encryptedContent);

        // Mock the KMS client
        $mockClient = Mockery::mock(KmsClient::class);
        $mockClient->shouldReceive('decrypt')
            ->once()
            ->with([
                'CiphertextBlob' => 'encrypted-key-data',
                'EncryptionContext' => ['app' => 'testing'],
            ])
            ->andReturn(new Result(['Plaintext' => $key]));

        // Replace the KMS client in the Occulta instance
        $occulta = new Occulta();
        $reflectionClass = new \ReflectionClass($occulta);
        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($occulta, $mockClient);

        // Test the decrypt method
        $result = $occulta->decrypt('encrypted-key-data', $tempFile);

        $this->assertStringEndsWith('.decrypted', $result);
        $this->assertFileExists($result);
        $this->assertEquals('TEST_KEY=test_value', file_get_contents($result));

        // Clean up
        unlink($tempFile);
        unlink($result);
    }

    #[Test]
    public function it_throws_exception_when_file_does_not_exist()
    {
        $this->expectException(\InvalidArgumentException::class);

        $occulta = new Occulta();
        $occulta->encryptFile('/path/to/nonexistent/file');
    }

    #[Test]
    public function it_throws_exception_when_decryption_fails()
    {
        // Create a temporary file with invalid encrypted content
        $tempFile = tempnam(sys_get_temp_dir(), 'test_invalid_encrypted_env_');
        file_put_contents($tempFile, 'invalid-encrypted-content');

        // Mock the KMS client
        $mockClient = Mockery::mock(KmsClient::class);
        $mockClient->shouldReceive('decrypt')
            ->once()
            ->andReturn(new Result(['Plaintext' => 'invalid-key']));

        // Replace the KMS client in the Occulta instance
        $occulta = new Occulta();
        $reflectionClass = new \ReflectionClass($occulta);
        $reflectionProperty = $reflectionClass->getProperty('client');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($occulta, $mockClient);

        // Test the decrypt method
        $this->expectException(\RuntimeException::class);
        $occulta->decrypt('encrypted-key-data', $tempFile);

        // Clean up
        unlink($tempFile);
    }
}
