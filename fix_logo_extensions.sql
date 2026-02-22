-- SQL script to fix logo paths on production
-- 1. Fix double extensions (e.g., .webp.webp -> .webp)
UPDATE rent_model_web 
SET logo = REPLACE(logo, '.webp.webp', '.webp') 
WHERE logo LIKE '%.webp.webp%';

-- 2. Clean up absolute server paths (convert to relative paths starting with /)
UPDATE rent_model_web 
SET logo = REPLACE(logo, '/home/tiktakby/public_html/', '/') 
WHERE logo LIKE '/home/tiktakby/public_html/%';

-- 3. Standard fix for remaining non-webp images (if any)
UPDATE rent_model_web 
SET logo = REPLACE(REPLACE(REPLACE(logo, '.png', '.webp'), '.jpg', '.webp'), '.jpeg', '.webp')
WHERE (logo LIKE '%.png' OR logo LIKE '%.jpg' OR logo LIKE '%.jpeg')
  AND logo NOT LIKE '%.webp%';

-- Verification
-- SELECT logo FROM rent_model_web WHERE logo LIKE '%.webp.webp%' OR logo LIKE '/home/%';
