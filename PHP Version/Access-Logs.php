<?php
// // Define ABSPATH for template security
// define('ABSPATH', dirname(__FILE__));

// Prevent any output before headers
ob_start();

// Increase execution time and memory limits
set_time_limit(120); // Set to 2 minutes
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 120);


// Force content type and encoding
header('Content-Type: text/html; charset=utf-8');

// Set default timezone
date_default_timezone_set('Africa/Nairobi');

// Function to safely output HTML
function safe_html($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Function to handle fatal errors
function fatal_handler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        show_error_page('Fatal Error', $error['message'], $error['file'], $error['line']);
    }
}
register_shutdown_function('fatal_handler');

// Function to show error page
function show_error_page($title, $message, $file = null, $line = null) {
    $timeInfo = [
        'range' => isset($GLOBALS['rangeText']) ? $GLOBALS['rangeText'] : 'Unknown',
        'start' => isset($GLOBALS['startTime']) ? date('Y-m-d H:i:s', $GLOBALS['startTime']) : 'Not set',
        'end' => isset($GLOBALS['endTime']) ? date('Y-m-d H:i:s', $GLOBALS['endTime']) : 'Not set',
        'current' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    $debugInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'device_ip' => isset($GLOBALS['deviceIP']) ? $GLOBALS['deviceIP'] : 'Not set'
    ];
    
    exit;
}

try {
    // Rest of Code
    class DahuaAccessLogs {
        private $config;
        private $curl;

        public function __construct(array $config) {
            $this->config = $config;
            $this->curl = curl_init();
        }

        public function getLogs($startTime, $endTime) {
            try {
                $url = sprintf(
                    "http://%s/cgi-bin/recordFinder.cgi?action=find&name=AccessControlCardRec&StartTime=%d&EndTime=%d",
                    $this->config['deviceIP'],
                    $startTime,
                    $endTime
                );

                curl_setopt_array($this->curl, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
                    CURLOPT_USERPWD => "{$this->config['username']}:{$this->config['password']}",
                    CURLOPT_HEADER => true,
                    CURLOPT_TIMEOUT => 60
                ]);

                $response = curl_exec($this->curl);
                
                if ($response === false) {
                    throw new RuntimeException('Connection Error: ' . curl_error($this->curl));
                }

                $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
                $body = substr($response, $headerSize);

                if ($httpCode !== 200) {
                    throw new RuntimeException('Failed to fetch access records. HTTP Code: ' . $httpCode);
                }

                // Parse the response
                $lines = explode("\n", $body);
                $records = [];
                $currentIndex = null;
                $currentRecord = [];
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (preg_match('/records\[(\d+)\]\.(\w+)=(.*)/', $line, $matches)) {
                        $index = $matches[1];
                        $field = $matches[2];
                        $value = $matches[3];
                        
                        if ($currentIndex !== $index) {
                            if (!empty($currentRecord)) {
                                $records[] = $currentRecord;
                            }
                            $currentRecord = [];
                            $currentIndex = $index;
                        }
                        
                        $currentRecord[$field] = $value;
                    }
                }
                
                if (!empty($currentRecord)) {
                    $records[] = $currentRecord;
                }

                return $records;

            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
                return [];
            }
        }

        public function __destruct() {
            if (is_resource($this->curl)) {
                curl_close($this->curl);
            }
        }
    }

    // Main execution code
    $endTime = time();
    $startTime = $endTime - (24 * 3600);

    $config = [
        'deviceIP' => '10.1.1.208',
        'username' => 'admin',
        'password' => 'admin123'
    ];

    $accessLogs = new DahuaAccessLogs($config);
    $records = $accessLogs->getLogs($startTime, $endTime);
    
    // Print records
    echo "\n=== Access Records for Last 24 Hours ===\n";
    echo "Time Range: " . date('Y-m-d H:i:s', $startTime) . " to " . date('Y-m-d H:i:s', $endTime) . "\n";
    echo "Total Records: " . count($records) . "\n";
    echo "----------------------------------------\n";
    
    foreach ($records as $record) {
        echo "RECORD DETAILS:\n";
        echo "----------------------------------------\n";
        echo "Time: " . ($record['Time'] ?? 'N/A') . "\n";
        echo "Device: " . ($record['DeviceName'] ?? 'N/A') . "\n";
        echo "Door: " . ($record['Door'] ?? 'N/A') . "\n";
        echo "----------------------------------------\n";
        echo "USER INFORMATION:\n";
        echo "Name: " . ($record['Name'] ?? 'N/A') . "\n";
        echo "Card No: " . ($record['CardNo'] ?? 'N/A') . "\n";
        echo "User ID: " . ($record['UserID'] ?? 'N/A') . "\n";
        echo "----------------------------------------\n";
        echo "ACCESS DETAILS:\n";
        echo "Status: " . ($record['Status'] ?? 'N/A') . "\n";
        echo "Verify Mode: " . ($record['VerifyMode'] ?? 'N/A') . "\n";
        echo "Event Type: " . ($record['EventType'] ?? 'N/A') . "\n";
        echo "----------------------------------------\n";
        echo "ADDITIONAL INFO:\n";
        echo "Card Type: " . ($record['CardType'] ?? 'N/A') . "\n";
        echo "Card Reader: " . ($record['CardReader'] ?? 'N/A') . "\n";
        echo "Card Reader No: " . ($record['CardReaderNo'] ?? 'N/A') . "\n";
        echo "----------------------------------------\n";
        echo "RAW DATA:\n";
        echo json_encode($record, JSON_PRETTY_PRINT) . "\n";
        echo "========================================\n\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Flush output buffer
ob_end_flush();
?>