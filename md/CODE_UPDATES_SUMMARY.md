# Code Updates Summary - Database Schema Migration

## Overview
This document summarizes all code changes made to support the new database schema with Categories table and audit fields.

---

## Database Changes Implemented

### 1. Categories Table
- ✅ Created `Categories` table with `CategoryID`, `CategoryName`, `Description`, `CreatedAt`
- ✅ Changed `medicines.Category` (ENUM) to `medicines.CategoryID` (INT, FK)

### 2. Indexes Added
- ✅ BranchInventory indexes (BranchID, MedicineID, ExpiryDate, Stocks)
- ✅ Accounts indexes (BranchID, Role, AccountStatus, UserCode)
- ✅ Chat indexes (ConversationID+Timestamp, UserID, ConversationID)
- ✅ Transaction indexes (BranchID+DateTime, UserID, TransactionID)

### 3. CHECK Constraints
- ✅ Stocks >= 0
- ✅ Price >= 0
- ✅ Quantity > 0
- ✅ Subtotal >= 0

### 4. Audit Fields
- ✅ BranchInventory: `CreatedAt`, `UpdatedAt`, `CreatedBy`, `UpdatedBy`
- ✅ SalesTransactions: `UpdatedAt`

---

## Code Files Updated

### 1. `branch1/api/medicine_api.php`

#### Changes:
- ✅ Updated `getMedicines()` to JOIN with Categories table
- ✅ Changed `m.Category` to `c.CategoryName AS Category` in SELECT
- ✅ Updated category filter to use `c.CategoryID` instead of `m.Category`
- ✅ Updated `addMedicine()` to handle CategoryID:
  - Accepts CategoryID (numeric) or category name (string)
  - Finds or creates category if name provided
  - Handles "Others" option with custom category name
  - Adds `CreatedBy` to BranchInventory INSERT
- ✅ Updated `updateMedicine()` to handle CategoryID:
  - Same category handling as addMedicine
  - Adds `UpdatedBy` to BranchInventory UPDATE
- ✅ Added `getCategories()` function for API endpoint

#### Key Features:
- Backward compatible: Accepts both CategoryID and category name
- Auto-creates categories if they don't exist
- Handles "Others" option with custom category input

### 2. `branch1/med_inventory.php`

#### Changes:
- ✅ Updated category query: `SELECT CategoryID, CategoryName FROM Categories`
- ✅ Updated medicines query: Added `LEFT JOIN Categories c ON m.CategoryID = c.CategoryID`
- ✅ Changed category display: `c.CategoryName AS Category`
- ✅ Updated category select options: Use `CategoryID` as value, `CategoryName` as display
- ✅ Added "Others (Specify)" option to both Add and Edit modals

### 3. `branch1/js/medicine.js`

#### Changes:
- ✅ Updated category handling in `addMedicine()` and `updateMedicine()`
- ✅ Improved "Others" category logic:
  - If "Others" selected, sends category name from `otherCategory` field
  - If CategoryID provided, keeps it as is
  - API handles finding or creating the category
- ✅ Updated `toggleOtherCategory()` to handle new structure

---

## Migration Steps Required

### ⚠️ IMPORTANT: Data Migration Needed

Before the code changes work properly, you need to migrate existing data:

1. **Populate Categories Table:**
```sql
INSERT INTO Categories (CategoryName)
SELECT DISTINCT Category 
FROM medicines 
WHERE Category IS NOT NULL
ORDER BY Category;
```

2. **Update Existing Medicines:**
```sql
UPDATE medicines m
JOIN Categories c ON m.Category = c.CategoryName
SET m.CategoryID = c.CategoryID
WHERE m.Category IS NOT NULL;
```

3. **Handle NULL Categories:**
```sql
-- Create "Other" category if needed
INSERT INTO Categories (CategoryName) VALUES ('Other') 
ON DUPLICATE KEY UPDATE CategoryName = CategoryName;

-- Set NULL categories to "Other"
SET @other_id = (SELECT CategoryID FROM Categories WHERE CategoryName = 'Other');
UPDATE medicines SET CategoryID = @other_id WHERE CategoryID IS NULL;
```

4. **Then run the ALTER TABLE:**
```sql
ALTER TABLE medicines 
DROP COLUMN Category,
ADD COLUMN CategoryID INT,
ADD FOREIGN KEY (CategoryID) REFERENCES Categories(CategoryID);
```

**Note:** If you already ran the ALTER TABLE, you may need to:
- First add CategoryID column (if not exists)
- Migrate data
- Then drop Category column

See `MIGRATION_CATEGORIES.md` for detailed migration guide.

---

## API Changes

### New Endpoint
- **GET** `/api/medicine_api.php?action=get_categories`
  - Returns list of all categories with CategoryID and CategoryName

### Updated Endpoints
- **GET** `/api/medicine_api.php?action=get_medicines&category={CategoryID}`
  - Category filter now uses CategoryID (integer) instead of category name

- **POST** `/api/medicine_api.php?action=add_medicine`
  - Accepts `category` as CategoryID (integer) or category name (string)
  - If "Others" selected, expects `otherCategory` field with custom name
  - Automatically creates category if name provided and doesn't exist

- **POST** `/api/medicine_api.php?action=update_medicine`
  - Same category handling as add_medicine

---

## Breaking Changes

### ⚠️ Frontend Changes Required
- Category filter now uses CategoryID instead of category name
- Category select options now have CategoryID as value
- JavaScript needs to send CategoryID (not category name) for filtering

### ✅ Backward Compatibility
- API still accepts category names (auto-converts to CategoryID)
- "Others" option still works (creates new category)

---

## Testing Checklist

- [ ] Test adding medicine with existing category (CategoryID)
- [ ] Test adding medicine with "Others" option (custom category name)
- [ ] Test updating medicine category
- [ ] Test category filter dropdown
- [ ] Test category filter functionality
- [ ] Verify audit fields (CreatedBy, UpdatedBy) are populated
- [ ] Verify all medicines display with correct category names
- [ ] Test with empty Categories table (should handle gracefully)

---

## Files Modified

1. ✅ `branch1/api/medicine_api.php` - Updated all category-related queries and functions
2. ✅ `branch1/med_inventory.php` - Updated category queries and select options
3. ✅ `branch1/js/medicine.js` - Updated category handling logic

## Files Created

1. ✅ `MIGRATION_CATEGORIES.md` - Migration guide for existing data
2. ✅ `CODE_UPDATES_SUMMARY.md` - This file

---

## Notes

- All queries now use `LEFT JOIN Categories` to handle NULL CategoryID gracefully
- Category names are displayed as `CategoryName` from Categories table
- The code is backward compatible and will auto-create categories if needed
- Audit fields (CreatedBy, UpdatedBy) are automatically populated from session

---

*Last Updated: 2024*
*Migration Status: Code Updated - Data Migration Required*

