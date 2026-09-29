-- Email account limit per email domain, set by a Super Admin (NULL = use the customer's or plan's limit).
ALTER TABLE email_domains ADD COLUMN max_mailboxes INT UNSIGNED NULL AFTER status;
