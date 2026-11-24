-- Add the missing quiz difficulty levels without deleting or renumbering existing rows.
-- Safe to apply more than once; existing labels and foreign keys are preserved.
INSERT INTO quiz_difficulte (libelle)
SELECT 'Très simple'
WHERE NOT EXISTS (
    SELECT 1 FROM quiz_difficulte WHERE libelle = 'Très simple'
);

INSERT INTO quiz_difficulte (libelle)
SELECT 'Simple'
WHERE NOT EXISTS (
    SELECT 1 FROM quiz_difficulte WHERE libelle = 'Simple'
);

INSERT INTO quiz_difficulte (libelle)
SELECT 'Intermédiaire'
WHERE NOT EXISTS (
    SELECT 1 FROM quiz_difficulte WHERE libelle = 'Intermédiaire'
);

INSERT INTO quiz_difficulte (libelle)
SELECT 'Difficile'
WHERE NOT EXISTS (
    SELECT 1 FROM quiz_difficulte WHERE libelle = 'Difficile'
);

INSERT INTO quiz_difficulte (libelle)
SELECT 'Très difficile'
WHERE NOT EXISTS (
    SELECT 1 FROM quiz_difficulte WHERE libelle = 'Très difficile'
);
