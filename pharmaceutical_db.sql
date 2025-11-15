-- *******************************************************************
-- 1. CORRECTED CREATE CORE TABLES
-- *******************************************************************

-- Table 1: Branches (Correct)
CREATE TABLE Branches (
    BranchID INT PRIMARY KEY AUTO_INCREMENT,
    BranchName VARCHAR(100) NOT NULL,
    BranchAddress VARCHAR(255),
    BranchCode VARCHAR(10) UNIQUE NOT NULL
)ENGINE=InnoDB;

-- Table 2: Accounts (Correct)
CREATE TABLE Accounts (
    UserID INT PRIMARY KEY AUTO_INCREMENT,
    BranchID INT NOT NULL,
    UserCode VARCHAR(10) UNIQUE NOT NULL, 
    FirstName VARCHAR(50) NOT NULL,
    LastName VARCHAR(50) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    HashedPassword VARCHAR(255) NOT NULL, 
    Role ENUM('Admin', 'Staff') NOT NULL,
    AccountStatus ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    DateCreated DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLogin DATETIME,
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID)
)ENGINE=InnoDB;

-- Table 3: Details (Correct)
CREATE TABLE Details (
    UserID INT PRIMARY KEY, 
    DateOfBirth DATE,
    Gender ENUM('Male', 'Female', 'Other'),
    PersonalPhoneNumber VARCHAR(20),
    PersonalAddress VARCHAR(255),
    EmergencyContactName VARCHAR(100),
    EmergencyContactPhone VARCHAR(20),
    HireDate DATE NOT NULL,
    Position VARCHAR(50) NOT NULL,
    Salary DECIMAL(10, 2), 
    NationalIDNumber VARCHAR(30) UNIQUE,
    LastUpdated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID)
)ENGINE=InnoDB;

-- Table 3B: OTP Verification (For Password Reset)
CREATE TABLE OTPVerification (
    OTPID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    OTPCode VARCHAR(6) NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME NOT NULL,
    IsUsed TINYINT(1) DEFAULT 0,
    AttemptCount INT DEFAULT 0,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE
)ENGINE=InnoDB;

-- Table 4: medicines (Correct)

CREATE TABLE medicines (
    MedicineID INT AUTO_INCREMENT PRIMARY KEY,
    MedicineName VARCHAR(100) NOT NULL,
    CategoryID INT,
    CustomCategory VARCHAR(100),
    Form ENUM('Pill/Tablet', 'Liquid', 'Cream/Gel/Ointment', 'Inhaler', 'Injection', 'Patch', 'Drops'),
    Unit VARCHAR(20),
    FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID)
)ENGINE=InnoDB;


-- Table 5: BranchInventory (Correct)
CREATE TABLE BranchInventory (
    BranchInventoryID INT PRIMARY KEY AUTO_INCREMENT,
    BranchID INT NOT NULL,
    MedicineID INT NOT NULL,
    
    Stocks INT DEFAULT 0,
    Price DECIMAL(10,2) DEFAULT 0.00,
    ExpiryDate DATE, 
    Status ENUM('Active', 'Low Stock', 'Out of Stock', 'Expiring Soon', 'Expired') DEFAULT 'Active',
    
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID),
    FOREIGN KEY (MedicineID) REFERENCES medicines(MedicineID),
    UNIQUE KEY idx_branch_med (BranchID, MedicineID) 
)ENGINE=InnoDB;

-- Table 6: Transactions (POS Receipt Header)
CREATE TABLE SalesTransactions (
    TransactionID INT PRIMARY KEY AUTO_INCREMENT, -- Renamed PK
    BranchID INT NOT NULL,
    UserID INT NOT NULL,
    TransactionDateTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    TotalAmount DECIMAL(10, 2) NOT NULL,
    PaymentMethod ENUM('Cash', 'Card', 'Credit') NOT NULL,
    CustomerName VARCHAR(100),
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID),
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID)
)ENGINE=InnoDB;

-- Table 7: TransactionItems (POS Receipt Details)
CREATE TABLE TransactionItems (
    TransactionItemID INT PRIMARY KEY AUTO_INCREMENT, 
    TransactionID INT NOT NULL, 
    BranchInventoryID INT NOT NULL,
    MedicineNameSnapshot VARCHAR(200) NOT NULL, 
    Quantity INT NOT NULL,
    PricePerUnit DECIMAL(10, 2) NOT NULL,
    Subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (TransactionID) REFERENCES SalesTransactions(TransactionID) ON DELETE CASCADE,
    FOREIGN KEY (BranchInventoryID) REFERENCES BranchInventory(BranchInventoryID) ON DELETE CASCADE
)ENGINE=InnoDB;

-- Table 8: ChatConversations (Correct)
CREATE TABLE ChatConversations (
    ConversationID INT PRIMARY KEY AUTO_INCREMENT,
    LastMessageTimestamp DATETIME,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB;

-- Table 9: ChatParticipants (Updated)
CREATE TABLE ChatParticipants ( 
    ParticipantID INT PRIMARY KEY AUTO_INCREMENT,
    ConversationID INT NOT NULL,
    UserID INT NOT NULL,
    BranchID INT NOT NULL,
    LastReadTimestamp DATETIME, 
    FOREIGN KEY (ConversationID) REFERENCES ChatConversations(ConversationID) ON DELETE CASCADE,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE,
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID) ON DELETE CASCADE,
    UNIQUE KEY (ConversationID, UserID) 
)ENGINE=InnoDB;

-- Table 10: ChatMessages (Updated)
CREATE TABLE ChatMessages (
    MessageID INT PRIMARY KEY AUTO_INCREMENT,
    ConversationID INT NOT NULL,
    SenderUserID INT NOT NULL,
    BranchID INT NOT NULL,
    MessageContent TEXT NOT NULL,
    Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('pending', 'sent', 'delivered', 'read') DEFAULT 'sent',
    FOREIGN KEY (ConversationID) REFERENCES ChatConversations(ConversationID) ON DELETE CASCADE,
    FOREIGN KEY (SenderUserID) REFERENCES Accounts(UserID) ON DELETE CASCADE,
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID) ON DELETE CASCADE
)ENGINE=InnoDB;

-- *******************************************************************
-- Notifications Table for Bell Notification System
-- *******************************************************************

CREATE TABLE Notifications (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NULL,
    BranchID INT NOT NULL,

    Type ENUM(
        'inventory',
        'med',
        'chat',
        'pos',
        'reports',
        'account',
        'system'
    ) NOT NULL,

    Category ENUM(
        'Low Stock',
        'Out of Stock',
        'Expiring Soon',
        'Expired',
        'Add',
        'Edit',
        'Delete',
        'Message',
        'Sale',
        'Report',
        'Profile',
        'Other'
    ) NULL,

    Title VARCHAR(255) NOT NULL,
    Message TEXT NOT NULL,
    Link VARCHAR(255),
    ResourceType VARCHAR(50),
    ResourceID INT,
    Severity ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    IsRead TINYINT(1) NOT NULL DEFAULT 0,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user_isread (UserID, IsRead, CreatedAt),
    INDEX idx_branch_created (BranchID, CreatedAt),
    INDEX idx_type_category (Type, Category, CreatedAt),
    INDEX idx_resource (ResourceType, ResourceID),

    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID),
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID)
) ENGINE=InnoDB;

