<?php
// app/Models/WhatsApp.php
namespace App\Models;

use App\Models\Setting;

class WhatsApp {
    /**
     * Kirim pesan WhatsApp via Fonnte API
     * 
     * @param string $target  Nomor HP (08... atau 628...)
     * @param string $message Isi pesan
     * @return array Respon dari API
     */
    public static function send($target, $message) {
        $enabled = Setting::get('wa_notif_enabled', '0');
        $token   = Setting::get('fonnte_token', '');

        if ($enabled !== '1' || empty($token)) {
            return ['status' => false, 'reason' => 'WhatsApp notification disabled or token missing'];
        }

        // Format nomor: ubah 08 ke 62
        $target = self::formatNumber($target);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ),
            CURLOPT_HTTPHEADER => array(
                "Authorization: $token"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Audit::log('WA_ERROR', null, null, "cURL Error: $err | Target: $target");
            return ['status' => false, 'reason' => "cURL Error: $err"];
        }

        $result = json_decode($response, true);
        
        // Log jika API Fonnte mengembalikan error (misal token salah)
        // Fonnte bisa mengembalikan boolean false atau integer 0 untuk kegagalan
        if (!isset($result['status']) || $result['status'] == false) {
            $reason = $result['reason'] ?? ($result['detail'] ?? 'Unknown Error');
            Audit::log('WA_ERROR', null, null, "Fonnte API Fail: $reason | Target: $target");
        }

        return $result;
    }

    /**
     * Kirim file WhatsApp via Fonnte API
     * 
     * @param string $target   Nomor HP
     * @param string $filePath Path absolute file di server
     * @param string $message  Pesan penyerta (caption)
     * @return array
     */
    public static function sendFile($target, $filePath, $message = '') {
        $enabled = Setting::get('wa_notif_enabled', '0');
        $token   = Setting::get('fonnte_token', '');

        if ($enabled !== '1' || empty($token)) {
            return ['status' => false, 'reason' => 'WhatsApp notification disabled or token missing'];
        }

        if (!file_exists($filePath)) {
            return ['status' => false, 'reason' => 'File not found: ' . $filePath];
        }

        $target = self::formatNumber($target);
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'target' => $target,
                'message' => $message,
                'file' => new \CURLFile(realpath($filePath), 'application/pdf', basename($filePath)),
                'countryCode' => '62',
            ),
            CURLOPT_HTTPHEADER => array(
                "Authorization: $token"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Audit::log('WA_ERROR', null, null, "cURL Error (File): $err | Target: $target");
            return ['status' => false, 'reason' => "cURL Error: $err"];
        }

        $result = json_decode($response, true);
        if (!isset($result['status']) || $result['status'] == false) {
            $reason = $result['reason'] ?? ($result['detail'] ?? 'Unknown Error');
            Audit::log('WA_ERROR', null, null, "Fonnte API File Fail: $reason | Target: $target");
        }

        return $result;
    }

    /**
     * Pastikan nomor HP diawali 62
     */
    private static function formatNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);
        if (strpos($number, '0') === 0) {
            $number = '62' . substr($number, 1);
        } elseif (strpos($number, '8') === 0) {
            $number = '62' . $number;
        }
        return $number;
    }
}
