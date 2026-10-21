<?php
namespace App\Models;
use App\Core\Database;
class Gallery {
    public static function getActive() {
        return Database::getInstance()->query("SELECT * FROM galeri WHERE aktif = 'Ya' ORDER BY urutan ASC");
    }
}
