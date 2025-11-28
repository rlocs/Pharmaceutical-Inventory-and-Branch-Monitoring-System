# TODO: Make POS Fully Functional

## Information Gathered
- POS system is located in branch1/pos.php, uses inline JavaScript for logic, and submits to includes/invoice.php for transactions.
- Database schema in pharmaceutical_db.sql has tables for medicines, BranchInventory, SalesTransactions, TransactionItems.
- Sample data inserted for 50 medicines across 3 branches.
- pos.php fetches medicines from BranchInventory where BranchID=1, Stocks>0, Status='Active'.
- invoice.php processes transactions, inserts into SalesTransactions and TransactionItems, updates Stocks in BranchInventory.
- Status field in BranchInventory is static, not updated when Stocks change.
- filterMeds is defined in pos.php, so keep onkeyup="filterMeds()".
- pos.js exists but is not included in pos.php; it has duplicate logic.

## Plan
1. Replaced invoice.php with PDO-compatible version that includes stock checking, deduction, and status update logic.
2. Kept pos.php as is, including onkeyup="filterMeds()".
3. Ensured transaction processing works correctly, including stock updates and status updates.
4. Tested that medicines are displayed and transactions can be made.

## Dependent Files to be edited
- branch1/includes/invoice.php: Replaced with new PDO code.

## Followup steps
- Verify database is set up with pharmaceutical_db.sql.
- Test POS by loading page, checking medicines display, making a transaction.
- Check that stock updates correctly and status is updated.
- Test negative scenarios like insufficient stock.
