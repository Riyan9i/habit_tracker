<?php
/**
 * SMS Configuration File
 * Supports multiple SMS providers
 */

class SMS {
    private $provider;
    private $api_key;
    private $api_secret;
    private $sender_id;
    
    public function __construct() {
        // Get SMS settings from database
        global $conn;
        $query = "SELECT * FROM admin_settings LIMIT 1";
        $result = $conn->query($query);
        $settings = $result->fetch_assoc();
        
        $this->provider = $settings['sms_provider'] ?? 'fast2sms';
        $this->api_key = $settings['sms_api_key'] ?? '';
        $this->api_secret = $settings['sms_api_secret'] ?? '';
        $this->sender_id = $settings['sms_sender_id'] ?? 'HABITR';
    }
    
    /**
     * Send SMS to a single recipient
     */
    public function sendSMS($to, $message) {
        switch ($this->provider) {
            case 'fast2sms':
                return $this->sendViaFast2SMS($to, $message);
            case 'sslwireless':
                return $this->sendViaSSLWireless($to, $message);
            case 'twilio':
                return $this->sendViaTwilio($to, $message);
            case 'nexmo':
                return $this->sendViaNexmo($to, $message);
            default:
                return $this->sendViaCustomAPI($to, $message);
        }
    }
    
    /**
     * Send SMS using Fast2SMS (India)
     */
    private function sendViaFast2SMS($to, $message) {
        $url = "https://www.fast2sms.com/dev/bulkV2";
        
        $fields = array(
            "sender_id" => $this->sender_id,
            "message" => $message,
            "route" => "v3",
            "numbers" => $to,
        );
        
        $headers = array(
            'authorization: ' . $this->api_key,
            'Content-Type: application/json'
        );
        
        return $this->makeCurlRequest($url, $fields, $headers);
    }
    
    /**
     * Send SMS using SSL Wireless (Bangladesh)
     */
    private function sendViaSSLWireless($to, $message) {
        $url = "https://sms.sslwireless.com/pushapi/dynamic/server.php";
        
        // Remove leading 0 and add country code for Bangladesh
        if (substr($to, 0, 1) === '0') {
            $to = '880' . substr($to, 1);
        }
        
        $params = array(
            'user' => $this->api_key,
            'pass' => $this->api_secret,
            'sid' => $this->sender_id,
            'sms[0][0]' => $to,
            'sms[0][1]' => urlencode($message),
            'sms[0][2]' => time(),
            'type' => 'text'
        );
        
        return $this->makeCurlRequest($url, $params);
    }
    
    /**
     * Send SMS using Twilio
     */
    private function sendViaTwilio($to, $message) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->api_key}/Messages.json";
        
        $fields = array(
            'To' => $to,
            'From' => $this->sender_id,
            'Body' => $message
        );
        
        $headers = array(
            'Authorization: Basic ' . base64_encode($this->api_key . ':' . $this->api_secret)
        );
        
        return $this->makeCurlRequest($url, $fields, $headers, true);
    }
    
    /**
     * Send SMS using Vonage (Nexmo)
     */
    private function sendViaNexmo($to, $message) {
        $url = "https://rest.nexmo.com/sms/json";
        
        $fields = array(
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
            'to' => $to,
            'from' => $this->sender_id,
            'text' => $message
        );
        
        return $this->makeCurlRequest($url, $fields);
    }
    
    /**
     * Send SMS using custom API
     */
    private function sendViaCustomAPI($to, $message) {
        // Custom API implementation
        // You can modify this based on your SMS provider
        return array(
            'success' => false,
            'message' => 'Custom API not configured'
        );
    }
    
    /**
     * Make cURL request
     */
    private function makeCurlRequest($url, $data, $headers = array(), $is_twilio = false) {
        $ch = curl_init();
        
        if ($is_twilio) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return array(
                'success' => false,
                'message' => 'cURL Error: ' . $error
            );
        }
        
        return array(
            'success' => true,
            'response' => json_decode($response, true)
        );
    }
    
    /**
     * Send OTP SMS
     */
    public function sendOTP($to, $otp) {
        $message = "Your Habit Tracker OTP is: {$otp}. Valid for 10 minutes.";
        return $this->sendSMS($to, $message);
    }
    
    /**
     * Send habit reminder SMS
     */
    public function sendHabitReminder($to, $habit_name, $reminder_time) {
        $message = "Reminder: Don't forget to '{$habit_name}' today at {$reminder_time}!";
        return $this->sendSMS($to, $message);
    }
    
    /**
     * Send daily summary SMS
     */
    public function sendDailySummary($to, $completed_habits, $total_habits, $streak) {
        $message = "Daily Summary: You completed {$completed_habits}/{$total_habits} habits. Current streak: {$streak} days. Keep it up!";
        return $this->sendSMS($to, $message);
    }
    
    /**
     * Send weekly report SMS
     */
    public function sendWeeklyReport($to, $success_rate, $streak) {
        $message = "Weekly Report: Your habit success rate is {$success_rate}%. Current streak: {$streak} days. Great work!";
        return $this->sendSMS($to, $message);
    }
}
?>