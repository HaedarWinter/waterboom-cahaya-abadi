<?php
namespace App\Models;
use App\Core\Database;
class Faq {
    public static function getActive() {
        return Database::getInstance()->query("SELECT * FROM faq WHERE aktif = 'Ya' ORDER BY urutan ASC");
    }
}
