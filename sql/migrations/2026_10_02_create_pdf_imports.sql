-- CV imports from PDF (create_resume form, PDFtoCV).
--
-- One row per import. Each account gets 1 free import, plus 5 more with the
-- premium purchase (user_purchases.product_key = 'remove_qrsume_branding').
-- An import is written as 'pending' before the PDF is sent to Adobe, set to
-- 'done' when it worked, and deleted when it failed, so failures never count.
--
-- Run this BEFORE deploying the PHP that uses it.

CREATE TABLE IF NOT EXISTS pdf_imports (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    INDEX idx_pdf_imports_user (user_id)
);
