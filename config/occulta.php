<?php
// config for Code16/Occulta
return [
    // kms key id as seen in aws's kms dashboard (usually it looks like uuid)
    'key_id' => '',

    // Associative array of custom encryption's context
    // warning: when changed you won't be able to decrypt previously encrypted data
    'context' => [
        // 'my_secret_key' => 'my_secret_value'
    ],

    'should_compress' => env('OCCULTA_SHOULD_COMPRESS', false),

    'destination_disk' => '',

    'number_of_encrypted_dotenv_to_keep_when_cleaning_up' => env('NUMBER_OF_ENCRYPTED_DOTENV_TO_KEEP_WHEN_CLEANING_UP', 7),

];