-- NotificationReadState:
CREATE TABLE NotificationReadState (
    ReadID INT AUTO_INCREMENT PRIMARY KEY,
    NotificationID INT NOT NULL,
    UserID INT NOT NULL,
    IsRead TINYINT(1) NOT NULL DEFAULT 0,
    ReadAt TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uniq_notification_user (NotificationID, UserID),
    INDEX idx_user_isread (UserID, IsRead),

    FOREIGN KEY (NotificationID) REFERENCES Notifications(NotificationID) ON DELETE CASCADE,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID)
) ENGINE=InnoDB;

--CalendarNotes table:
CREATE TABLE CalendarNotes (
    NoteID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    BranchID INT NOT NULL,
    NoteDate DATE NOT NULL,
    NoteText TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID),
    FOREIGN KEY (BranchID) REFERENCES Branches(BranchID)
)ENGINE=InnoDB;

-- ToDoList table:
CREATE TABLE ToDoList (
        TaskID INT AUTO_INCREMENT PRIMARY KEY,
        UserID INT NOT NULL,
        BranchID INT NOT NULL,
        TaskText VARCHAR(255) NOT NULL,
        IsDone TINYINT(1) DEFAULT 0,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (UserID) REFERENCES Accounts(UserID),
        FOREIGN KEY (BranchID) REFERENCES Branches(BranchID)
)ENGINE=InnoDB;


--Remove this if you encounter error
ALTER TABLE ChatMessages
ADD BranchID INT NOT NULL AFTER SenderUserID;

ALTER TABLE ChatMessages
ADD FOREIGN KEY (BranchID) REFERENCES Branches(BranchID) ON DELETE CASCADE;

-- *******************************************************************
-- 2. INSERT DATA
-- *******************************************************************

-- BRANCH DATA
INSERT INTO Branches (BranchName, BranchCode) VALUES 
('Lipa Branch', 'B001'),       -- BranchID 1
('Sto Tomas Branch', 'B002'),  -- BranchID 2
('Malvar Branch', 'B003');     -- BranchID 3


-- USER ACCOUNTS
-- A. ADMIN USER (Branch 1)
INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role)
VALUES (1, 'ADMIN1B1', 'Ralph Lauren', 'Bautista', 'bautistastudentacc@gmail.com', '$2y$10$b3XJ2UTkPSS28yiJo.7YFOk3BbLZ8MZeqzcreHpCqdGqbdh9H6veG', 'Admin');
INSERT INTO Details (UserID, DateOfBirth, Gender, PersonalPhoneNumber, PersonalAddress, EmergencyContactName, EmergencyContactPhone, HireDate, Position, Salary, NationalIDNumber)
VALUES (LAST_INSERT_ID(), '1985-04-10', 'Male', '09493298133', '101 Maple Ave, Branch 1 Lipa City', 'Ziyu', '09123456789', '2018-08-01', 'Administrator', 95000.00, '12345678901');

-- B. STAFF 1, BRANCH 1 (Lipa)
INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role)
VALUES (1, 'STAFF1B1', 'Erryca', 'Hizon', 'abistadoerryca@gmail.com', '$2y$10$b3XJ2UTkPSS28yiJo.7YFOk3BbLZ8MZeqzcreHpCqdGqbdh9H6veG', 'Staff');
INSERT INTO Details (UserID, DateOfBirth, Gender, PersonalPhoneNumber, PersonalAddress, EmergencyContactName, EmergencyContactPhone, HireDate, Position, Salary, NationalIDNumber)
VALUES (LAST_INSERT_ID(), '1992-11-20', 'Female', '09509002527', '202 Oak St, Branch 1 Lipa City', 'Zamburat', '09123456789', '2022-05-15', 'staff',48000.00, '23456789012');

-- C. STAFF 1, BRANCH 2 (Sto Tomas)
INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role)
VALUES (2, 'STAFF1B2', 'Abby', 'Balambing', 'Abby.b@branch2.com', '$2y$10$b3XJ2UTkPSS28yiJo.7YFOk3BbLZ8MZeqzcreHpCqdGqbdh9H6veG', 'Staff');
INSERT INTO Details (UserID, DateOfBirth, Gender, PersonalPhoneNumber, PersonalAddress, EmergencyContactName, EmergencyContactPhone, HireDate,  Position, Salary, NationalIDNumber)
VALUES (LAST_INSERT_ID(), '1998-07-05', 'Female', '09493298133', '303 Pine Lane, Branch 2 Sto Tomas City','Mamita delarosa', '099762354278', '2023-01-20', 'Staff', 45000.00, '34567890123');

-- D. STAFF 1, BRANCH 3 (Malvar)
INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role)
VALUES (3, 'STAFF1B3', 'Sopphie', 'Pasta', 'sophie.p@branch3.com', '$2y$10$b3XJ2UTkPSS28yiJo.7YFOk3BbLZ8MZeqzcreHpCqdGqbdh9H6veG', 'Staff');
INSERT INTO Details (UserID, DateOfBirth, Gender, PersonalPhoneNumber, PersonalAddress, EmergencyContactName, EmergencyContactPhone, HireDate, Position, Salary, NationalIDNumber)
VALUES (LAST_INSERT_ID(), '1990-03-28', 'Female', '+63920456789', '404 Cedar St, Branch 3 Malvar City(soon)','Zamburat', '09889162534', '2020-10-10', 'Staff', 55000.00, '45678901234');

-- ***************************************************************
-- 1. INSERT 50 UNIQUE MEDICINES (Updating the global catalog)
-- ***************************************************************

-- Drop existing medicines to ensure a clean slate for the 50 items

