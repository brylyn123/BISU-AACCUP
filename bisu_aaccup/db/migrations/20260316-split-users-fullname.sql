-- Migration: split users.full_name into firstname/middlename/lastname
-- Run this once after deploying the updated authentication stack.

START TRANSACTION;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS firstname VARCHAR(50) DEFAULT '' AFTER user_id,
  ADD COLUMN IF NOT EXISTS middlename VARCHAR(50) DEFAULT '' AFTER firstname,
  ADD COLUMN IF NOT EXISTS lastname VARCHAR(50) DEFAULT '' AFTER middlename;

UPDATE users
SET firstname = COALESCE(NULLIF(TRIM(full_name), ''), firstname),
    middlename = '',
    lastname = '';

ALTER TABLE users
  MODIFY COLUMN firstname VARCHAR(50) NOT NULL,
  MODIFY COLUMN middlename VARCHAR(50) NOT NULL DEFAULT '',
  MODIFY COLUMN lastname VARCHAR(50) NOT NULL DEFAULT '';

ALTER TABLE users
  DROP COLUMN IF EXISTS full_name;

COMMIT;
