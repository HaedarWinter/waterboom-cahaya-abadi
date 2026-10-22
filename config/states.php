<?php
declare(strict_types=1);

return [
    'MENUNGGU_BAYAR' => [
        'label' => 'Menunggu Bayar',
        'transitions' => [
            'MENUNGGU_VERIFIKASI' => [
                'actor' => 'Pengunjung',
                'permission' => null,
                'guard' => 'belum_lewat_deadline_dan_file_valid',
                'effects' => ['create_payment_attempt', 'create_payment_proof'],
            ],
            'KEDALUWARSA' => [
                'actor' => 'Sistem',
                'permission' => null,
                'guard' => 'payment_deadline_lewat',
                'effects' => ['release_quota', 'outbox_wa_expired'],
            ],
            'DIBATALKAN' => [
                'actor' => 'Pengunjung',
                'permission' => 'reservation.cancel',
                'guard' => 'belum_ada_pembayaran_terverifikasi',
                'effects' => ['release_quota'],
            ],
        ],
    ],
    'MENUNGGU_VERIFIKASI' => [
        'label' => 'Menunggu Verifikasi',
        'transitions' => [
            'LUNAS' => [
                'actor' => 'Admin',
                'permission' => 'payment.verify',
                'guard' => 'nominal_cocok_dan_pemverifikasi_ada',
                'effects' => ['update_payment_terverifikasi', 'ledger_in', 'generate_qr_token', 'outbox_wa_tiket'],
            ],
            'DITOLAK' => [
                'actor' => 'Admin',
                'permission' => 'payment.reject',
                'guard' => 'alasan_wajib',
                'effects' => ['update_payment_ditolak', 'outbox_wa_alasan'],
            ],
        ],
    ],
    'DITOLAK' => [
        'label' => 'Ditolak',
        'transitions' => [
            'MENUNGGU_VERIFIKASI' => [
                'actor' => 'Pengunjung',
                'permission' => null,
                'guard' => 'belum_lewat_deadline',
                'effects' => ['create_payment_attempt_baru'],
            ],
            'KEDALUWARSA' => [
                'actor' => 'Sistem',
                'permission' => null,
                'guard' => 'payment_deadline_lewat',
                'effects' => ['release_quota', 'outbox_wa_expired'],
            ],
        ],
    ],
    'LUNAS' => [
        'label' => 'Lunas',
        'transitions' => [
            'SELESAI' => [
                'actor' => 'Admin',
                'permission' => 'checkin.scan',
                'guard' => 'hari_ini_sama_tanggal_kunjungan_dan_token_valid_belum_terpakai',
                'effects' => ['catat_checkins', 'tandai_token_terpakai'],
            ],
            'REFUND_DIAJUKAN' => [
                'actor' => 'Admin',
                'permission' => 'refund.request',
                'guard' => 'belum_selesai_dan_alasan_wajib',
                'effects' => ['create_refunds_diajukan'],
            ],
            'TIDAK_HADIR' => [
                'actor' => 'Sistem',
                'permission' => null,
                'guard' => 'tanggal_kunjungan_lewat_tanpa_checkin',
                'effects' => ['catat_no_show'],
            ],
        ],
    ],
    'REFUND_DIAJUKAN' => [
        'label' => 'Refund Diajukan',
        'transitions' => [
            'DIREFUND' => [
                'actor' => 'Admin',
                'permission' => 'refund.approve',
                'guard' => 'nominal_kurang_sama_terbayar_dan_approver_bukan_requester',
                'effects' => ['update_payment_direfund', 'ledger_out', 'release_quota_jika_belum_lewat'],
            ],
            'LUNAS' => [
                'actor' => 'Admin',
                'permission' => 'refund.approve',
                'guard' => 'alasan_penolakan_wajib',
                'effects' => ['update_refunds_ditolak'],
            ],
        ],
    ],
    'SELESAI' => [
        'label' => 'Selesai',
        'transitions' => [],
    ],
    'DIREFUND' => [
        'label' => 'Direfund',
        'transitions' => [],
    ],
    'KEDALUWARSA' => [
        'label' => 'Kedaluwarsa',
        'transitions' => [],
    ],
    'DIBATALKAN' => [
        'label' => 'Dibatalkan',
        'transitions' => [],
    ],
    'TIDAK_HADIR' => [
        'label' => 'Tidak Hadir',
        'transitions' => [],
    ],
];
