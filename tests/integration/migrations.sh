# Sourced by the CI runner. Its mysql function connects only inside the CI db container.
migration=database/migrations/202611230003_enforce_unique_member_identity.sql
schema=qetg_migration_test

mysql <<'SQL'
CREATE DATABASE qetg_migration_test;
CREATE TABLE qetg_migration_test.quiz_membre LIKE test.quiz_membre;
ALTER TABLE qetg_migration_test.quiz_membre
    DROP KEY uq_quiz_membre_login, DROP KEY uq_quiz_membre_email,
    MODIFY login TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci NOT NULL;
CREATE TABLE qetg_migration_test.quiz_difficulte LIKE test.quiz_difficulte;
INSERT INTO qetg_migration_test.quiz_difficulte (id_difficulte, libelle) VALUES (19, 'Simple'), (42, 'Personnalisée');
INSERT INTO qetg_migration_test.quiz_membre (id, login, password_hash, email, nom, prenom, ip, date) VALUES
    (1, 'Existing', 'hash-one', 'one@example.invalid', 'Test', 'CI', '', ''),
    (2, 'existing', 'hash-two', 'two@example.invalid', 'Test', 'CI', '', '');
SQL

query() { mysql --batch --skip-column-names "$schema" -e "$1"; }
assert_query() {
    local actual
    actual=$(query "$1")
    if [[ "$actual" != "$2" ]]; then
        printf 'Unexpected SQL result: %s\nExpected: %s\nActual: %s\n' "$1" "$2" "$actual" >&2
        return 1
    fi
}

snapshot_sql='SELECT id, HEX(login), HEX(email), password_hash FROM quiz_membre ORDER BY id'
expect_migration_rejected() {
    local before after output
    before=$(query "$snapshot_sql")
    if output=$(mysql "$schema" < "$migration" 2>&1) || [[ "$output" != *"$1"* ]]; then
        printf 'Expected migration rejection (%s); got: %s\n' "$1" "$output" >&2
        return 1
    fi
    after=$(query "$snapshot_sql")
    [[ "$before" == "$after" ]] || { echo 'A rejected migration changed member data.' >&2; return 1; }
    assert_query "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND COLUMN_NAME = 'login'" text
    assert_query "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND INDEX_NAME LIKE 'uq_quiz_membre_%'" 0
}

expect_migration_rejected 'duplicate quiz_membre.login'
query "UPDATE quiz_membre SET login = 'Different', email = 'ONE@example.invalid' WHERE id = 2"
expect_migration_rejected 'duplicate quiz_membre.email'
query "UPDATE quiz_membre SET login = CONCAT(REPEAT('é', 255), ' '), email = 'two@example.invalid' WHERE id = 2"
expect_migration_rejected 'exceeds 255 characters'

query "UPDATE quiz_membre SET login = REPEAT('é', 255) WHERE id = 2"
before=$(query "$snapshot_sql")
mysql "$schema" < "$migration"
assert_query "$snapshot_sql" "$before"
assert_query "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND COLUMN_NAME = 'login'" 255
assert_query "SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND COLUMN_NAME = 'login'" utf8mb4_0900_as_ci
assert_query "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_membre' AND NON_UNIQUE = 0 AND SUB_PART IS NULL AND INDEX_NAME IN ('uq_quiz_membre_email','uq_quiz_membre_login') ORDER BY COLUMN_NAME" $'email\nlogin'

seed=database/migrations/202611230001_seed_quiz_difficulties.sql
mysql "$schema" < "$seed"
mysql "$schema" < "$seed"
assert_query "SELECT COUNT(*) FROM quiz_difficulte" 6
assert_query "SELECT libelle FROM quiz_difficulte ORDER BY id_difficulte" $'Simple\nPersonnalisée\nTrès simple\nIntermédiaire\nDifficile\nTrès difficile'
assert_query "SELECT id_difficulte FROM quiz_difficulte WHERE libelle = 'Simple'" 19
printf 'Member identity and difficulty migration checks passed.\n'

traffic_migration=database/migrations/202611230004_create_daily_traffic.sql
traffic_schema=qetg_traffic_migration_test
mysql -e "CREATE DATABASE $traffic_schema"
mysql "$traffic_schema" < "$traffic_migration"
mysql "$traffic_schema" < "$traffic_migration"
traffic_query() { mysql --batch --skip-column-names "$traffic_schema" -e "$1"; }
traffic_columns=$(traffic_query "SELECT CONCAT(COLUMN_NAME, ':', DATA_TYPE, ':', COLUMN_TYPE) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_daily_traffic' ORDER BY ORDINAL_POSITION")
expected_traffic_columns=$'daily:date:date\npage_views:bigint:bigint unsigned\nvisitor_sessions:bigint:bigint unsigned'
if [[ "$traffic_columns" != "$expected_traffic_columns" ]]; then
    printf 'Unexpected traffic table columns.\nExpected:\n%s\nActual:\n%s\n' "$expected_traffic_columns" "$traffic_columns" >&2
    exit 1
fi
traffic_primary_key=$(traffic_query "SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_daily_traffic' AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX")
if [[ "$traffic_primary_key" != daily ]]; then
    printf 'Unexpected traffic primary key: %s\n' "$traffic_primary_key" >&2
    exit 1
fi
printf 'Daily traffic migration checks passed.\n'