<?php
namespace App\Models;
use App\Core\Database;
class News {
    public static function getActive($limit = 3) {
        return Database::getInstance()->query("SELECT * FROM berita WHERE aktif = 'Ya' ORDER BY created_at DESC LIMIT ?", [$limit], "i");
    }
}
