<?php

namespace Code16\Occulta;

use Aws\Kms\KmsClient;

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
        $this->keyId = config('encrypt-env-kms.key_id');
        $this->encryptionContext = config('encrypt-env-kms.context', []);
    }

    public function encrypt($value, $serialize = true)
    {
        return base64_encode($this->client->encrypt([
            'KeyId' => $this->keyId,
            'Plaintext' => $serialize ? serialize($value) : $value,
            'EncryptionContext' => $this->encryptionContext,
        ])->get('CiphertextBlob'));
    }
}
