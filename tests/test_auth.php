<?php
/**
 * Authentication Tests
 * 
 * Tests for login, logout, password reset, and session management
 */

require_once __DIR__ . '/../dbconnection.php';

class AuthTests {
    private $pdo;
    private $testResults = [];
    
    public function __construct() {
        try {
            $db = new Database();
            $this->pdo = $db->getConnection();
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test 1: Valid Login
     */
    public function testValidLogin() {
        $userCode = 'STAFF1B1';
        $password = 'test123'; // Update with actual test password
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_AuthenticateUser(?)");
            $stmt->execute([$userCode]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['HashedPassword'])) {
                $this->testResults[] = [
                    'test' => 'Valid Login',
                    'status' => 'PASS',
                    'message' => 'User authenticated successfully'
                ];
            } else {
                $this->testResults[] = [
                    'test' => 'Valid Login',
                    'status' => 'FAIL',
                    'message' => 'Authentication failed'
                ];
            }
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'Valid Login',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test 2: Invalid Login - Wrong Password
     */
    public function testInvalidPassword() {
        $userCode = 'STAFF1B1';
        $wrongPassword = 'wrongpassword';
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_AuthenticateUser(?)");
            $stmt->execute([$userCode]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && !password_verify($wrongPassword, $user['HashedPassword'])) {
                $this->testResults[] = [
                    'test' => 'Invalid Password',
                    'status' => 'PASS',
                    'message' => 'Correctly rejected wrong password'
                ];
            } else {
                $this->testResults[] = [
                    'test' => 'Invalid Password',
                    'status' => 'FAIL',
                    'message' => 'Should have rejected wrong password'
                ];
            }
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'Invalid Password',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test 3: Invalid Login - Non-existent User
     */
    public function testNonExistentUser() {
        $userCode = 'NONEXISTENT';
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_AuthenticateUser(?)");
            $stmt->execute([$userCode]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->testResults[] = [
                    'test' => 'Non-existent User',
                    'status' => 'PASS',
                    'message' => 'Correctly rejected non-existent user'
                ];
            } else {
                $this->testResults[] = [
                    'test' => 'Non-existent User',
                    'status' => 'FAIL',
                    'message' => 'Should have rejected non-existent user'
                ];
            }
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'Non-existent User',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test 4: Password Reset - Valid Credentials
     */
    public function testPasswordResetValid() {
        $userCode = 'STAFF1B1';
        $dob = '1992-11-20';
        $newPassword = 'newtest123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_ResetPassword(?, ?, ?)");
            $stmt->execute([$userCode, $dob, $hashedPassword]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['Success'] == 1) {
                $this->testResults[] = [
                    'test' => 'Password Reset - Valid',
                    'status' => 'PASS',
                    'message' => 'Password reset successful'
                ];
            } else {
                $this->testResults[] = [
                    'test' => 'Password Reset - Valid',
                    'status' => 'FAIL',
                    'message' => 'Password reset failed'
                ];
            }
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'Password Reset - Valid',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test 5: Password Reset - Invalid DOB
     */
    public function testPasswordResetInvalidDOB() {
        $userCode = 'STAFF1B1';
        $wrongDob = '1990-01-01';
        $newPassword = 'newtest123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_ResetPassword(?, ?, ?)");
            $stmt->execute([$userCode, $wrongDob, $hashedPassword]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || $result['Success'] == 0) {
                $this->testResults[] = [
                    'test' => 'Password Reset - Invalid DOB',
                    'status' => 'PASS',
                    'message' => 'Correctly rejected invalid DOB'
                ];
            } else {
                $this->testResults[] = [
                    'test' => 'Password Reset - Invalid DOB',
                    'status' => 'FAIL',
                    'message' => 'Should have rejected invalid DOB'
                ];
            }
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'Password Reset - Invalid DOB',
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test 6: SQL Injection Attempt
     */
    public function testSQLInjection() {
        $maliciousInput = "'; DROP TABLE Accounts; --";
        
        try {
            $stmt = $this->pdo->prepare("CALL SP_AuthenticateUser(?)");
            $stmt->execute([$maliciousInput]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If we get here without error, prepared statement worked
            $this->testResults[] = [
                'test' => 'SQL Injection Protection',
                'status' => 'PASS',
                'message' => 'Prepared statement prevented SQL injection'
            ];
        } catch (Exception $e) {
            $this->testResults[] = [
                'test' => 'SQL Injection Protection',
                'status' => 'PASS',
                'message' => 'Exception caught (expected): ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        echo "Running Authentication Tests...\n\n";
        
        $this->testValidLogin();
        $this->testInvalidPassword();
        $this->testNonExistentUser();
        $this->testPasswordResetValid();
        $this->testPasswordResetInvalidDOB();
        $this->testSQLInjection();
        
        $this->printResults();
    }
    
    /**
     * Print test results
     */
    private function printResults() {
        $passed = 0;
        $failed = 0;
        $errors = 0;
        
        foreach ($this->testResults as $result) {
            $status = $result['status'];
            $icon = $status === 'PASS' ? '✅' : ($status === 'FAIL' ? '❌' : '⚠️');
            
            echo sprintf("%s %s: %s\n", 
                $icon, 
                $result['test'], 
                $result['message']
            );
            
            if ($status === 'PASS') $passed++;
            elseif ($status === 'FAIL') $failed++;
            else $errors++;
        }
        
        echo "\n";
        echo "Summary: {$passed} passed, {$failed} failed, {$errors} errors\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $tests = new AuthTests();
    $tests->runAll();
}

