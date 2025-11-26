# Database Migration: Add DiscountAmount to SalesTransactions

## Migration SQL Script

```sql
ALTER TABLE SalesTransactions
ADD COLUMN DiscountAmount DECIMAL(10, 2) DEFAULT 0 NOT NULL AFTER TotalAmount;
```

## Description
Adds a new column `DiscountAmount` to store discount applied per transaction.

## Usage
Apply this migration script to the database before deploying POS discount feature code updates.

## Verification
- Confirm `DiscountAmount` column exists in `SalesTransactions` table.
- New POS transactions should save discount amount.
- Discount should appear correctly in receipts.

## Rollback
```sql
ALTER TABLE SalesTransactions DROP COLUMN DiscountAmount;