INSERT INTO medicines (MedicineID, MedicineName, CategoryID, Form, Unit) VALUES
(1, 'Paracetamol 500mg', 1, 'Pill/Tablet', 'mg'), -- Analgesic
(2, 'Amoxicillin 250mg', 2, 'Pill/Tablet', 'mg'), -- Antibiotic
(3, 'Cotrimoxazole Syrup 240mg/5mL', 2, 'Liquid', 'mL'), -- Antibiotic
(4, 'Metformin HCL 500mg', 22, 'Pill/Tablet', 'mg'), -- Antidiabetic
(5, 'Losartan Potassium 50mg', 23, 'Pill/Tablet', 'mg'), -- Antihypertensive
(6, 'Simvastatin 20mg', 24, 'Pill/Tablet', 'mg'), -- Cholesterol
(7, 'Omeprazole 20mg Delayed Release', 21, 'Pill/Tablet', 'mg'), -- Antacid
(8, 'Salbutamol Nebules 2.5mg', 15, 'Inhaler', 'mL'), -- Respiratory
(9, 'Ibuprofen 400mg', 1, 'Pill/Tablet', 'mg'), -- Analgesic
(10, 'Cefalexin 500mg Capsule', 2, 'Pill/Tablet', 'mg'), -- Antibiotic
(11, 'Lansoprazole 30mg Capsule', 21, 'Pill/Tablet', 'mg'), -- Antacid
(12, 'Amlodipine 5mg Tablet', 23, 'Pill/Tablet', 'mg'), -- Antihypertensive
(13, 'Cetirizine 10mg Tablet', 5, 'Pill/Tablet', 'mg'), -- Antihistamine
(14, 'Loratadine 10mg Tablet', 5, 'Pill/Tablet', 'mg'), -- Antihistamine
(15, 'Dextromethorphan HBr Syrup', 9, 'Liquid', 'mL'), -- Cough and Cold
(16, 'Phenylephrine Nasal Spray', 9, 'Drops', 'mL'), -- Cough and Cold
(17, 'Hydrocortisone 1% Cream', 17, 'Cream/Gel/Ointment', 'gram'), -- Topical
(18, 'Clotrimazole 1% Cream', 7, 'Cream/Gel/Ointment', 'gram'), -- Antifungal
(19, 'Insulin Glargine Injection', 22, 'Injection', 'units'), -- Antidiabetic
(20, 'Atorvastatin 10mg', 24, 'Pill/Tablet', 'mg'), -- Cholesterol
(21, 'Diclofenac Sodium 50mg', 1, 'Pill/Tablet', 'mg'), -- Analgesic
(22, 'Ranitidine 150mg Tablet', 21, 'Pill/Tablet', 'mg'), -- Antacid
(23, 'Furosemide 40mg Tablet', 10, 'Pill/Tablet', 'mg'), -- Diuretic
(24, 'Gabapentin 300mg Capsule', 25, 'Pill/Tablet', 'mg'), -- Anticonvulsant
(25, 'Levothyroxine 50mcg', 26, 'Pill/Tablet', 'mcg'), -- Thyroid
(26, 'Sertraline 50mg', 27, 'Pill/Tablet', 'mg'), -- Antidepressant
(27, 'Fluoxetine 20mg', 27, 'Pill/Tablet', 'mg'), -- Antidepressant
(28, 'Warfarin 5mg Tablet', 28, 'Pill/Tablet', 'mg'), -- Anticoagulant
(29, 'Apixaban 5mg Tablet', 28, 'Pill/Tablet', 'mg'), -- Anticoagulant
(30, 'Adalimumab Injection', 29, 'Injection', 'mg'), -- Biologic
(31, 'Budesonide Inhaler', 15, 'Inhaler', 'mcg'), -- Respiratory
(32, 'Montelukast 10mg', 15, 'Pill/Tablet', 'mg'), -- Respiratory
(33, 'Prednisolone 5mg Tablet', 35, 'Pill/Tablet', 'mg'), -- Corticosteroid
(34, 'Prednisone 10mg Tablet', 35, 'Pill/Tablet', 'mg'), -- Corticosteroid
(35, 'Tamsulosin 0.4mg Capsule', 30, 'Pill/Tablet', 'mg'), -- Urological
(36, 'Finasteride 5mg Tablet', 30, 'Pill/Tablet', 'mg'), -- Urological
(37, 'Sildenafil 50mg Tablet', 31, 'Pill/Tablet', 'mg'), -- Erectile Dysfunction
(38, 'Tadalafil 20mg Tablet', 31, 'Pill/Tablet', 'mg'), -- Erectile Dysfunction
(39, 'Vitamin D3 1000 IU Capsule', 32, 'Pill/Tablet', 'IU'), -- Supplement
(40, 'Vitamin C 500mg Tablet', 32, 'Pill/Tablet', 'mg'), -- Supplement
(41, 'Melatonin 3mg Tablet', 33, 'Pill/Tablet', 'mg'), -- Sleep Aid
(42, 'Zolpidem 10mg Tablet', 33, 'Pill/Tablet', 'mg'), -- Sleep Aid
(43, 'Fexofenadine 180mg', 5, 'Pill/Tablet', 'mg'), -- Antihistamine
(44, 'Aspirin Low Dose 81mg', 34, 'Pill/Tablet', 'mg'), -- Cardio
(45, 'Clopidogrel 75mg Tablet', 34, 'Pill/Tablet', 'mg'), -- Cardio
(46, 'Metoprolol 50mg Tablet', 23, 'Pill/Tablet', 'mg'), -- Antihypertensive
(47, 'Lisinopril 10mg Tablet', 23, 'Pill/Tablet', 'mg'), -- Antihypertensive
(48, 'Azithromycin 250mg', 2, 'Pill/Tablet', 'mg'), -- Antibiotic
(49, 'Doxycycline 100mg Capsule', 2, 'Pill/Tablet', 'mg'), -- Antibiotic
(50, 'Mupirocin Ointment 2%', 17, 'Cream/Gel/Ointment', 'gram'); -- Topical



-- ***************************************************************
-- 2. INSERT BRANCH INVENTORY DATA (50 Items x 3 Branches)
-- ***************************************************************

-- BRANCH 1 (Lipa) - Populating ALL 50 items
INSERT INTO BranchInventory (BranchID, MedicineID, Stocks, Price, ExpiryDate, Status) VALUES
(1, 1, 100, 3.50, '2026-03-10', 'Active'), (1, 2, 50, 6.00, '2026-08-12', 'Active'),
(1, 3, 25, 8.25, '2025-11-20', 'Active'), (1, 4, 0, 4.00, '2026-01-05', 'Out of Stock'),
(1, 5, 75, 7.00, '2025-12-30', 'Active'), (1, 6, 20, 9.80, '2025-11-10', 'Low Stock'),
(1, 7, 10, 5.50, '2026-04-15', 'Low Stock'), (1, 8, 5, 12.50, '2025-11-09', 'Expiring Soon'),
(1, 9, 150, 4.75, '2026-03-22', 'Active'), (1, 10, 40, 11.00, '2025-09-30', 'Active'),
(1, 11, 30, 6.20, '2026-07-01', 'Active'), (1, 12, 60, 8.50, '2025-10-01', 'Active'),
(1, 13, 80, 2.00, '2027-01-20', 'Active'), (1, 14, 70, 2.50, '2027-02-15', 'Active'),
(1, 15, 45, 9.00, '2025-12-10', 'Active'), (1, 16, 15, 15.00, '2026-05-01', 'Active'),
(1, 17, 20, 10.50, '2026-06-01', 'Active'), (1, 18, 25, 11.25, '2026-07-01', 'Active'),
(1, 19, 5, 150.00, '2025-10-10', 'Expiring Soon'), (1, 20, 90, 10.20, '2026-01-01', 'Active'),
(1, 21, 30, 4.90, '2026-02-01', 'Active'), (1, 22, 0, 3.10, '2026-03-01', 'Out of Stock'),
(1, 23, 10, 8.80, '2025-12-01', 'Low Stock'), (1, 24, 20, 15.50, '2026-05-01', 'Active'),
(1, 25, 40, 7.50, '2026-06-01', 'Active'), (1, 26, 0, 12.00, '2026-07-01', 'Out of Stock'),
(1, 27, 25, 11.50, '2026-08-01', 'Active'), (1, 28, 15, 20.00, '2025-11-01', 'Low Stock'),
(1, 29, 35, 25.00, '2026-09-01', 'Active'), (1, 30, 2, 800.00, '2025-10-05', 'Low Stock'),
(1, 31, 10, 35.00, '2026-01-05', 'Low Stock'), (1, 32, 50, 9.50, '2026-02-05', 'Active'),
(1, 33, 40, 7.80, '2026-03-05', 'Active'), (1, 34, 40, 8.30, '2026-04-05', 'Active'),
(1, 35, 20, 18.00, '2026-05-05', 'Active'), (1, 36, 15, 22.00, '2026-06-05', 'Active'),
(1, 37, 5, 55.00, '2025-12-05', 'Low Stock'), (1, 38, 7, 75.00, '2026-07-05', 'Active'),
(1, 39, 120, 1.50, '2027-03-01', 'Active'), (1, 40, 200, 1.25, '2027-04-01', 'Active'),
(1, 41, 60, 3.00, '2026-05-01', 'Active'), (1, 42, 10, 10.00, '2025-11-25', 'Expiring Soon'),
(1, 43, 80, 2.75, '2027-01-01', 'Active'), (1, 44, 150, 0.90, '2026-10-01', 'Active'),
(1, 45, 40, 15.00, '2026-11-01', 'Active'), (1, 46, 55, 10.80, '2026-12-01', 'Active'),
(1, 47, 45, 9.20, '2027-01-01', 'Active'), (1, 48, 30, 14.50, '2026-02-01', 'Active'),
(1, 49, 0, 16.00, '2026-03-01', 'Out of Stock'), (1, 50, 15, 13.00, '2026-04-01', 'Active');


