# Database Migration Checklist

## Pre-Migration

- [ ] **Backup Database** - Create full backup before making changes
- [ ] **Test Environment** - Test migration on development/staging first
- [ ] **Review Schema Changes** - Verify all ALTER TABLE statements

## Migration Steps

### Step 1: Create Categories Table
```sql
CREATE TABLE Categories (
    CategoryID INT PRIMARY KEY AUTO_INCREMENT,
    CategoryName VARCHAR(50) UNIQUE NOT NULL,
    Description TEXT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### Step 2: Populate Categories from Existing Data
```sql
INSERT INTO Categories (CategoryName)
SELECT DISTINCT Category 
FROM medicines 
WHERE Category IS NOT NULL
ORDER BY Category;
```

### Step 3: Add CategoryID Column (if not exists)
```sql
-- Check if column exists first
ALTER TABLE medicines 
ADD COLUMN CategoryID INT NULL;
```

### Step 4: Migrate Existing Data
```sql
UPDATE medicines m
JOIN Categories c ON m.Category = c.CategoryName
SET m.CategoryID = c.CategoryID
WHERE m.Category IS NOT NULL;
```

### Step 5: Handle NULL Categories
```sql
-- Create "Other" category
INSERT INTO Categories (CategoryName) VALUES ('Other') 
ON DUPLICATE KEY UPDATE CategoryName = CategoryName;

-- Set NULL to "Other"
SET @other_id = (SELECT CategoryID FROM Categories WHERE CategoryName = 'Other');
UPDATE medicines SET CategoryID = @other_id WHERE CategoryID IS NULL;
```

### Step 6: Add Foreign Key Constraint
```sql
ALTER TABLE medicines 
ADD FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID);
```

### Step 7: Make CategoryID NOT NULL (Optional)
```sql
ALTER TABLE medicines 
MODIFY COLUMN CategoryID INT NOT NULL;
```

### Step 8: Drop Old Category Column
```sql
ALTER TABLE medicines 
DROP COLUMN Category;
```

### Step 9: Add Indexes
```sql
-- Already in your SQL file, but verify they were created
SHOW INDEX FROM BranchInventory;
SHOW INDEX FROM Accounts;
SHOW INDEX FROM ChatMessages;
SHOW INDEX FROM SalesTransactions;
```

### Step 10: Add CHECK Constraints
```sql
-- Verify constraints were added
SELECT * FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'pharmaceutical_db' 
AND CONSTRAINT_TYPE = 'CHECK';
```

### Step 11: Add Audit Fields
```sql
-- Verify audit fields exist
DESCRIBE BranchInventory;
DESCRIBE SalesTransactions;
```

## Post-Migration Verification

- [ ] All medicines have CategoryID set
- [ ] All categories from old ENUM are in Categories table
- [ ] Foreign key constraint is working
- [ ] Indexes are created
- [ ] CHECK constraints are active
- [ ] Audit fields are present
- [ ] Test adding new medicine
- [ ] Test updating medicine
- [ ] Test category filter
- [ ] Test "Others" category option

## Rollback Plan

If migration fails:

1. Restore from backup
2. Review error logs
3. Fix issues
4. Retry migration

## Code Deployment

- [x] Updated `branch1/api/medicine_api.php`
- [x] Updated `branch1/med_inventory.php`
- [x] Updated `branch1/js/medicine.js`
- [ ] Update `branch2/api/medicine_api.php` (if exists)
- [ ] Update `branch2/med_inventory.php` (if exists)
- [ ] Update `branch3/api/medicine_api.php` (if exists)
- [ ] Update `branch3/med_inventory.php` (if exists)

---

*Migration Guide Created: 2024*

