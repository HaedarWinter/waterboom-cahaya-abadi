<?php
// app/Models/Setting.php
namespace App\Models;

use App\Core\Database;

class Setting {
    private static $cache = [];

    public static function get($key, $default = '') {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $db = Database::getInstance();
        $result = $db->query("SELECT setting_value FROM settings WHERE setting_key = ?", [$key], "s");
        
        if ($result && $result->num_rows > 0) {
            $value = $result->fetch_assoc()['setting_value'];
            self::$cache[$key] = $value;
            return $value;
        }

        return $default;
    }

    public static function set($key, $value) {
        $db = Database::getInstance();
        $check = self::get($key);

        if ($check !== '') {
            $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            $db->query($sql, [$value, $key], "ss");
        } else {
            $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
            $db->query($sql, [$key, $value], "ss");
        }
        
        self::$cache[$key] = $value;
        return true;
    }
}
