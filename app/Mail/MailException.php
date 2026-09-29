<?php
declare(strict_types=1);

namespace App\Mail;

/** A mail server problem whose message is safe to show to the user. */
final class MailException extends \RuntimeException
{
}
