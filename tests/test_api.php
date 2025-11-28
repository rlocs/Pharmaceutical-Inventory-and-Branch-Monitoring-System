<?php
/**
 * API Tests
 * 
 * Tests for API endpoints including medicine API and chat API
 */

class APITests {
    private $baseUrl;
    private $testResults = [];
    private $sessionCookie = '';
    
    public function __construct($baseUrl = 'http://localhost') {
        $this->baseUrl = $baseUrl;
    }
    
    /**
     * Helper: Make HTTP request
     */
    private function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
            }
        }
        
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'body' => $response,
            'json' => json_decode($response, true)
        ];
    }
    
    /**
     * Test 1: Medicine API - Get Medicines (Unauthenticated)
     */
    public function testGetMedicinesUnauthenticated() {
        $url = $this->baseUrl . '/branch1/api/medicine_api.php?action=get_medicines';
        $response = $this->makeRequest($url);
        
        if ($response['code'] === 401 || $response['code'] === 403) {
            $this->testResults[] = [
                'test' => 'Get Medicines - Unauthenticated',
                'status' => 'PASS',
                'message' => 'Correctly rejected unauthenticated request'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Get Medicines - Unauthenticated',
                'status' => 'FAIL',
                'message' => 'Should reject unauthenticated requests'
            ];
        }
    }
    
    /**
     * Test 2: Medicine API - Get Medicines (Authenticated)
     */
    public function testGetMedicinesAuthenticated() {
        // First, login to get session
        $this->login();
        
        $url = $this->baseUrl . '/branch1/api/medicine_api.php?action=get_medicines&page=1';
        $response = $this->makeRequest($url);
        
        if ($response['code'] === 200 && isset($response['json']['success'])) {
            $this->testResults[] = [
                'test' => 'Get Medicines - Authenticated',
                'status' => 'PASS',
                'message' => 'Successfully retrieved medicines'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Get Medicines - Authenticated',
                'status' => 'FAIL',
                'message' => 'Failed to retrieve medicines: ' . $response['body']
            ];
        }
    }
    
    /**
     * Test 3: Medicine API - Get Alerts
     */
    public function testGetAlerts() {
        $url = $this->baseUrl . '/branch1/api/medicine_api.php?action=get_alerts';
        $response = $this->makeRequest($url);
        
        if ($response['code'] === 200 && isset($response['json']['alerts'])) {
            $this->testResults[] = [
                'test' => 'Get Alerts',
                'status' => 'PASS',
                'message' => 'Successfully retrieved alerts'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Get Alerts',
                'status' => 'FAIL',
                'message' => 'Failed to retrieve alerts'
            ];
        }
    }
    
    /**
     * Test 4: Medicine API - Add Medicine (Invalid Data)
     */
    public function testAddMedicineInvalid() {
        $url = $this->baseUrl . '/branch1/api/medicine_api.php?action=add_medicine';
        $data = [
            'medicineName' => '', // Invalid: empty
            'category' => 'Test',
            'stocks' => -5 // Invalid: negative
        ];
        
        $response = $this->makeRequest($url, 'POST', $data, [
            'Content-Type' => 'application/json'
        ]);
        
        if ($response['code'] === 400 || 
            (isset($response['json']['success']) && $response['json']['success'] === false)) {
            $this->testResults[] = [
                'test' => 'Add Medicine - Invalid Data',
                'status' => 'PASS',
                'message' => 'Correctly rejected invalid data'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Add Medicine - Invalid Data',
                'status' => 'FAIL',
                'message' => 'Should reject invalid data'
            ];
        }
    }
    
    /**
     * Test 5: Chat API - Get Conversations (Unauthenticated)
     */
    public function testGetConversationsUnauthenticated() {
        $url = $this->baseUrl . '/branch1/api/chat_api.php?action=get_conversations';
        $response = $this->makeRequest($url);
        
        if ($response['code'] === 401 || 
            (isset($response['json']['success']) && $response['json']['success'] === false)) {
            $this->testResults[] = [
                'test' => 'Get Conversations - Unauthenticated',
                'status' => 'PASS',
                'message' => 'Correctly rejected unauthenticated request'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Get Conversations - Unauthenticated',
                'status' => 'FAIL',
                'message' => 'Should reject unauthenticated requests'
            ];
        }
    }
    
    /**
     * Test 6: XSS Protection in API Responses
     */
    public function testXSSProtection() {
        // This would test if API properly escapes HTML in responses
        // Implementation depends on actual API structure
        
        $this->testResults[] = [
            'test' => 'XSS Protection',
            'status' => 'INFO',
            'message' => 'Manual review required - check API responses escape HTML'
        ];
    }
    
    /**
     * Helper: Login to get session
     */
    private function login() {
        $loginUrl = $this->baseUrl . '/b-login.php';
        $loginData = [
            'username' => 'STAFF1B1',
            'password' => 'test123' // Update with actual test password
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $loginUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        // Extract session cookie from headers
        if (preg_match('/Set-Cookie: (.*?);/', $response, $matches)) {
            $this->sessionCookie = $matches[1];
        }
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        echo "Running API Tests...\n\n";
        
        $this->testGetMedicinesUnauthenticated();
        $this->testGetMedicinesAuthenticated();
        $this->testGetAlerts();
        $this->testAddMedicineInvalid();
        $this->testGetConversationsUnauthenticated();
        $this->testXSSProtection();
        
        $this->printResults();
    }
    
    /**
     * Print test results
     */
    private function printResults() {
        $passed = 0;
        $failed = 0;
        $info = 0;
        
        foreach ($this->testResults as $result) {
            $status = $result['status'];
            $icon = $status === 'PASS' ? '✅' : ($status === 'FAIL' ? '❌' : 'ℹ️');
            
            echo sprintf("%s %s: %s\n", 
                $icon, 
                $result['test'], 
                $result['message']
            );
            
            if ($status === 'PASS') $passed++;
            elseif ($status === 'FAIL') $failed++;
            else $info++;
        }
        
        echo "\n";
        echo "Summary: {$passed} passed, {$failed} failed, {$info} info\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $baseUrl = $argv[1] ?? 'http://localhost';
    $tests = new APITests($baseUrl);
    $tests->runAll();
}

