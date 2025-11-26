<?php
// sample_notifications.php - Create sample notifications for testing
session_start();
header('Content-Type: application/json');

require_once '../../dbconnection.php';

// For testing purposes, allow access without authentication if no session exists
$user_id = $_SESSION['user_id'] ?? 1; // Default to user ID 1 for testing

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Sample notifications for testing
    $samples = [
        [
            'type' => 'inventory',
            'category' => 'Low Stock',
            'title' => 'Low Stock: Paracetamol',
            'message' => 'Current stock: 5',
            'link' => 'med_inventory.php',
            'user_id' => $user_id
        ],
        [
            'type' => 'inventory',
            'category' => 'Expiring Soon',
            'title' => 'Expiring Soon: Ibuprofen',
            'message' => 'Expiry: 2024-12-15',
            'link' => 'med_inventory.php',
            'user_id' => $user_id
        ],
        [
            'type' => 'chat',
            'category' => 'Message',
            'title' => 'New message from Admin',
            'message' => 'Please check the inventory levels...',
            'link' => 'javascript:void(0);',
            'user_id' => $user_id
        ],
        [
            'type' => 'med',
            'category' => 'Add',
            'title' => 'Medicine added',
            'message' => 'Aspirin has been added to inventory',
            'link' => 'med_inventory.php',
            'user_id' => $user_id
        ]
    ];

    $created = 0;
    foreach ($samples as $sample) {
        $stmt = $pdo->prepare("INSERT INTO Notifications (UserID, Type, Category, Title, Message, Link, IsRead) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([
            $sample['user_id'],
            $sample['type'],
            $sample['category'],
            $sample['title'],
            $sample['message'],
            $sample['link']
        ]);
        $created++;
    }

    echo json_encode(['success' => true, 'message' => "Created $created sample notifications"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
