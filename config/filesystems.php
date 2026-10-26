<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'attendance_photos' => [
            'driver' => 'local',
            'root' => storage_path('app/private/attendance-photos'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/attendance-photos',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // No 'serve'/'url' here on purpose — unlike attendance_photos,
        // these files are never reached through a signed URL. Every
        // access goes through LeaveRequestAttachmentController, which
        // checks the requester's identity (owner/HRD/Manager) on every
        // single request, not just once when a link is generated.
        'leave_attachments' => [
            'driver' => 'local',
            'root' => storage_path('app/private/leave-attachments'),
            'throw' => false,
            'report' => false,
        ],

        // Same reasoning as leave_attachments — never a direct/signed URL,
        // only ever served through AttendanceReviewEvidenceController after
        // an HRD-role check.
        'attendance_evidence' => [
            'driver' => 'local',
            'root' => storage_path('app/private/attendance-evidence'),
            'throw' => false,
            'report' => false,
        ],

        // Same reasoning as leave_attachments/attendance_evidence — only
        // ever served through PaymentProofController after a Finance-role
        // check, never a direct/signed URL.
        'payment_proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/payment-proofs'),
            'throw' => false,
            'report' => false,
        ],

        // Same reasoning as leave_attachments — never a direct/signed URL,
        // only ever served through Hrd\EmployeeDocumentController, which
        // the hrd route group already gates to role=hrd.
        'employee_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/employee-documents'),
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
