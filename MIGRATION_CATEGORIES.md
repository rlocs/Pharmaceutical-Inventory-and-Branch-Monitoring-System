# Category Migration Guide

## Overview
This guide helps migrate existing data from the old Category ENUM to the new Categories table structure.

## Step 1: Populate Categories Table

Before running the ALTER TABLE statements, you need to populate the Categories table with all existing category values:

```sql
-- Insert all unique categories from existing medicines
INSERT INTO Categories (CategoryName)
SELECT DISTINCT Category 
FROM medicines 
WHERE Category IS NOT NULL
ORDER BY Category;
```

## Step 2: Update Existing Medicines

After creating the Categories table and populating it, update existing medicines to use CategoryID:

```sql
-- Update medicines to set CategoryID based on Category name
UPDATE medicines m
JOIN Categories c ON m.Category = c.CategoryName
SET m.CategoryID = c.CategoryID
WHERE m.Category IS NOT NULL;
```

## Step 3: Handle NULL Categories

If there are medicines with NULL categories:

```sql
-- Option 1: Set to a default "Other" category
INSERT INTO Categories (CategoryName) VALUES ('Other') ON DUPLICATE KEY UPDATE CategoryName = CategoryName;
SET @other_category_id = (SELECT CategoryID FROM Categories WHERE CategoryName = 'Other');
UPDATE medicines SET CategoryID = @other_category_id WHERE CategoryID IS NULL;

-- Option 2: Or leave as NULL if CategoryID allows NULL
-- (You may need to ALTER the column to allow NULL first)
```

## Step 4: Verify Migration

```sql
-- Check for any medicines without CategoryID
SELECT COUNT(*) as missing_categories 
FROM medicines 
WHERE CategoryID IS NULL;

-- Verify all categories were migrated
SELECT 
    (SELECT COUNT(DISTINCT Category) FROM medicines WHERE Category IS NOT NULL) as old_count,
    (SELECT COUNT(*) FROM Categories) as new_count;
```

## Step 5: Run ALTER TABLE

After data migration is complete, run the ALTER TABLE statements:

```sql
ALTER TABLE medicines 
DROP COLUMN Category,
ADD COLUMN CategoryID INT,
ADD FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID);
```

**Note:** If you already ran the ALTER TABLE, you may need to:
1. Add CategoryID column (if not exists)
2. Migrate data
3. Then drop Category column

## Important Notes

- **Backup your database** before running migration
- Test on a development environment first
- The code has been updated to work with CategoryID
- Existing medicines will need CategoryID set before the Category column can be dropped

