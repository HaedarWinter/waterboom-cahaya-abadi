<?php
namespace App\Models;
use App\Core\Database;
class Review {
    public static function getApproved($limit = 6) {
        return Database::getInstance()->query("SELECT * FROM reviews WHERE status = 'Approved' ORDER BY created_at DESC LIMIT ?", [$limit], "i");
    }
    public static function getStats() {
        return Database::getInstance()->query("SELECT ROUND(AVG(rating), 1) as avg, COUNT(*) as total FROM reviews WHERE status = 'Approved'")->fetch_assoc();
    }
}
