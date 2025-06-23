<?php

// config for Code16/Occulta
return [
    // kms key id as seen in aws's kms dashboard (usually it looks like an uuid)
    'key_id' => '',

    // Associative array of custom encryption's context
    // warning: when changed you won't be able to decrypt previously encrypted data
    'context' => [
        // 'my_secret_key' => 'my_secret_value'
    ],

    'destination_disk' => '',
    'destination_path' => 'dotenv/',

    // If you want to backup an env file with a suffix such as .env.production, you can set this to your desired suffix
    'env_suffix' => null, // eg: 'production'

    'number_of_encrypted_dotenv_to_keep_when_cleaning_up' => env('NUMBER_OF_ENCRYPTED_DOTENV_TO_KEEP_WHEN_CLEANING_UP', 7),

];
