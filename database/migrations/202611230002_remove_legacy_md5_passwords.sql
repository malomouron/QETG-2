-- One-time security migration for existing databases created with the legacy schema.
-- Back up the database before applying. This migration intentionally invalidates
-- MD5 credentials: affected members must use the password-reset-by-email flow.
-- Pending registrations protected only by MD5 are discarded and can be restarted.

ALTER TABLE quiz_membre
    CHANGE COLUMN pass_md5 password_hash VARCHAR(255) NOT NULL;

UPDATE quiz_membre
SET password_hash = ''
WHERE CHAR_LENGTH(password_hash) = 32
  AND password_hash REGEXP '^[0-9A-Fa-f]{32}$';

ALTER TABLE quiz_cle
    CHANGE COLUMN cle_pass_md5 cle_password_hash VARCHAR(255) NOT NULL;

DELETE FROM quiz_cle
WHERE CHAR_LENGTH(cle_password_hash) = 32
  AND cle_password_hash REGEXP '^[0-9A-Fa-f]{32}$';
