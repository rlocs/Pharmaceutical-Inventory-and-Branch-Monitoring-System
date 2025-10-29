-- Create database if not exists (user to run: CREATE DATABASE pharma_db;)
-- Then use pharmaceutical_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Staff') NOT NULL,
    dob DATE NOT NULL
);


-- SQL to create the medicines table
CREATE TABLE medicines (
    medicine_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL, -- E.g., 'Analgesic', 'Antibiotic', 'Vitamins'
    price DECIMAL(10, 2) NOT NULL, -- Price in Peso
    stock_quantity INT NOT NULL,   -- Current stock level
    min_stock_threshold INT NOT NULL DEFAULT 10, -- Low-stock alert threshold (Your API needs this)
    expiry_date DATE,              -- Expiration date (Your API needs this)
    short_description TEXT,        -- Details that show on click
    image_url VARCHAR(255),        -- Placeholder for the medicine picture
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Sample users with hashed passwords (password for all: 'password123')
-- Hashes generated via password_hash('password123', PASSWORD_DEFAULT)



INSERT INTO users (username, password, role, dob) VALUES
('admin1', '$2y$10$L12QTm99BTjPO.fShbumJ.TAtcLvYLotPpei4YGUN4pcEB5JVri4W', 'Admin', '1990-01-01'),
('staff1', '$2y$10$HxlRwSLqlm4JHSu0Crj9xOvoJ6diX/299zG58w7KknWYVB3RhLaRe', 'Staff', '1985-05-15');


INSERT INTO medicines (name, category, price, stock_quantity, min_stock_threshold, expiry_date, short_description, image_url) VALUES
-- ANALGESICS / PAIN RELIEF (Items 1-5)
('Paracetamol 500mg (Generic)', 'Analgesics', 2.50, 250, 50, '2027-08-10', 'Relieves mild to moderate pain and reduces fever.', 'placehold.co/100x100?text=P'),
('Ibuprofen 200mg (Fast Act)', 'Analgesics', 4.75, 0, 20, '2026-05-20', 'Anti-inflammatory drug for pain, swelling, and fever.', 'placehold.co/100x100?text=I'),
('Mefenamic Acid 500mg', 'Analgesics', 8.90, 150, 30, '2027-01-25', 'Used for dysmenorrhea and mild to moderate pain.', 'placehold.co/100x100?text=M'),
('Naproxen Sodium 220mg', 'Analgesics', 11.25, 90, 15, '2026-07-01', 'Long-lasting pain and fever relief.', 'placehold.co/100x100?text=N'),
('Co-codamol 30/500mg', 'Analgesics', 15.00, 8, 10, '2027-04-15', 'LOW STOCK ALERT. Stronger pain relief combination.', 'placehold.co/100x100?text=C'),

-- ANTIBIOTICS (Items 6-10)
('Amoxicillin 500mg Capsule', 'Antibiotics', 16.50, 120, 25, '2026-06-18', 'Broad-spectrum penicillin antibiotic.', 'placehold.co/100x100?text=A'),
('Azithromycin 250mg Tablet', 'Antibiotics', 35.00, 15, 20, '2025-11-28', 'NEAR EXPIRY ALERT. Used to treat bacterial infections.', 'placehold.co/100x100?text=Z'),
('Ciprofloxacin 500mg', 'Antibiotics', 22.00, 65, 15, '2027-02-10', 'Fluoroquinolone antibiotic for various infections.', 'placehold.co/100x100?text=Q'),
('Metronidazole 400mg', 'Antibiotics', 10.50, 80, 20, '2026-09-05', 'Treats bacterial and parasitic infections.', 'placehold.co/100x100?text=E'),
('Doxycycline 100mg', 'Antibiotics', 5.50, 4, 10, '2027-03-01', 'LOW STOCK ALERT. Tetracycline antibiotic.', 'placehold.co/100x100?text=D'),

-- VITAMINS / SUPPLEMENTS (Items 11-15)
('Vitamin C (Ascorbic Acid) 100mg', 'Vitamins/Supplements', 1.50, 400, 50, '2028-01-01', 'Immune system support and antioxidant.', 'placehold.co/100x100?text=VC'),
('Multivitamins + Minerals', 'Vitamins/Supplements', 6.00, 0, 10, '2026-04-01', 'OUT OF STOCK ALERT. Daily essential nutrients.', 'placehold.co/100x100?text=MV'),
('Vitamin B Complex', 'Vitamins/Supplements', 3.00, 25, 15, '2025-12-15', 'NEAR EXPIRY ALERT. Supports metabolism and nerve function.', 'placehold.co/100x100?text=VB'),
('Calcium Carbonate 600mg', 'Vitamins/Supplements', 7.50, 110, 20, '2027-10-20', 'Bone health supplement.', 'placehold.co/100x100?text=CA'),
('Ferrous Sulfate (Iron)', 'Vitamins/Supplements', 2.00, 9, 10, '2027-05-11', 'LOW STOCK ALERT. Used to treat iron deficiency anemia.', 'placehold.co/100x100?text=FE'),

-- COUGH, COLD & FLU (Items 16-20)
('Dextromethorphan (Cough Syrup) 120ml', 'Cough/Cold/Flu', 65.00, 55, 15, '2027-03-22', 'Non-drowsy cough suppressant.', 'placehold.co/100x100?text=CS'),
('Loratadine 10mg (Anti-allergy)', 'Cough/Cold/Flu', 5.00, 130, 30, '2026-11-10', 'Relieves symptoms of allergic rhinitis.', 'placehold.co/100x100?text=L'),
('Phenylephrine (Decongestant)', 'Cough/Cold/Flu', 3.50, 75, 20, '2027-06-01', 'Nasal and sinus decongestant.', 'placehold.co/100x100?text=P'),
('Ambroxol Syrup 60ml', 'Cough/Cold/Flu', 45.00, 15, 10, '2026-08-08', 'Mucolytic for cough with phlegm.', 'placehold.co/100x100?text=A'),
('Fluconazole 150mg', 'Cough/Cold/Flu', 80.00, 1, 5, '2027-09-01', 'LOW STOCK ALERT. Antifungal medication.', 'placehold.co/100x100?text=F'),

-- GASTROINTESTINAL (Items 21-25)
('Loperamide 2mg (Anti-Diarrheal)', 'Gastrointestinal', 6.00, 200, 40, '2028-02-14', 'Reduces the frequency of diarrhea.', 'placehold.co/100x100?text=LO'),
('Ranitidine 150mg (Acid Reducer)', 'Gastrointestinal', 9.00, 0, 15, '2026-01-01', 'OUT OF STOCK ALERT. Treats stomach and intestinal ulcers.', 'placehold.co/100x100?text=R'),
('Omeprazole 20mg', 'Gastrointestinal', 18.00, 30, 15, '2025-12-30', 'NEAR EXPIRY ALERT. Proton pump inhibitor (PPI).', 'placehold.co/100x100?text=O'),
('Aluminum Hydroxide (Antacid)', 'Gastrointestinal', 4.00, 100, 25, '2027-11-01', 'Relieves heartburn and indigestion.', 'placehold.co/100x100?text=AL'),
('Bisacodyl 5mg (Laxative)', 'Gastrointestinal', 3.50, 70, 15, '2026-03-28', 'Stimulant laxative.', 'placehold.co/100x100?text=BI'),

-- DERMATOLOGICAL / MISC (Items 26-30)
('Clotrimazole Cream 1%', 'Dermatological', 40.00, 60, 10, '2027-08-01', 'Antifungal cream for skin infections.', 'placehold.co/100x100?text=CL'),
('Hydrocortisone Cream 0.5%', 'Dermatological', 25.00, 12, 10, '2025-12-05', 'NEAR EXPIRY ALERT. For skin irritation and inflammation.', 'placehold.co/100x100?text=HY'),
('Povidone-Iodine Solution 15ml', 'Dermatological', 35.00, 95, 20, '2028-05-15', 'Antiseptic solution for wounds.', 'placehold.co/100x100?text=PO'),
('Insulin Glargine Pen', 'Miscellaneous', 750.00, 45, 10, '2026-09-01', 'Long-acting insulin for diabetes.', 'placehold.co/100x100?text=IN'),
('Blood Pressure Monitor', 'Medical Devices', 1500.00, 20, 5, '2200-01-01', 'Digital device for home use.', 'placehold.co/100x100?text=BP');