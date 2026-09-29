-- Sync imports every discovered resource automatically. Resources an admin
-- removed from the panel are marked so the next sync does not bring them back.
ALTER TABLE provider_resources ADD COLUMN auto_import TINYINT(1) NOT NULL DEFAULT 1 AFTER local_id;
