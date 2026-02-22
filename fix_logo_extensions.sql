-- SQL script to update logo file extensions to .webp in rent_model_web
-- This will only update rows where the extension is currently .png, .jpg, or .jpeg

UPDATE rent_model_web 
SET logo = REPLACE(REPLACE(REPLACE(logo, '.png', '.webp'), '.jpg', '.webp'), '.jpeg', '.webp')
WHERE logo LIKE '%.png' 
   OR logo LIKE '%.jpg' 
   OR logo LIKE '%.jpeg';

-- Verification query
-- SELECT logo FROM rent_model_web WHERE logo LIKE '%.webp';
