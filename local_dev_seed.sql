-- =====================================================================
-- local_dev_seed.sql
--
-- SQLite-compatible schema + seed data for local development of qrsume,
-- reverse-engineered from the raw SQL / db_insert() / db_update() calls
-- found in the PHP source (no .sql schema file exists in the repo).
--
-- Load into the sqlite file that assets/db.php opens when running under
-- PHP's built-in dev server (php -S), e.g.:
--   sqlite3 local_dev.sqlite < local_dev_seed.sql
--
-- Seed user: username "testuser" / password "Test1234!" / privilege "admin"
-- Password hash below was produced by actually running:
--   php -r "echo password_hash('Test1234!', PASSWORD_DEFAULT);"
-- Result: $2y$12$y6ychZgpV307bPthXhE14uoaJNtHZlLZq8SEap/4GriNB6JwwTmmO
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------
-- users
-- Source: assets/register.php (INSERT INTO users ...), assets/login.php
-- (SELECT id, username, password_hash, privilege ...), admin/admin_users.php
-- (SELECT id, username, email, privilege, created_at, updated_at ... /
-- UPDATE users SET username, email, privilege, updated_at ...),
-- admin/admin_login_as_user.php (SELECT id, username ...)
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_verified   INTEGER NOT NULL DEFAULT 0,
    privilege     TEXT NOT NULL DEFAULT 'user', -- values seen in code: 'user', 'admin', 'banned'
    created_at    TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at    TEXT DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------------------
