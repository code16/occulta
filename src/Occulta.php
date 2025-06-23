<?php

namespace Code16\Occulta;

use Aws\Kms\KmsClient;
use Illuminate\Support\Facades\Storage;

class Occulta
{
    private KmsClient $client;

    private string $keyId;

    private array $encryptionContext;

    public function __construct()
    {
        $clientParams = [
            'version' => '2014-11-01',
        ];

        if (config('services.kms.key') && config('services.kms.secret')) {
            $clientParams['credentials'] = [
                'key' => config('services.kms.key'),
                'secret' => config('services.kms.secret'),
            ];
        }

        if (config('services.kms.region')) {
            $clientParams['region'] = config('services.kms.region');
        }

        $this->client = new KmsClient($clientParams);
        $this->keyId = config('occulta.key_id');
        $this->encryptionContext = config('occulta.context', []);
    }

    public function encrypt($value, $serialize = true)
    {
        return base64_encode($this->client->encrypt([
            'KeyId' => $this->keyId,
            'Plaintext' => $serialize ? serialize($value) : $value,
            'EncryptionContext' => $this->encryptionContext,
        ])->get('CiphertextBlob'));
    }

    public function encryptFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File does not exist: {$filePath}");
        }

        // Generating a data key with KMS
        $result = $this->client->generateDataKey([
            'KeyId' => $this->keyId,
            'KeySpec' => 'AES_256',
        ]);

        $plaintextKey = $result['Plaintext'];
        $ciphertextKey = $result['CiphertextBlob'];

        $originalContent = file_get_contents($filePath);

        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encryptedContent = openssl_encrypt(
            $originalContent,
            'aes-256-cbc',
            $plaintextKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        // as soon as encryption is done, we can unset the plaintext key to avoid memory leaks
        unset($plaintextKey);

        // Saving encrypted file
        $encryptedFilePath = $filePath.'.encrypted';
        // $storeFile = Storage::disk('local')->put($encryptedFilePath, $iv . $encryptedContent, ['throw' => true]);
        file_put_contents($encryptedFilePath, $iv.$encryptedContent);

        // Saving encrypted key
        $encryptedKeyPath = $filePath.'.key.encrypted';
        $ciphertextKeyBase64 = base64_encode($ciphertextKey);
        file_put_contents($encryptedKeyPath, $ciphertextKeyBase64);

        return [
            'file' => $encryptedFilePath,
            'key' => $encryptedKeyPath,
        ];
    }

    public function decrypt($key, $filePath)
    {
        $decrypted = $this->client->decrypt([
            'CiphertextBlob' => $key,
            'EncryptionContext' => $this->encryptionContext,
        ]);

        $plainTextKey = $decrypted->get('Plaintext');

        $encryptedFile = file_get_contents($filePath);

        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($encryptedFile, 0, $ivLength);
        $ciphertext = substr($encryptedFile, $ivLength);

        $decryptedContent = openssl_decrypt(
            $ciphertext,
            'aes-256-cbc',
            $plainTextKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decryptedContent === false) {
            throw new \RuntimeException('Decryption failed');
        }

        $outputPath = str($filePath)->replace('.encrypted', '')->toString().'.decrypted';
        file_put_contents($outputPath, $decryptedContent);

        return $outputPath;
    }
}
