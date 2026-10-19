-- Webmail: an HTML signature (logo, links, colours) besides the plain-text one.
ALTER TABLE webmail_prefs
    ADD COLUMN signature_mode VARCHAR(8) NOT NULL DEFAULT 'text' AFTER signature,
    ADD COLUMN signature_html MEDIUMTEXT NULL AFTER signature_mode;
