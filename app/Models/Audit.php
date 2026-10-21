<?php
// app/Models/Audit.php
namespace App\Models;

use App\Core\Database;

class Audit {
    /**
     * Catat aktivitas admin
     * 
     * @param string $action       Aksi yang dilakukan (LOGIN, ADD, EDIT, DELETE, dll)
     * @param string $target_table Tabel yang dimodifikasi
     * @param int    $target_id    ID baris yang dimodifikasi
     * @param string $details      Keterangan tambahan (opsional)
     */
    public static function log($action, $target_table = null, $target_id = null, $details = null) {
        $db = Database::getInstance();
        
        $admin_id = $_SESSION['admin_id'] ?? null;
        $username = $_SESSION['username'] ?? 'SYSTEM';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $sql = "INSERT INTO audit_logs (admin_id, admin_username, action, target_table, target_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        return $db->query($sql, [$admin_id, $username, $action, $target_table, $target_id, $details, $ip], "isssiss");
    }
}
