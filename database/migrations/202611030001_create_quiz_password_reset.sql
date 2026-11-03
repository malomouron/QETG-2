-- Apply manually after taking a database backup. This migration does not alter legacy tables.
CREATE TABLE IF NOT EXISTS quiz_password_reset (
    id INT NOT NULL AUTO_INCREMENT,
    member_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quiz_password_reset_token_hash (token_hash),
    KEY idx_quiz_password_reset_member (member_id),
    CONSTRAINT fk_quiz_password_reset_member
        FOREIGN KEY (member_id) REFERENCES quiz_membre (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