-- BRANCH 2 (Sto Tomas) - Populating ALL 50 items (different prices/stocks)
INSERT INTO BranchInventory (BranchID, MedicineID, Stocks, Price, ExpiryDate, Status) VALUES
(2, 1, 90, 3.60, '2026-03-10', 'Active'), (2, 2, 60, 6.10, '2026-08-12', 'Active'),
(2, 3, 30, 8.35, '2025-11-20', 'Active'), (2, 4, 15, 4.20, '2026-01-05', 'Low Stock'),
(2, 5, 80, 7.10, '2025-12-30', 'Active'), (2, 6, 0, 9.90, '2025-11-10', 'Out of Stock'),
(2, 7, 20, 5.60, '2026-04-15', 'Active'), (2, 8, 10, 12.60, '2025-11-09', 'Low Stock'),
(2, 9, 140, 4.85, '2026-03-22', 'Active'), (2, 10, 50, 11.10, '2025-09-30', 'Active'),
(2, 11, 40, 6.30, '2026-07-01', 'Active'), (2, 12, 70, 8.60, '2025-10-01', 'Active'),
(2, 13, 90, 2.10, '2027-01-20', 'Active'), (2, 14, 60, 2.60, '2027-02-15', 'Active'),
(2, 15, 50, 9.10, '2025-12-10', 'Active'), (2, 16, 20, 15.10, '2026-05-01', 'Active'),
(2, 17, 30, 10.60, '2026-06-01', 'Active'), (2, 18, 35, 11.35, '2026-07-01', 'Active'),
(2, 19, 0, 151.00, '2025-10-10', 'Out of Stock'), (2, 20, 80, 10.30, '2026-01-01', 'Active'),
(2, 21, 40, 5.00, '2026-02-01', 'Active'), (2, 22, 10, 3.20, '2026-03-01', 'Low Stock'),
(2, 23, 0, 8.90, '2025-12-01', 'Out of Stock'), (2, 24, 30, 15.60, '2026-05-01', 'Active'),
(2, 25, 50, 7.60, '2026-06-01', 'Active'), (2, 26, 10, 12.10, '2026-07-01', 'Low Stock'),
(2, 27, 35, 11.60, '2026-08-01', 'Active'), (2, 28, 0, 20.10, '2025-11-01', 'Out of Stock'),
(2, 29, 45, 25.10, '2026-09-01', 'Active'), (2, 30, 5, 801.00, '2025-10-05', 'Active'),
(2, 31, 20, 35.10, '2026-01-05', 'Active'), (2, 32, 60, 9.60, '2026-02-05', 'Active'),
(2, 33, 50, 7.90, '2026-03-05', 'Active'), (2, 34, 50, 8.40, '2026-04-05', 'Active'),
(2, 35, 30, 18.10, '2026-05-05', 'Active'), (2, 36, 25, 22.10, '2026-06-05', 'Active'),
(2, 37, 10, 55.10, '2025-12-05', 'Active'), (2, 38, 12, 75.10, '2026-07-05', 'Active'),
(2, 39, 130, 1.60, '2027-03-01', 'Active'), (2, 40, 210, 1.35, '2027-04-01', 'Active'),
(2, 41, 70, 3.10, '2026-05-01', 'Active'), (2, 42, 0, 10.10, '2025-11-25', 'Out of Stock'),
(2, 43, 90, 2.85, '2027-01-01', 'Active'), (2, 44, 160, 1.00, '2026-10-01', 'Active'),
(2, 45, 50, 15.10, '2026-11-01', 'Active'), (2, 46, 65, 10.90, '2026-12-01', 'Active'),
(2, 47, 55, 9.30, '2027-01-01', 'Active'), (2, 48, 40, 14.60, '2026-02-01', 'Active'),
(2, 49, 10, 16.10, '2026-03-01', 'Low Stock'), (2, 50, 25, 13.10, '2026-04-01', 'Active');


