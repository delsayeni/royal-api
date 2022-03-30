<?php

namespace Rashtell\Domain;

/**
 * This example shows sending a message using a local sendmail binary.
 */

date_default_timezone_set("Etc/UTC");
//Import the PHPMailer class into the global namespace
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    public $from = "";
    public $fromName = "";
    public $to = "";
    public $toName = "";
    public $subject = "";
    public $htmlBody = "";
    public $textBody = "";

    public function setUpMail()
    {
        $mail = new PHPMailer();
        //Create a new PHPMailer instance
        // Set PHPMailer to use the sendmail transport
        // $mail->isSendmail();
        $mail->IsSMTP();

        $mail->Host = 'smtp.titan.email';
        $mail->Port = 587;
        $mail->SMTPSecure = 'tls';
        $mail->SMTPAuth = true;
        $mail->isHTML(true);
        $mail->Username = 'emails@royalsexecutive.com';
        $mail->Password = 'ePKPxQinVA';

        $mail->SMTPDebug = 0;

        //Set who the message is to be sent from
        $mail->setFrom($this->from, $this->fromName);
        
        //Set an alternative reply-to address
        $mail->addReplyTo($this->from, $this->fromName);

        //Set who the message is to be sent to
        $mail->addAddress($this->to, $this->toName);

        // $mail->

        //Set the subject line
        $mail->Subject = $this->subject;

        //Set the body
        $mail->Body = $this->htmlBody;

        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        // $mail->msgHTML($this->htmlBody);

        //Replace the plain text body with one created manually
        $mail->AltBody = $this->textBody;

        //Attach an image file
        // $mail->addAttachment("images/phpmailer_mini.png");
        return $mail;
    }

    public function sendMail()
    {
        $mail = $this->setUpMail();

        //send the message, check for errors
        if (!$mail->send()) {
            // if (!mail($this->to, $this->subject, $this->htmlBody)) {

            return ["error" => "Message error: " . $mail->ErrorInfo, "success" => null];
        } else {
            return ["success" => "Message sent!", "error" => null];
        }
    }
}
