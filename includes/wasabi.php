<?php
use Aws\S3\S3Client;

function shutterpress_generate_temp_download_url($key, $expires = '+15 minutes') {
    // Use your existing credential constants
    $access_key = defined('DBI_AWS_ACCESS_KEY_ID') ? DBI_AWS_ACCESS_KEY_ID : '';
    $secret_key = defined('DBI_AWS_SECRET_ACCESS_KEY') ? DBI_AWS_SECRET_ACCESS_KEY : '';

    if (!$access_key || !$secret_key) {
        error_log('ShutterPress: Wasabi credentials not found');
        return false;
    }

    try {
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'ap-northeast-2',
            'endpoint'    => 'https://s3.ap-northeast-2.wasabisys.com',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $access_key,
                'secret' => $secret_key,
            ],
        ]);

        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => 'designfabricmedia',
            'Key'    => $key,
        ]);

        $request = $s3->createPresignedRequest($cmd, $expires);

        return (string) $request->getUri();
    } catch (Exception $e) {
        error_log('Wasabi Signed URL Error: ' . $e->getMessage());
        return false;
    }
}