-- BRANCH 3 (Malvar) - Populating ALL 50 items (different prices/stocks)
INSERT INTO BranchInventory (BranchID, MedicineID, Stocks, Price, ExpiryDate, Status) VALUES
(3, 1, 110, 3.40, '2026-03-10', 'Active'), (3, 2, 45, 5.90, '2026-08-12', 'Active'),
(3, 3, 35, 8.15, '2025-11-20', 'Active'), (3, 4, 10, 3.90, '2026-01-05', 'Low Stock'),
(3, 5, 85, 6.90, '2025-12-30', 'Active'), (3, 6, 25, 9.70, '2025-11-10', 'Low Stock'),
(3, 7, 0, 5.40, '2026-04-15', 'Out of Stock'), (3, 8, 15, 12.40, '2025-11-09', 'Active'),
(3, 9, 130, 4.65, '2026-03-22', 'Active'), (3, 10, 35, 10.90, '2025-09-30', 'Active'),
(3, 11, 50, 6.10, '2026-07-01', 'Active'), (3, 12, 50, 8.40, '2025-10-01', 'Active'),
(3, 13, 100, 1.90, '2027-01-20', 'Active'), (3, 14, 80, 2.40, '2027-02-15', 'Active'),
(3, 15, 60, 8.90, '2025-12-10', 'Active'), (3, 16, 25, 14.90, '2026-05-01', 'Active'),
(3, 17, 35, 10.40, '2026-06-01', 'Active'), (3, 18, 40, 11.05, '2026-07-01', 'Active'),
(3, 19, 3, 149.00, '2025-10-10', 'Low Stock'), (3, 20, 70, 10.10, '2026-01-01', 'Active'),
(3, 21, 50, 4.80, '2026-02-01', 'Active'), (3, 22, 5, 3.00, '2026-03-01', 'Low Stock'),
(3, 23, 15, 8.70, '2025-12-01', 'Low Stock'), (3, 24, 25, 15.40, '2026-05-01', 'Active'),
(3, 25, 30, 7.40, '2026-06-01', 'Active'), (3, 26, 15, 11.90, '2026-07-01', 'Low Stock'),
(3, 27, 40, 11.40, '2026-08-01', 'Active'), (3, 28, 5, 19.90, '2025-11-01', 'Expiring Soon'),
(3, 29, 40, 24.90, '2026-09-01', 'Active'), (3, 30, 0, 799.00, '2025-10-05', 'Out of Stock'),
(3, 31, 25, 34.90, '2026-01-05', 'Active'), (3, 32, 70, 9.40, '2026-02-05', 'Active'),
(3, 33, 60, 7.70, '2026-03-05', 'Active'), (3, 34, 60, 8.20, '2026-04-05', 'Active'),
(3, 35, 35, 17.90, '2026-05-05', 'Active'), (3, 36, 30, 21.90, '2026-06-05', 'Active'),
(3, 37, 15, 54.90, '2025-12-05', 'Active'), (3, 38, 10, 74.90, '2026-07-05', 'Active'),
(3, 39, 140, 1.40, '2027-03-01', 'Active'), (3, 40, 220, 1.15, '2027-04-01', 'Active'),
(3, 41, 80, 2.90, '2026-05-01', 'Active'), (3, 42, 5, 9.90, '2025-11-25', 'Low Stock'),
(3, 43, 100, 2.65, '2027-01-01', 'Active'), (3, 44, 170, 0.80, '2026-10-01', 'Active'),
(3, 45, 60, 14.90, '2026-11-01', 'Active'), (3, 46, 75, 10.70, '2026-12-01', 'Active'),
(3, 47, 65, 9.10, '2027-01-01', 'Active'), (3, 48, 50, 14.40, '2026-02-01', 'Active'),
(3, 49, 15, 15.90, '2026-03-01', 'Low Stock'), (3, 50, 30, 12.90, '2026-04-01', 'Active');


-- ***************************************************************
-- INSERT 50 POS TRANSACTIONS (18 for B1, 17 for B2, 15 for B3)
-- Uses the updated 50-item catalog and calculated BranchInventoryIDs.
-- ***************************************************************

-- Assuming TransactionID AUTO_INCREMENT picks up from where previous insertions left off.

-- BRANCH 1 DATA (UserID 2 - Erryca Hizon)
-- The BranchInventoryIDs are now 1-50 (e.g., Paracetamol=1, Cotrimoxazole=3, Salbutamol=8)
-- Prices are based on Branch 1 inventory: Paracetamol(1)=3.50, Cotrimoxazole(3)=8.25, Salbutamol(8)=12.50

-- 18 Transactions for Branch 1
INSERT INTO SalesTransactions (BranchID, UserID, TotalAmount, PaymentMethod) VALUES
(1, 2, 7.00, 'Cash'),-- T1
(1, 2, 8.25, 'Card'),-- T2
(1, 2, 12.50, 'Cash'),-- T3
(1, 2, 14.00, 'Cash'),-- T4
(1, 2, 3.50, 'Card'),-- T5
(1, 2, 25.00, 'Credit'),-- T6
(1, 2, 11.75, 'Cash'),-- T7 (Corrected Amount)
(1, 2, 17.50, 'Card'),-- T8
(1, 2, 21.00, 'Cash'),-- T9
(1, 2, 8.25, 'Cash'),-- T10 (Corrected Amount)
(1, 2, 12.50, 'Card'),-- T11 (Corrected Amount)
(1, 2, 8.25, 'Cash'),-- T12 (Corrected Amount)
(1, 2, 7.00, 'Card'),-- T13
(1, 2, 3.50, 'Cash'),-- T14
(1, 2, 16.50, 'Credit'),-- T15
(1, 2, 3.50, 'Cash'),-- T16 (Corrected Amount)
(1, 2, 28.00, 'Card'),-- T17
(1, 2, 3.50, 'Cash');-- T18 (Corrected Amount)

-- Insert Transaction Items for Branch 1 (T1-T18)
INSERT INTO TransactionItems (TransactionID, BranchInventoryID, MedicineNameSnapshot, Quantity, PricePerUnit, Subtotal) VALUES
(LAST_INSERT_ID() - 17, 1, 'Paracetamol 500mg', 2, 3.50, 7.00),
(LAST_INSERT_ID() - 16, 3, 'Cotrimoxazole Syrup 240mg/5mL', 1, 8.25, 8.25),
(LAST_INSERT_ID() - 15, 8, 'Salbutamol Nebules 2.5mg', 1, 12.50, 12.50),
(LAST_INSERT_ID() - 14, 1, 'Paracetamol 500mg', 4, 3.50, 14.00),
(LAST_INSERT_ID() - 13, 1, 'Paracetamol 500mg', 1, 3.50, 3.50),
(LAST_INSERT_ID() - 12, 8, 'Salbutamol Nebules 2.5mg', 2, 12.50, 25.00),
-- T7: Two items
(LAST_INSERT_ID() - 11, 1, 'Paracetamol 500mg', 1, 3.50, 3.50),
(LAST_INSERT_ID() - 11, 3, 'Cotrimoxazole Syrup 240mg/5mL', 1, 8.25, 8.25), 
(LAST_INSERT_ID() - 10, 1, 'Paracetamol 500mg', 5, 3.50, 17.50),
(LAST_INSERT_ID() - 9, 1, 'Paracetamol 500mg', 6, 3.50, 21.00),
-- T10: One item (Cotrimoxazole)
(LAST_INSERT_ID() - 8, 3, 'Cotrimoxazole Syrup 240mg/5mL', 1, 8.25, 8.25), 
-- T11: One item (Salbutamol)
(LAST_INSERT_ID() - 7, 8, 'Salbutamol Nebules 2.5mg', 1, 12.50, 12.50), 
-- T12: One item (Cotrimoxazole)
(LAST_INSERT_ID() - 6, 3, 'Cotrimoxazole Syrup 240mg/5mL', 1, 8.25, 8.25), 
(LAST_INSERT_ID() - 5, 1, 'Paracetamol 500mg', 2, 3.50, 7.00),
(LAST_INSERT_ID() - 4, 1, 'Paracetamol 500mg', 1, 3.50, 3.50),
(LAST_INSERT_ID() - 3, 3, 'Cotrimoxazole Syrup 240mg/5mL', 2, 8.25, 16.50),
-- T16: One item (Paracetamol)
(LAST_INSERT_ID() - 2, 1, 'Paracetamol 500mg', 1, 3.50, 3.50), 
(LAST_INSERT_ID() - 1, 1, 'Paracetamol 500mg', 8, 3.50, 28.00),
-- T18: One item (Paracetamol)
(LAST_INSERT_ID(), 1, 'Paracetamol 500mg', 1, 3.50, 3.50);


