<?php
require_once __DIR__ . '/../../app/init.php';
enforce_active_session($koneksi);

if (!has_canonical_permission('reconciliation.manage')) {
    header("Location: ../reconciliation.php?error=Akses ditolak!");
    exit;
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['reconcile', 'unreconcile'], true)) {
    header("Location: ../reconciliation.php");
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: ../reconciliation.php?error=Token CSRF tidak valid.");
    exit;
}

$ledgerId = (int)($_POST['id'] ?? 0);
$notes = trim((string)($_POST['notes'] ?? $_POST['reason'] ?? ''));
$status = $action === 'reconcile' ? 'RECONCILED' : 'REVERTED';
if ($action === 'unreconcile' && $notes === '') {
    header("Location: ../reconciliation.php?error=Alasan pembatalan wajib diisi.");
    exit;
}

$stmt = $koneksi->prepare(
    'INSERT INTO ledger_reconciliations (ledger_entry_id, status, reconciled_by, notes)
     SELECT ?, ?, ?, ?
     WHERE EXISTS (SELECT 1 FROM ledger_entries WHERE id = ?)'
);
$adminId = (int)$_SESSION['admin_id'];
$stmt->bind_param('isisi', $ledgerId, $status, $adminId, $notes, $ledgerId);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $message = $action === 'reconcile'
        ? 'Entri berhasil direkonsiliasi.'
        : 'Rekonsiliasi dibatalkan dengan jejak baru.';
    header('Location: ../reconciliation.php?pesan=' . urlencode($message));
} else {
    header('Location: ../reconciliation.php?error=Gagal menyimpan rekonsiliasi.');
}
exit;
