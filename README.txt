MEDICINE POS (XAMPP-ready)

Instructions:
1. Import the SQL:
   - Start XAMPP (Apache + MySQL)
   - Open http://localhost/phpmyadmin
   - Import 'medicine_system.sql' (it creates DB and table with sample data)

2. Deploy files:
   - Copy all PHP files and assets into XAMPP's htdocs folder, e.g. C:\xampp\htdocs\med_pos\
   - Or extract the ZIP into htdocs/med_pos/

3. Access:
   - Open http://localhost/med_pos/index.php

Notes:
- DB credentials are in config.php (default: root, empty password). Change if you use a password.
- The app reads medicines from the MySQL table 'medicines'.
- On checkout the invoice is sent via POST and displayed for printing.

If you want:
- Add save-to-database orders, user login, or improved UI, I can extend it.