-- BRANCH 2 DATA (UserID 3 - Abby Balambing)
-- The BranchInventoryIDs are now 51-100 (e.g., Paracetamol=51, Losartan=55)
-- Prices are based on Branch 2 inventory: Paracetamol(51)=3.60, Losartan(55)=7.10 (using the updated price of 7.10)

-- 17 Transactions for Branch 2
INSERT INTO SalesTransactions (BranchID, UserID, TotalAmount, PaymentMethod, CustomerName) VALUES
(2, 3, 7.20, 'Cash', 'Jane Doe'),-- T19
(2, 3, 3.60, 'Card', 'John Smith'),-- T20
(2, 3, 14.40, 'Cash', NULL),-- T21
(2, 3, 7.10, 'Cash', 'Lisa Ray'),-- T22 (Corrected Amount)
(2, 3, 10.80, 'Card', NULL),-- T23
(2, 3, 35.50, 'Cash', 'Mike Chen'),-- T24 (Corrected Amount)
(2, 3, 3.60, 'Credit', NULL), -- T25
(2, 3, 7.20, 'Cash', 'Sarah Connor'),-- T26
(2, 3, 14.20, 'Card', NULL),-- T27 (Corrected Amount)
(2, 3, 3.60, 'Cash', 'Alex Du'),-- T28
(2, 3, 10.80, 'Cash', NULL),-- T29
(2, 3, 7.10, 'Card', 'Ben Jones'),-- T30 (Corrected Amount)
(2, 3, 18.00, 'Cash', NULL),-- T31
(2, 3, 7.20, 'Card', 'Cathy Lee'),-- T32
(2, 3, 3.60, 'Cash', NULL),-- T33
(2, 3, 14.20, 'Credit', 'David Kim'),-- T34 (Corrected Amount)
(2, 3, 21.60, 'Cash', NULL);-- T35

-- Insert Transaction Items for Branch 2 (T19-T35)
INSERT INTO TransactionItems (TransactionID, BranchInventoryID, MedicineNameSnapshot, Quantity, PricePerUnit, Subtotal) VALUES
(LAST_INSERT_ID() - 16, 51, 'Paracetamol 500mg', 2, 3.60, 7.20),
(LAST_INSERT_ID() - 15, 51, 'Paracetamol 500mg', 1, 3.60, 3.60),
(LAST_INSERT_ID() - 14, 51, 'Paracetamol 500mg', 4, 3.60, 14.40),
(LAST_INSERT_ID() - 13, 55, 'Losartan Potassium 50mg', 1, 7.10, 7.10),
(LAST_INSERT_ID() - 12, 51, 'Paracetamol 500mg', 3, 3.60, 10.80),
(LAST_INSERT_ID() - 11, 55, 'Losartan Potassium 50mg', 5, 7.10, 35.50),
(LAST_INSERT_ID() - 10, 51, 'Paracetamol 500mg', 1, 3.60, 3.60),
(LAST_INSERT_ID() - 9, 51, 'Paracetamol 500mg', 2, 3.60, 7.20),
(LAST_INSERT_ID() - 8, 55, 'Losartan Potassium 50mg', 2, 7.10, 14.20),
(LAST_INSERT_ID() - 7, 51, 'Paracetamol 500mg', 1, 3.60, 3.60),
(LAST_INSERT_ID() - 6, 51, 'Paracetamol 500mg', 3, 3.60, 10.80),
(LAST_INSERT_ID() - 5, 55, 'Losartan Potassium 50mg', 1, 7.10, 7.10),
(LAST_INSERT_ID() - 4, 51, 'Paracetamol 500mg', 5, 3.60, 18.00),
(LAST_INSERT_ID() - 3, 51, 'Paracetamol 500mg', 2, 3.60, 7.20),
(LAST_INSERT_ID() - 2, 51, 'Paracetamol 500mg', 1, 3.60, 3.60),
(LAST_INSERT_ID() - 1, 55, 'Losartan Potassium 50mg', 2, 7.10, 14.20),
(LAST_INSERT_ID(), 51, 'Paracetamol 500mg', 6, 3.60, 21.60);


-- BRANCH 3 DATA (UserID 4 - Sopphie Pasta)
-- The BranchInventoryIDs are now 101-150 (e.g., Paracetamol=101, Simvastatin=106, Ibuprofen=109)
-- Prices are based on Branch 3 inventory: Paracetamol(101)=3.40, Simvastatin(106)=9.70, Ibuprofen(109)=4.65

-- 15 Transactions for Branch 3
INSERT INTO SalesTransactions (BranchID, UserID, TotalAmount, PaymentMethod) VALUES
(3, 4, 6.80, 'Cash'),-- T36 (Corrected Amount)
(3, 4, 9.70, 'Card'),-- T37 (Corrected Amount)
(3, 4, 9.30, 'Cash'),-- T38 (Corrected Amount)
(3, 4, 13.60, 'Credit'),-- T39 (Corrected Amount)
(3, 4, 4.65, 'Cash'),-- T40 (Corrected Amount)
(3, 4, 19.40, 'Card'),-- T41 (Corrected Amount)
(3, 4, 3.40, 'Cash'),-- T42 (Corrected Amount)
(3, 4, 13.95, 'Credit'),-- T43 (Corrected Amount)
(3, 4, 20.40, 'Cash'),-- T44 (Corrected Amount)
(3, 4, 9.70, 'Cash'),-- T45 (Corrected Amount)
(3, 4, 4.65, 'Card'),-- T46 (Corrected Amount)
(3, 4, 6.80, 'Credit'),-- T47 (Corrected Amount)
(3, 4, 9.70, 'Cash'),-- T48 (Corrected Amount)
(3, 4, 4.65, 'Card'),-- T49 (Corrected Amount)
(3, 4, 3.40, 'Cash');-- T50 (Corrected Amount)

