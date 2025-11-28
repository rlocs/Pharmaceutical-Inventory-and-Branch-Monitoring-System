-- Populate Categories table with existing category names from the old ENUM
-- This should be run after creating the Categories table

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

-- Verify the categories were inserted
SELECT COUNT(*) as TotalCategories FROM Categories;
SELECT * FROM Categories ORDER BY CategoryName;

