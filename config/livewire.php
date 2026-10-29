<?php

// Jen to, co se liší od výchozího nastavení Livewire (vendor/livewire/livewire/config/livewire.php).
// Laravel slučuje jen první úroveň, proto je tu celé `temporary_file_upload`.
return [
    'temporary_file_upload' => [
        'disk' => null,
        // Archiv mozku bere soubory do 30 MB (výchozí strop Livewire je 12 MB). Menší limit
        // jednotlivého pole hlídá jeho maxSize(), nad tím ještě php.ini (ImageUpload::maxKilobytes()).
        'rules' => ['required', 'file', 'max:30720'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];
