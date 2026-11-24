-- One-time MySQL migration. Select the QETG database, back it up and stop all writers first.
-- Run without --force. Neither the guard nor a failing ALTER changes member data.
DROP PROCEDURE IF EXISTS qetg_migration_202611230003;
DELIMITER //
CREATE PROCEDURE qetg_migration_202611230003()
BEGIN
    DECLARE login_charset VARCHAR(64);
    DECLARE login_collation VARCHAR(64);

    IF EXISTS (SELECT 1 FROM quiz_membre WHERE CHAR_LENGTH(login) > 255) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Migration stopped: quiz_membre.login exceeds 255 characters';
    END IF;

    IF EXISTS (SELECT 1 FROM quiz_membre GROUP BY login HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Migration stopped: duplicate quiz_membre.login values';
    END IF;

    IF EXISTS (SELECT 1 FROM quiz_membre GROUP BY email HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Migration stopped: duplicate quiz_membre.email values';
    END IF;

    SELECT CHARACTER_SET_NAME, COLLATION_NAME INTO login_charset, login_collation
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND COLUMN_NAME = 'login';

    SET @qetg_member_identity_ddl = CONCAT(
        'ALTER TABLE quiz_membre MODIFY COLUMN login VARCHAR(255) CHARACTER SET ', login_charset,
        ' COLLATE ', login_collation, ' NOT NULL,',
        ' ADD UNIQUE KEY uq_quiz_membre_login (login), ADD UNIQUE KEY uq_quiz_membre_email (email)'
    );
    PREPARE qetg_member_identity_ddl FROM @qetg_member_identity_ddl;
    EXECUTE qetg_member_identity_ddl;
    DEALLOCATE PREPARE qetg_member_identity_ddl;
END//
DELIMITER ;

CALL qetg_migration_202611230003();
DROP PROCEDURE qetg_migration_202611230003;