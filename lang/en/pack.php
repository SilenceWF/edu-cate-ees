<?php

return [
    'installed' => 'Pack installed: :policies policies, :modules modules, :questions questions, :people_created new people.',
    'errors' => [
        'bad_zip' => 'The file is not a valid .zip.',
        'not_found' => 'Folder :path was not found.',
        'no_manifest' => 'No pack.yaml file was found in the pack.',
    ],
    'setup' => [
        'title' => 'First-time setup',
        'lead' => 'The platform needs a "company pack": a folder with your company\'s logo, policies, modules and questions.',
        'upload_title' => 'Upload your company pack',
        'upload_lead' => 'Zip the pack folder (see docs/PACK_GUIDE.md).',
        'upload' => 'Install pack',
        'demo_title' => 'Or try the demo',
        'demo_lead' => 'A fictional company with complete ISO 27001 / NIS2 content, to see how it works.',
        'demo' => 'Install demo',
        'cli' => 'Or from the command line:',
    ],
];