-- Insert Transaction Items for Branch 3 (T36-T50)
INSERT INTO TransactionItems (TransactionID, BranchInventoryID, MedicineNameSnapshot, Quantity, PricePerUnit, Subtotal) VALUES
(LAST_INSERT_ID() - 14, 101, 'Paracetamol 500mg', 2, 3.40, 6.80),
(LAST_INSERT_ID() - 13, 106, 'Simvastatin 20mg', 1, 9.70, 9.70),
(LAST_INSERT_ID() - 12, 109, 'Ibuprofen 400mg', 2, 4.65, 9.30),
(LAST_INSERT_ID() - 11, 101, 'Paracetamol 500mg', 4, 3.40, 13.60),
(LAST_INSERT_ID() - 10, 109, 'Ibuprofen 400mg', 1, 4.65, 4.65),
(LAST_INSERT_ID() - 9, 106, 'Simvastatin 20mg', 2, 9.70, 19.40),
(LAST_INSERT_ID() - 8, 101, 'Paracetamol 500mg', 1, 3.40, 3.40),
(LAST_INSERT_ID() - 7, 109, 'Ibuprofen 400mg', 3, 4.65, 13.95),
(LAST_INSERT_ID() - 6, 101, 'Paracetamol 500mg', 6, 3.40, 20.40),
(LAST_INSERT_ID() - 5, 106, 'Simvastatin 20mg', 1, 9.70, 9.70),
(LAST_INSERT_ID() - 4, 109, 'Ibuprofen 400mg', 1, 4.65, 4.65),
(LAST_INSERT_ID() - 3, 101, 'Paracetamol 500mg', 2, 3.40, 6.80),
(LAST_INSERT_ID() - 2, 106, 'Simvastatin 20mg', 1, 9.70, 9.70),
(LAST_INSERT_ID() - 1, 109, 'Ibuprofen 400mg', 1, 4.65, 4.65),
(LAST_INSERT_ID(), 101, 'Paracetamol 500mg', 1, 3.40, 3.40);

-- No need for separate UPDATE statements, as I've fixed the TotalAmount values in the INSERTs above.
-- The previous T7, T10, T11, T12, T16, T18 logic is now baked into the INSERTs for TransactionID 1 through 18.




-- ***************************************************************
-- Stored Procedures
-- ***************************************************************

DELIMITER //

-- 1. Authenticate User (Login)
CREATE PROCEDURE SP_AuthenticateUser (
    IN p_UserCode VARCHAR(10)
)
BEGIN
    SELECT 
        UserID, 
        UserCode,
        FirstName,
        LastName,
        HashedPassword, 
        Role, 
        BranchID
    FROM Accounts
    WHERE 
        UserCode = p_UserCode
        AND AccountStatus = 'Active';  -- only active users can log in
END //
 

-- 2. Reset Password (Forgot Password)
CREATE PROCEDURE SP_ResetPassword (
    IN p_UserCode VARCHAR(10),
    IN p_DateOfBirth DATE,
    IN p_NewHashedPassword VARCHAR(255)
)
BEGIN
    DECLARE v_UserID INT;

    SELECT A.UserID 
    INTO v_UserID
    FROM Accounts A
    INNER JOIN Details D ON A.UserID = D.UserID
    WHERE 
        A.UserCode = p_UserCode
        AND D.DateOfBirth = p_DateOfBirth
        AND A.AccountStatus = 'Active';

    IF v_UserID IS NOT NULL THEN
        UPDATE Accounts SET HashedPassword = p_NewHashedPassword WHERE UserID = v_UserID;
        SELECT 1 AS Success;
    ELSE
        SELECT 0 AS Success;
    END IF;
END //

-- 3. Get all conversations for a user


CREATE PROCEDURE SP_GetConversations (
    IN p_UserID INT
)
BEGIN
    SELECT DISTINCT
        c.ConversationID,
        c.LastMessageTimestamp,
        -- Other participant details
        (SELECT a.FirstName 
         FROM ChatParticipants cp 
         JOIN Accounts a ON cp.UserID = a.UserID 
         WHERE cp.ConversationID = c.ConversationID 
           AND cp.UserID != p_UserID 
         LIMIT 1) AS FirstName,
        (SELECT a.LastName 
         FROM ChatParticipants cp 
         JOIN Accounts a ON cp.UserID = a.UserID 
         WHERE cp.ConversationID = c.ConversationID 
           AND cp.UserID != p_UserID 
         LIMIT 1) AS LastName,
        (SELECT b.BranchName 
         FROM ChatParticipants cp 
         JOIN Accounts a ON cp.UserID = a.UserID 
         JOIN Branches b ON a.BranchID = b.BranchID 
         WHERE cp.ConversationID = c.ConversationID 
           AND cp.UserID != p_UserID 
         LIMIT 1) AS BranchName,
        -- Last message
        (SELECT cm.MessageContent 
         FROM ChatMessages cm 
         WHERE cm.ConversationID = c.ConversationID 
         ORDER BY cm.Timestamp DESC 
         LIMIT 1) AS LastMessage,
        -- Unread message count
        (SELECT COUNT(*) 
         FROM ChatMessages cm 
         WHERE cm.ConversationID = c.ConversationID 
           AND cm.Timestamp > (
               SELECT cp.LastReadTimestamp 
               FROM ChatParticipants cp 
               WHERE cp.ConversationID = c.ConversationID 
                 AND cp.UserID = p_UserID
           )) AS UnreadCount
    FROM ChatConversations c
    JOIN ChatParticipants p 
      ON c.ConversationID = p.ConversationID
    WHERE p.UserID = p_UserID
    GROUP BY c.ConversationID, c.LastMessageTimestamp
    ORDER BY c.LastMessageTimestamp DESC;
END //



-- 4. Get all messages for a conversation
CREATE PROCEDURE SP_GetMessages (
    IN p_ConversationID INT
)
BEGIN
    SELECT
        m.MessageID,
        m.SenderUserID,
        m.MessageContent,
        m.Timestamp,
        a.FirstName,
        a.LastName,
        b.BranchName
    FROM ChatMessages m
    JOIN Accounts a ON m.SenderUserID = a.UserID
    JOIN Branches b ON a.BranchID = b.BranchID
    WHERE m.ConversationID = p_ConversationID
    ORDER BY m.Timestamp ASC;
END //

-- 5. Send a message and update conversation timestamp
CREATE PROCEDURE SP_SendMessage (
    IN p_ConversationID INT,
    IN p_SenderUserID INT,
    IN p_MessageContent TEXT
)
BEGIN
    DECLARE newMessageID INT;
    DECLARE v_BranchID INT;

    -- Get the sender's BranchID
    SELECT BranchID INTO v_BranchID
    FROM Accounts
    WHERE UserID = p_SenderUserID;

    -- Insert the new message
    INSERT INTO ChatMessages (ConversationID, SenderUserID, BranchID, MessageContent)
    VALUES (p_ConversationID, p_SenderUserID, v_BranchID, p_MessageContent);

    SET newMessageID = LAST_INSERT_ID();

    -- Update the conversation's last message timestamp
    UPDATE ChatConversations
    SET LastMessageTimestamp = CURRENT_TIMESTAMP
    WHERE ConversationID = p_ConversationID;

    -- Return the newly created message
    SELECT
        m.MessageID,
        m.SenderUserID,
        m.MessageContent,
        m.Timestamp,
        a.FirstName,
        a.LastName
    FROM ChatMessages m
    JOIN Accounts a ON m.SenderUserID = a.UserID
    WHERE m.MessageID = newMessageID;
END //

-- 6. Update a user's last read timestamp for a conversation
CREATE PROCEDURE SP_UpdateLastRead (
    IN p_ConversationID INT,
    IN p_UserID INT
)
BEGIN
    UPDATE ChatParticipants
    SET LastReadTimestamp = CURRENT_TIMESTAMP
    WHERE ConversationID = p_ConversationID AND UserID = p_UserID;
END //