-- personalinfo (per-user, quick_access_table)
-- Source: assets/register.php INSERT INTO personalinfo(user_id,
-- personal_name, personal_lastname, personal_profession, personal_bio,
-- cv_url); create_resume/upload/save_profile.php and
-- assets/upload/upload_profile.php add/use `id`, `personal_photo`.
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS personalinfo (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id               INTEGER NOT NULL,
    personal_photo        TEXT DEFAULT 'default.webp',
    personal_name         TEXT,
    personal_lastname     TEXT,
    personal_profession   TEXT,
    personal_bio          TEXT,
    cv_url                TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- contactinfo (per-user, quick_access_table)
-- Source: assets/register.php INSERT INTO contactinfo(user_id,
-- phone_number, email, github, linkedin, twitter); assets/upload/
-- upload_contact.php and create_resume/upload/save_contactinfo.php add
-- `facebook`. Code always looks records up / updates by user_id alone
-- (SELECT user_id FROM contactinfo WHERE user_id = ?), so user_id is
-- treated as unique per user.
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contactinfo (
    user_id      INTEGER PRIMARY KEY,
    phone_number TEXT,
    email        TEXT,
    github       TEXT,
    facebook     TEXT,
    linkedin     TEXT,
    twitter      TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- aptitudes (per-user, quick_access_table)
-- Source: assets/register.php, assets/upload/upload_aptitude.php
-- (aptitude_id PK), create_resume/upload/save_aptitudes.php
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS aptitudes (
    aptitude_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    aptitude    TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- education (per-user, quick_access_table)
-- Source: assets/register.php, assets/upload/upload_education.php
-- (education_id PK), create_resume/upload/save_education.php
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS education (
    education_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id             INTEGER NOT NULL,
    date                TEXT,
    place_of_study      TEXT,
    name_of_studies     TEXT,
    brief_description   TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- experience (per-user, quick_access_table)
-- Source: assets/register.php, assets/upload/upload_experience.php
-- (experience_id PK), create_resume/upload/save_experience.php
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS experience (
    experience_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id             INTEGER NOT NULL,
    date                TEXT,
    place_of_work       TEXT,
    job_name            TEXT,
    brief_description   TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- interests (per-user, quick_access_table)
-- Source: assets/register.php, assets/upload/upload_interest.php
-- (interest_id PK), create_resume/upload/save_projects.php (despite the
-- filename, this form writes/reads the `interests` table using
-- interest_id/interest/description).
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS interests (
    interest_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    interest    TEXT,
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- languages (per-user, quick_access_table)
-- Source: assets/register.php, assets/upload/upload_language.php
-- (languages_id PK), create_resume/upload/save_languages.php
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS languages (
    languages_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL,
    language     TEXT,
    level        TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- custom_sections (per-user, quick_access_table)
-- Source: assets/upload/upload_custom_section.php (section_id PK),
-- create_resume/upload/save_custom_sections.php
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS custom_sections (
    section_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    section_title   TEXT,
    section_content TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- visibility_settings (per-user, quick_access_table)
-- Source: assets/db.php ($visibility[$element["field_name"]] =
-- $element["is_visible"]), create_resume/upload/save_contactinfo.php
-- (INSERT/UPDATE INTO visibility_settings (user_id, field_name,
-- is_visible)). field_name values actually checked against $visibility
-- in index.php are 'phone' and 'email'.
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visibility_settings (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    field_name TEXT NOT NULL,
    is_visible INTEGER NOT NULL DEFAULT 1,
    UNIQUE (user_id, field_name),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- resumes
-- Source: resumes/index.php (SELECT * FROM resumes WHERE user_id = :uid
-- ORDER BY updated_at DESC; columns read: title, description, version,
-- last_used, updated_at, tags, resume_id), resumes/preview_resume.php
-- (SELECT * FROM resumes WHERE resume_id = :resume_id AND user_id =
-- :user_id; column `data` holds a JSON blob).
-- No INSERT statement for this table was found anywhere in the repo
-- (resumes/edit_resume.php is an empty placeholder file), so no seed
-- rows are added -- only best-effort columns from the SELECT usages.
-- Confidence: MEDIUM (columns inferred only from reads, not from any
-- INSERT/UPDATE statement)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS resumes (
    resume_id   INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    title       TEXT,
    description TEXT,
    version     TEXT,
    last_used   TEXT,
    tags        TEXT,
    data        TEXT, -- JSON blob (personal_info, contact_info, education, experience, skills, languages, projects, custom_sections)
    created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at  TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- photos
-- Source: assets/upload_photo.php (INSERT INTO photos (user_id,
-- photo_url) VALUES ...), still actively linked from assets/form.php.
-- Note: the *current* profile-picture flow (assets/upload/
-- upload_profile_picture.php) actually writes to
-- personalinfo.personal_photo instead of this table -- this table is
-- used by the separate article-photo-upload flow reached from
-- assets/form.php.
-- Confidence: MEDIUM (only 2 columns ever referenced; no id/created_at
-- column is read anywhere, but one is assumed for a sane PK)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS photos (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    photo_url  TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- static_pages
-- Source: admin/admin_pages.php (INSERT INTO static_pages (page_slug,
-- page_title, page_content) ... ON DUPLICATE KEY UPDATE -- page_slug is
-- the natural/unique key), page.php (SELECT page_title, page_content
-- FROM static_pages WHERE page_slug = :slug)
-- Confidence: HIGH
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS static_pages (
    page_slug    TEXT PRIMARY KEY,
    page_title   TEXT NOT NULL,
    page_content TEXT
);

-- ---------------------------------------------------------------------
-- blogarticles
-- Source: assets/create_article.php (INSERT/UPDATE ... article_title,
-- user_id, article_date, article_content, article_tags,
-- article_summary, article_status, article_photo; also queries a
-- `slug` column), assets/delete_article.php (DELETE ... WHERE
-- article_id = ...; displays `views`), assets/blog.php / blog.php
-- (SELECT * ... WHERE article_status = 'published'), assets/upload/
-- upload_profile_picture.php (UPDATE blogarticles SET article_photo
-- ... WHERE user_id = ? AND article_id = ?)
-- Confidence: HIGH (article_id, user_id, article_title, article_date,
-- article_content, article_tags, article_summary, article_status,
-- article_photo); MEDIUM for slug/views (referenced in SELECT/WHERE
-- clauses and displayed, but no INSERT ever sets them explicitly)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blogarticles (
    article_id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL,
    article_title   TEXT,
    article_date    TEXT,
    article_content TEXT,
    article_tags    TEXT,
    article_summary TEXT,
    article_status  TEXT DEFAULT 'draft', -- values seen in code: 'draft', 'published'
    article_photo   TEXT DEFAULT 'default.webp',
    slug            TEXT,
    views           INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- page_stats
-- Source: assets/stats.php recordStat() -- INSERT INTO page_stats
-- (user_id, stat_date, {type}) VALUES (:uid, CURDATE(), 1) ON DUPLICATE
-- KEY UPDATE {type} = {type} + 1, where {type} is one of
-- 'views'/'downloads'/'qr_scans'. Unique key is (user_id, stat_date).
-- No seed rows added (per instructions, daily stat rows aren't needed
-- to click through the site).
-- Confidence: HIGH for columns; no seed data.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_stats (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id   INTEGER NOT NULL,
    stat_date TEXT NOT NULL,
    views     INTEGER DEFAULT 0,
    downloads INTEGER DEFAULT 0,
    qr_scans  INTEGER DEFAULT 0,
    UNIQUE (user_id, stat_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- user_statistics
-- Source: assets/stats.php recordStat() -- INSERT INTO user_statistics
-- (user_id, {type}) VALUES (:uid, 1) ON DUPLICATE KEY UPDATE {type} =
-- {type} + 1. Lifetime counters, one row per user (unique on user_id).
-- assets/update_statistics.php (which also touched this table) has
-- already been deleted as dead code, but assets/stats.php still
-- references this table live, so it is kept.
-- No seed rows added.
-- Confidence: HIGH for columns; no seed data.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_statistics (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id   INTEGER NOT NULL UNIQUE,
    views     INTEGER DEFAULT 0,
    downloads INTEGER DEFAULT 0,
    qr_scans  INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- user_purchases
-- Source: stripe_webhook.php (INSERT INTO user_purchases (user_id,
-- product_key, stripe_checkout_session_id, stripe_payment_intent_id,
-- amount_paid, currency, status) ... ON DUPLICATE KEY UPDATE), keyed by
-- stripe_checkout_session_id. checkout_remove_branding.php creates the
-- Stripe Checkout session (product_key = 'remove_qrsume_branding') that
-- the webhook later records here. No seed rows added -- purchase state
-- isn't needed to click through the site and is easy to get wrong.
-- Confidence: HIGH for columns; no seed data.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_purchases (
    id                          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id                     INTEGER NOT NULL,
    product_key                 TEXT NOT NULL,
    stripe_checkout_session_id  TEXT UNIQUE,
    stripe_payment_intent_id    TEXT,
    amount_paid                 INTEGER,
    currency                    TEXT,
    status                      TEXT DEFAULT 'pending',
    created_at                  TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- email_tracking
-- Source: email/track_open.php (INSERT INTO email_tracking (email_id,
-- recipient_email, ip_address, user_agent, is_first_open) VALUES (...,
-- TRUE)). No seed rows added -- purely a tracking-pixel log, not
-- needed to click through the site.
-- Confidence: HIGH for columns; no seed data.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_tracking (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email_id        TEXT,
    recipient_email TEXT,
    ip_address      TEXT,
    user_agent      TEXT,
    is_first_open   INTEGER DEFAULT 0,
    created_at      TEXT DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Seed user: testuser / Test1234! / admin (so admin/* pages are reachable)
INSERT INTO users (id, username, email, password_hash, is_verified, privilege, created_at, updated_at)
VALUES (
    1,
    'testuser',
    'test@example.com',
    '$2y$12$y6ychZgpV307bPthXhE14uoaJNtHZlLZq8SEap/4GriNB6JwwTmmO',
    1,
    'admin',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

INSERT INTO personalinfo (user_id, personal_photo, personal_name, personal_lastname, personal_profession, personal_bio, cv_url)
VALUES (
    1,
    'default.webp',
    'Test',
    'User',
    'Software Engineer',
    'Building things end to end, from backend APIs to polished front ends.',
    'pdf2.php?username=testuser'
);

INSERT INTO contactinfo (user_id, phone_number, email, github, facebook, linkedin, twitter)
VALUES (
    1,
    '555-123-4567',
    'test@example.com',
    'https://github.com/testuser',
    'https://facebook.com/testuser',
    'https://linkedin.com/in/testuser',
    'https://twitter.com/testuser'
);

INSERT INTO education (user_id, date, place_of_study, name_of_studies, brief_description)
VALUES (
    1,
    '2018 - 2022',
    'State University, Testville',
    'B.Sc. in Computer Science',
    'Studied algorithms, databases and web development; graduated with honors.'
);

INSERT INTO experience (user_id, date, place_of_work, job_name, brief_description)
VALUES (
    1,
    '2022 - Present',
    'Acme Corp, Testville',
    'Software Engineer',
    'Built and maintained internal tools and customer-facing web applications.'
);

INSERT INTO interests (user_id, interest, description)
VALUES
    (1, 'Open Source', 'Contributes to open-source projects in spare time.'),
    (1, 'Hiking', 'Enjoys exploring trails on weekends.');

INSERT INTO languages (user_id, language, level)
VALUES
    (1, 'English', 'Fluent'),
    (1, 'Spanish', 'Intermediate');

INSERT INTO aptitudes (user_id, aptitude)
VALUES
    (1, 'Teamwork'),
    (1, 'Problem Solving'),
    (1, 'Communication');

INSERT INTO custom_sections (user_id, section_title, section_content)
VALUES
    (1, 'Certifications', 'AWS Certified Developer - Associate (2023)'),
    (1, 'Volunteering', 'Weekly volunteer tutor for local coding bootcamp.');

-- field_name values actually read by index.php via $visibility[...]: 'phone', 'email'
INSERT INTO visibility_settings (user_id, field_name, is_visible)
VALUES
    (1, 'phone', 1),
    (1, 'email', 1);

-- A couple of static pages so admin/admin_pages.php and page.php have data
INSERT INTO static_pages (page_slug, page_title, page_content)
VALUES
    ('aboutus', 'About Us', '<p>QRsume turns your resume into a dynamic, always up-to-date profile.</p>'),
    ('termsandconditions', 'Terms and Conditions', '<p>Placeholder terms and conditions for local development.</p>');

-- A couple of blog articles so blog.php / assets/blog.php / article.php have data
INSERT INTO blogarticles (user_id, article_title, article_date, article_content, article_tags, article_summary, article_status, article_photo, slug, views)
VALUES
    (1, 'Welcome to My Blog', '2024-01-15', '<p>This is my first post, sharing thoughts on my career so far.</p>', 'career,intro', 'A quick introduction post.', 'published', 'default.webp', 'welcome-to-my-blog', 12),
    (1, 'Tips for a Great Resume', '2024-03-02', '<p>A few tips I have learned for building a standout resume.</p>', 'resume,tips', 'Practical resume-writing tips.', 'published', 'default.webp', 'tips-for-a-great-resume', 34);

-- NOTE: no seed rows for resumes, photos, user_purchases, page_stats,
-- user_statistics or email_tracking -- see the notes above each CREATE
-- TABLE statement for why each was left empty.
