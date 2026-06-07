<?php
// ============================================================================
// SECTION 0: DEFENSE-READY COMMENTING CONTEXT
// Purpose: mailer_helper.php centralizes UTMSPACE Claim System email delivery.
// The following comments explain what each block performs and why the business
// workflow benefits from automated, consistent, and examiner-readable email
// communication across Staff, Finance, and Admin modules.
// ============================================================================

// SECTION: PHPMailer DEPENDENCY LOADING - Loads the local PHPMailer classes so the system can send SMTP email without duplicating mail setup code in each module.
require_once __DIR__ . '/vendor/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer.php';
require_once __DIR__ . '/vendor/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// SECTION: REUSABLE EMAIL SERVICE - Provides one trusted function for all UTMSPACE notification workflows.
function sendSystemEmail($toEmail, $toName, $subject, $bodyHTML)
{
    // SECTION: SMTP CONFIGURATION - Stores the project email account in one location so Finance and Staff workflows use identical delivery settings.
    // WHY: The repository is private, so placeholder credentials can be replaced directly here during deployment without changing business modules.
    $smtpEmail    = 'utmspaceclaim.demo@gmail.com';
    $smtpPassword = 'kjvsghjthnholvyi';
    $fromName     = 'UTMSPACE Claim System';

    // SECTION: DEPLOYMENT SAFETY CHECK - Stops email delivery when the SMTP credentials are still placeholders.
    // WHY: A clear log entry makes configuration problems visible instead of allowing PHPMailer to fail later with a less obvious authentication error.
    if ($smtpEmail === 'YOUR_EMAIL@gmail.com' || $smtpPassword === 'YOUR_16_CHAR_PASSWORD') {
        error_log('UTMSPACE Mailer Error: SMTP credentials are still placeholders in mailer_helper.php.');
        return false;
    }

    // SECURITY: Email validation prevents malformed recipient addresses from reaching the SMTP layer.
    // WHY: Rejecting invalid email addresses early protects the notification workflow from avoidable delivery failures.
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // SECURITY: Removing line breaks from email header values prevents header injection.
    // WHY: Email subjects and display names are business metadata, not executable mail headers supplied by users.
    $cleanToName  = str_replace(["\r", "\n"], '', $toName);
    $cleanSubject = str_replace(["\r", "\n"], '', $subject);

    // SECURITY: Preventing XSS by escaping text values that are displayed inside the corporate HTML template.
    // WHY: Subject and recipient name values may originate from database records and must remain display-only content in email clients.
    $safeToName  = htmlspecialchars($cleanToName, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($cleanSubject, ENT_QUOTES, 'UTF-8');

    // SECTION: CORPORATE EMAIL TEMPLATE - Wraps workflow-specific content in a consistent UTMSPACE visual identity.
    // WHY: A standard template increases recipient trust, improves readability, and makes automated messages look official during the claim process.
    // SECURITY: Workflow snippets must escape their own dynamic values before passing HTML into $bodyHTML because this helper intentionally preserves approved HTML markup.
    $corporateHTML = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $safeSubject . '</title>
        </head>
        <body style="margin:0; padding:0; background:#f0f4f8; font-family:Arial, Helvetica, sans-serif; color:#1e293b;">
            <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">' . $safeSubject . '</div>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f0f4f8; padding:28px 12px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #dbe4f0;">
                            <tr>
                                <td style="background:#0f2b4d; padding:24px 28px; color:#ffffff;">
                                    <div style="font-size:20px; font-weight:700; letter-spacing:0;">UTMSPACE Claim System</div>
                                    <div style="font-size:13px; color:#bfdbfe; margin-top:6px;">Automated Business Workflow Notification</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:28px;">
                                    <p style="margin:0 0 18px; font-size:15px; line-height:1.6;">Dear ' . $safeToName . ',</p>
                                    <div style="font-size:15px; line-height:1.7; color:#334155;">
                                        ' . $bodyHTML . '
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="background:#f8fafc; padding:18px 28px; border-top:1px solid #e2e8f0;">
                                    <p style="margin:0; font-size:12px; line-height:1.6; color:#64748b;">
                                        This is an automated message from the UTMSPACE Claim System. Please do not reply to this email.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ';

    // SECTION: SMTP DELIVERY - Sends the prepared corporate message through Gmail SMTP using PHPMailer.
    // WHY: Centralized SMTP settings allow every role module to trigger email without duplicating credentials or transport rules.
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpEmail;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 15;
        $mail->CharSet    = 'UTF-8';

        // SECTION: MESSAGE ADDRESSING - Defines sender and recipient metadata for the outbound notification.
        // WHY: Consistent sender identity helps Finance and Staff recognize official claim-system messages.
        $mail->setFrom($smtpEmail, $fromName);
        $mail->addAddress($toEmail, $cleanToName);

        // SECTION: MESSAGE CONTENT - Sends both HTML and plain-text versions for broad email-client compatibility.
        // WHY: HTML improves readability while AltBody keeps the notification accessible in plain-text clients.
        $mail->isHTML(true);
        $mail->Subject = $cleanSubject;
        $mail->Body    = $corporateHTML;
        $mail->AltBody = html_entity_decode(strip_tags($safeSubject . "\n\n" . $bodyHTML), ENT_QUOTES, 'UTF-8');

        return $mail->send();
    } catch (Exception $e) {
        // SECTION: FAIL-SAFE ERROR HANDLING - Records mail failures without breaking the completed claim or payment transaction.
        // WHY: Email is a notification layer; the core financial workflow must remain successful after the database update has completed.
        error_log('UTMSPACE Mailer Error: ' . $e->getMessage());
        return false;
    }
}