-- 7. Find or Create a 1-on-1 Conversation (fixed)
CREATE PROCEDURE SP_FindOrCreateConversation (
    IN p_User1_ID INT,
    IN p_User2_ID INT
)
BEGIN
    DECLARE v_ConversationID INT DEFAULT NULL;
    DECLARE v_BranchID1 INT;
    DECLARE v_BranchID2 INT;

    -- Get BranchIDs for both users
    SELECT BranchID INTO v_BranchID1 FROM Accounts WHERE UserID = p_User1_ID;
    SELECT BranchID INTO v_BranchID2 FROM Accounts WHERE UserID = p_User2_ID;

    -- Try to find existing 1-on-1 conversation between the two users
    SELECT cp1.ConversationID
    INTO v_ConversationID
    FROM ChatParticipants cp1
    JOIN ChatParticipants cp2 ON cp1.ConversationID = cp2.ConversationID
    WHERE cp1.UserID = p_User1_ID
      AND cp2.UserID = p_User2_ID
    GROUP BY cp1.ConversationID
    HAVING COUNT(*) = 2
    LIMIT 1;

    -- If not found, create new conversation and add participants
    IF v_ConversationID IS NULL THEN
        INSERT INTO ChatConversations (LastMessageTimestamp) VALUES (CURRENT_TIMESTAMP);
        SET v_ConversationID = LAST_INSERT_ID();

        INSERT INTO ChatParticipants (ConversationID, UserID, BranchID, LastReadTimestamp)
        VALUES (v_ConversationID, p_User1_ID, v_BranchID1, CURRENT_TIMESTAMP);

        INSERT INTO ChatParticipants (ConversationID, UserID, BranchID, LastReadTimestamp)
        VALUES (v_ConversationID, p_User2_ID, v_BranchID2, CURRENT_TIMESTAMP);
    END IF;

    -- Return the conversation id
    SELECT v_ConversationID AS ConversationID;
END //

DELIMITER //

-- Stored Procedure to Get Alerts for a Branch
CREATE PROCEDURE SP_GetAlerts (
    IN p_BranchID INT
)
BEGIN
    SELECT
        m.MedicineName,
        bi.Stocks,
        bi.ExpiryDate,
        CASE
            WHEN bi.Stocks = 0 THEN 'Out of Stock'
            WHEN bi.Stocks > 0 AND bi.Stocks <= 10 THEN 'Low Stock'
            WHEN bi.ExpiryDate < CURDATE() THEN 'Expired'
            WHEN bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
            ELSE 'Active'
        END AS AlertType
    FROM BranchInventory bi
    JOIN medicines m ON bi.MedicineID = m.MedicineID
    WHERE bi.BranchID = p_BranchID
    AND (
        bi.Stocks = 0 OR
        (bi.Stocks > 0 AND bi.Stocks <= 10) OR
        bi.ExpiryDate < CURDATE() OR
        bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    )
    ORDER BY
        CASE AlertType
            WHEN 'Out of Stock' THEN 1
            WHEN 'Low Stock' THEN 2
            WHEN 'Expiring Soon' THEN 3
            WHEN 'Expired' THEN 4
        END,
        m.MedicineName;
END //

DELIMITER ;

CREATE TABLE Categories (
    CategoryID INT PRIMARY KEY AUTO_INCREMENT,
    CategoryName VARCHAR(50) UNIQUE NOT NULL,
    Description TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Populate Categories table with existing category names from the old ENUM
INSERT INTO Categories (CategoryName) VALUES
('Analgesic'),
('Antibiotic'),
('Antiseptic'),
('Antipyretic'),
('Antihistamine'),
('Antiviral'),
('Antifungal'),
('Cardiovascular'),
('Cough and Cold'),
('Diuretic'),
('Gastrointestinal'),
('Hormonal'),
('Nutritional Supplement'),
('Pain Relief'),
('Respiratory'),
('Sedative'),
('Topical'),
('Vaccine'),
('Vitamin/Mineral'),
('Other'),
('Antacid'),
('Antidiabetic'),
('Antihypertensive'),
('Cholesterol'),
('Anticonvulsant'),
('Thyroid'),
('Antidepressant'),
('Anticoagulant'),
('Biologic'),
('Urological'),
('Erectile Dysfunction'),
('Supplement'),
('Sleep Aid'),
('Cardio'),
('Cough/Cold'),
('Corticosteroid')
ON DUPLICATE KEY UPDATE CategoryName = CategoryName;


CREATE INDEX idx_chat_messages_conversation ON ChatMessages(ConversationID, Timestamp);
CREATE INDEX idx_accounts_usercode ON Accounts(UserCode);

-- BranchInventory queries
CREATE INDEX idx_branch_inventory_branchid ON BranchInventory(BranchID);
CREATE INDEX idx_branch_inventory_medicineid ON BranchInventory(MedicineID);
CREATE INDEX idx_branch_inventory_expiry ON BranchInventory(ExpiryDate);
CREATE INDEX idx_branch_inventory_stocks ON BranchInventory(Stocks);

-- Accounts queries
CREATE INDEX idx_accounts_branchid ON Accounts(BranchID);
CREATE INDEX idx_accounts_role ON Accounts(Role);
CREATE INDEX idx_accounts_status ON Accounts(AccountStatus);

-- Chat queries
CREATE INDEX idx_chat_messages_conversation_timestamp ON ChatMessages(ConversationID, Timestamp);
CREATE INDEX idx_chat_participants_userid ON ChatParticipants(UserID);
CREATE INDEX idx_chat_participants_conversation ON ChatParticipants(ConversationID);

-- Transaction queries
CREATE INDEX idx_sales_transactions_branchid_date ON SalesTransactions(BranchID, TransactionDateTime);
CREATE INDEX idx_sales_transactions_userid ON SalesTransactions(UserID);
CREATE INDEX idx_transaction_items_transactionid ON TransactionItems(TransactionID);

-- Ensure positive stock values
ALTER TABLE BranchInventory 
ADD CONSTRAINT chk_stocks_positive CHECK (Stocks >= 0);

-- Ensure positive prices
ALTER TABLE BranchInventory 
ADD CONSTRAINT chk_price_positive CHECK (Price >= 0);

-- Ensure positive quantities
ALTER TABLE TransactionItems 
ADD CONSTRAINT chk_quantity_positive CHECK (Quantity > 0);

-- Ensure positive subtotals
ALTER TABLE TransactionItems 
ADD CONSTRAINT chk_subtotal_positive CHECK (Subtotal >= 0);

-- Ensure valid expiry dates (not in past for new entries)
-- Note: This would need to be enforced at application level
-- or use a trigger


---Some tables lack audit information

-- Add to BranchInventory
ALTER TABLE BranchInventory 
ADD COLUMN CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN CreatedBy INT,
ADD COLUMN UpdatedBy INT,
ADD FOREIGN KEY (CreatedBy) REFERENCES Accounts(UserID),
ADD FOREIGN KEY (UpdatedBy) REFERENCES Accounts(UserID);

-- Add to SalesTransactions 
ALTER TABLE SalesTransactions 
ADD COLUMN UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

