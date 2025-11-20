<?php
/**
 * Database Tests
 * 
 * Tests for database schema, constraints, and data integrity
 */

require_once __DIR__ . '/../dbconnection.php';

class DatabaseTests {
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
     * Test 1: Check Required Tables Exist
     */
    public function testTablesExist() {
        $requiredTables = [
            'Branches',
            'Accounts',
            'Details',
            'medicines',
            'BranchInventory',
            'SalesTransactions',
            'TransactionItems',
            'ChatConversations',
            'ChatParticipants',
            'ChatMessages'
        ];
        
        $missing = [];
        foreach ($requiredTables as $table) {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            $this->testResults[] = [
                'test' => 'Required Tables Exist',
                'status' => 'PASS',
                'message' => 'All required tables found'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Required Tables Exist',
                'status' => 'FAIL',
                'message' => 'Missing tables: ' . implode(', ', $missing)
            ];
        }
    }
    
    /**
     * Test 2: Check Foreign Key Constraints
     */
    public function testForeignKeyConstraints() {
        $stmt = $this->pdo->query("
            SELECT 
                TABLE_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($fks) >= 14) { // Expected number of foreign keys
            $this->testResults[] = [
                'test' => 'Foreign Key Constraints',
                'status' => 'PASS',
                'message' => count($fks) . ' foreign key constraints found'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Foreign Key Constraints',
                'status' => 'FAIL',
                'message' => 'Expected at least 14 foreign keys, found ' . count($fks)
            ];
        }
    }
    
    /**
     * Test 3: Check Unique Constraints
     */
    public function testUniqueConstraints() {
        $expectedUniques = [
            'Accounts' => ['UserCode', 'Email'],
            'Branches' => ['BranchCode'],
            'Details' => ['NationalIDNumber']
        ];
        
        $allPass = true;
        $messages = [];
        
        foreach ($expectedUniques as $table => $columns) {
            foreach ($columns as $column) {
                $stmt = $this->pdo->query("
                    SELECT COUNT(*) as count
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '$table'
                    AND COLUMN_NAME = '$column'
                    AND CONSTRAINT_NAME LIKE '%UNIQUE%'
                ");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] == 0) {
                    $allPass = false;
                    $messages[] = "$table.$column";
                }
            }
        }
        
        if ($allPass) {
            $this->testResults[] = [
                'test' => 'Unique Constraints',
                'status' => 'PASS',
                'message' => 'All unique constraints found'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Unique Constraints',
                'status' => 'FAIL',
                'message' => 'Missing unique constraints: ' . implode(', ', $messages)
            ];
        }
    }
    
    /**
     * Test 4: Check Data Integrity - Orphaned Records
     */
    public function testDataIntegrity() {
        $issues = [];
        
        // Check for orphaned BranchInventory records
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count
            FROM BranchInventory bi
            LEFT JOIN Branches b ON bi.BranchID = b.BranchID
            WHERE b.BranchID IS NULL
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['count'] > 0) {
            $issues[] = "Orphaned BranchInventory records: {$result['count']}";
        }
        
        // Check for orphaned Accounts
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count
            FROM Accounts a
            LEFT JOIN Branches b ON a.BranchID = b.BranchID
            WHERE b.BranchID IS NULL
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['count'] > 0) {
            $issues[] = "Orphaned Accounts: {$result['count']}";
        }
        
        if (empty($issues)) {
            $this->testResults[] = [
                'test' => 'Data Integrity',
                'status' => 'PASS',
                'message' => 'No orphaned records found'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Data Integrity',
                'status' => 'FAIL',
                'message' => implode('; ', $issues)
            ];
        }
    }
    
    /**
     * Test 5: Check Indexes
     */
    public function testIndexes() {
        $importantIndexes = [
            'Accounts' => ['UserCode', 'Email'],
            'BranchInventory' => ['BranchID', 'MedicineID']
        ];
        
        $missing = [];
        foreach ($importantIndexes as $table => $columns) {
            foreach ($columns as $column) {
                $stmt = $this->pdo->query("
                    SHOW INDEX FROM $table WHERE Column_name = '$column'
                ");
                if ($stmt->rowCount() === 0) {
                    $missing[] = "$table.$column";
                }
            }
        }
        
        if (empty($missing)) {
            $this->testResults[] = [
                'test' => 'Important Indexes',
                'status' => 'PASS',
                'message' => 'All important indexes found'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Important Indexes',
                'status' => 'WARN',
                'message' => 'Missing indexes (recommended): ' . implode(', ', $missing)
            ];
        }
    }
    
    /**
     * Test 6: Check Password Hashing
     */
    public function testPasswordHashing() {
        $stmt = $this->pdo->query("
            SELECT HashedPassword FROM Accounts LIMIT 1
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && preg_match('/^\$2[ayb]\$/', $result['HashedPassword'])) {
            $this->testResults[] = [
                'test' => 'Password Hashing',
                'status' => 'PASS',
                'message' => 'Passwords are properly hashed (bcrypt)'
            ];
        } else {
            $this->testResults[] = [
                'test' => 'Password Hashing',
                'status' => 'FAIL',
                'message' => 'Passwords may not be properly hashed'
            ];
        }
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        echo "Running Database Tests...\n\n";
        
        $this->testTablesExist();
        $this->testForeignKeyConstraints();
        $this->testUniqueConstraints();
        $this->testDataIntegrity();
        $this->testIndexes();
        $this->testPasswordHashing();
        
        $this->printResults();
    }
    
    /**
     * Print test results
     */
    private function printResults() {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        
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
            else $warnings++;
        }
        
        echo "\n";
        echo "Summary: {$passed} passed, {$failed} failed, {$warnings} warnings\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $tests = new DatabaseTests();
    $tests->runAll();
}

