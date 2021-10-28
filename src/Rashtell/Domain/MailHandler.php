<?php

namespace Rashtell\Domain;

use Rashtell\Domain\Mailer;

class MailHandler
{
    const TEMPLATE_CONFIRM_EMAIL = 1;
    const TEMPLATE_FORGOT_PASSWORD = 2;
    const TEMPLATE_RECEIVE_TRANSFER = 3;
    const TEMPLATE_TRANSFER_CONFIRMATION = 4;
    const TEMPLATE_TRANSFER_STATUS = 5;

    public $from = "no-reply@liveetapp.com";
    public $fromName = "Royal Executive";
    private $template = "";
    private $to = "";
    private $params = "";

    public function __construct($template, $to, array $params)
    {
        $this->template = $template;
        $this->to = $to;
        $this->params = $params;
    }

    private function createConfirmEmailBody()
    {
        $username = $this->params["username"] ?? "user";
        $otp = $this->params["otp"] ?? "0000";

        $html = "
                    <!DOCTYPE html>
                    <html lang='en'>
                        <head>
                            <meta http-equiv='Content-Type' content='text/html; charset=iso-8859-1'>
                            <link rel='icon' href='assets/logo.png'>
                            <title>Confirm your account</title>

                        </head>
                        <body>
                            <div style='width: 640px; font-family: Arial, Helvetica, sans-serif; font-size: 11px;'>
                                <img src='assets/logo.png'/> 
                                <h1>Hello {$username}</h1>
                                <div align='center'>
                                    <p>
                                        Thank you for registering on Royal Executive Investment Bank & Companies.
                                    </p>
                                    <p>
                                        Please find below your One Time Pin (OTP) to use in completing your registration.
                                    </p>
                                    <p>
                                        <strong style='text-align: center; font-size: 15px;'>{$otp}</strong>
                                    </p>
                                </div>
                            </div>
                        </body>
                    </html>
                            ";


        $text = "Hello {$username},\n\nThank you for registering on Royal Executive Investment Bank & Companies.\nPlease find below your One Time Pin (OTP) to use in completing your registration.\n{$otp}";


        return ["html" => $html, "text" => $text];
    }

    private function createForgotPasswordBody()
    {
        $username = $this->params["username"] ?? "user";
        $otp = $this->params["otp"] ?? "0000";

        $html = "
                    <!DOCTYPE html>
                    <html lang='en'>
                        <head>
                            <meta http-equiv='Content-Type' content='text/html; charset=iso-8859-1'>
                            <link rel='icon' href='assets/logo.png'>
                            <title>Forgot Password</title>

                        </head>
                        <body>
                            <div style='width: 640px; font-family: Arial, Helvetica, sans-serif; font-size: 11px;'>
                                <img src='assets/logo.png'/> 
                                <h1>Hello {$username}</h1>
                                <div align='center'>
                                    <p>
                                        Please find below your One Time Pin (OTP) to use in completing your password reset.
                                    </p>
                                    <p>
                                        <strong style='text-align: center; font-size: 15px;'>{$otp}</strong>
                                    </p>
                                </div>
                            </div>
                        </body>
                    </html>
                            ";


        $text = "Hello {$username},\n\nPlease find below your One Time Pin (OTP) to use in completing your password reset.\n{$otp}";


        return ["html" => $html, "text" => $text];
    }

    private function createReceiveTransferBody()
    {
        $username = $this->params["username"] ?? "user";
        $accountName = $this->params["sender"] ?? "Admin";
        $amount = $this->params["amount"] ?? "$0.00";

        $html = "
                    <!DOCTYPE html>
                    <html lang='en'>
                        <head>
                            <meta http-equiv='Content-Type' content='text/html; charset=iso-8859-1'>
                            <link rel='icon' href='assets/logo.png'>
                            <title>Royal Executive Credit Alert</title>

                        </head>
                        <body>
                            <div style='width: 640px; font-family: Arial, Helvetica, sans-serif; font-size: 11px;'>
                                <img src='assets/logo.png'/> 
                                <h1>Hello {$username}</h1>
                                <div align='center'>
                                    <p>
                                        You have received an internal Transfer to your Royal Executive Account. Find details of the Transfer below:
                                    </p>
                                    <p>
                                        Sender: {$accountName}
                                    </p>
                                    <p>
                                        Amount: {$amount}
                                    </p>
                                    <p>
                                        Please Log In to your Royal Executive Account to see your balance 
                                    </p>
                                </div>
                            </div>
                        </body>
                    </html>
                            ";


        $text = "Hello {$username},\n\nYou have received an internal Transfer to your Royal Executive Account. Find details of the Transfer below:\n\nSender: {$accountName}\nAmount: {$amount}\n\nPlease Log In to your Royal Executive Account to see your balance.";


        return ["html" => $html, "text" => $text];
    }

    private function createTransferConfirmationBody()
    {
        $username = $this->params["username"] ?? "user";
        $accountName = $this->params["receiver"] ?? "John Doe";
        $accountNo = $this->params["accountno"] ?? "00000000";
        $transferType = $this->params["transfertype"] ?? "Local Transfer";
        $amount = $this->params["amount"] ?? "$0.00";

        $html = "
                    <!DOCTYPE html>
                    <html lang='en'>
                        <head>
                            <meta http-equiv='Content-Type' content='text/html; charset=iso-8859-1'>
                            <link rel='icon' href='assets/logo.png'>
                            <title>Royal Executive Transfer Confirmation</title>

                        </head>
                        <body>
                            <div style='width: 640px; font-family: Arial, Helvetica, sans-serif; font-size: 11px;'>
                                <img src='assets/logo.png'/> 
                                <h1>Hello {$username}</h1>
                                <div align='center'>
                                    <p>
                                        This is a confirmation of your just concluded transfer. Find details of the Transfer below:
                                    </p>
                                    <p>
                                        <strong>Receiver:</strong> {$accountName}
                                    </p>
                                    <p>
                                        <strong>Account Number:</strong> {$accountNo}
                                    </p>
                                    <p>
                                        <strong>Amount:</strong> {$amount}
                                    </p>
                                    <p>
                                        <strong>Transfer Type:</strong> {$transferType}
                                    </p>
                                    <p>
                                        <strong>Status:</strong> Pending
                                    </p>
                                    <p>
                                        You can monitor your transfers on the Payment module of your Royal Executive Account.
                                    </p>
                                </div>
                            </div>
                        </body>
                    </html>
                            ";


        $text = "Hello {$username},\n\nThis is a confirmation of your just concluded transfer. Find details of the Transfer below:\n\nReceiver: {$accountName}\nAccount Number: {$accountNo}\nAmount: {$amount}\nTransfer Type: {$transferType}\nStatus: Pending\n\nYou can monitor your transfers on the Payment module of your Royal Executive Account.";


        return ["html" => $html, "text" => $text];
    }

    private function createTransferStatusBody()
    {
        $username = $this->params["username"] ?? "user";
        $accountName = $this->params["receiver"] ?? "John Doe";
        $accountNo = $this->params["accountno"] ?? "00000000";
        $status = $this->params["status"] ?? "Pending";
        $remarks = $this->params["remarks"] ?? "Pending";
        $amount = $this->params["amount"] ?? "$0.00";

        $html = "
                    <!DOCTYPE html>
                    <html lang='en'>
                        <head>
                            <meta http-equiv='Content-Type' content='text/html; charset=iso-8859-1'>
                            <link rel='icon' href='assets/logo.png'>
                            <title>Royal Executive Transfer Update</title>

                        </head>
                        <body>
                            <div style='width: 640px; font-family: Arial, Helvetica, sans-serif; font-size: 11px;'>
                                <img src='assets/logo.png'/> 
                                <h1>Hello {$username}</h1>
                                <div align='center'>
                                    <p>
                                        There has been a change in your transfer status. Find details of the Transfer Update below:
                                    </p>
                                    <p>
                                        <strong>Receiver:</strong> {$accountName}
                                    </p>
                                    <p>
                                        <strong>Account Number:</strong> {$accountNo}
                                    </p>
                                    <p>
                                        <strong>Amount:</strong> {$amount}
                                    </p>
                                    <p>
                                        <strong>New Status:</strong> {$status}
                                    </p>
                                    <p>
                                        <strong>Remarks:</strong> {$remarks}
                                    </p>
                                    <p>
                                        You can monitor your transfers on the Payment module of your Royal Executive Account.
                                    </p>
                                </div>
                            </div>
                        </body>
                    </html>
                            ";


        $text = "Hello {$username},\n\nThere has been a change in your transfer status. Find details of the Transfer Update below:\n\nReceiver: {$accountName}\nAccount Number: {$accountNo}\nAmount: {$amount}\nNew Status: {$status}\nRemarks: {$status}\n\nYou can monitor your transfers on the Payment module of your Royal Executive Account.";


        return ["html" => $html, "text" => $text];
    }

    private function getTemplate()
    {
        $body = "";
        $subject = "";

        switch ($this->template) {
            case self::TEMPLATE_CONFIRM_EMAIL:
                $subject = "Confirm your account";

                $body = $this->createConfirmEmailBody();
                break;

            case self::TEMPLATE_FORGOT_PASSWORD:
                $subject = "Reset your Password";

                $body = $this->createForgotPasswordBody();
                break;
            case self::TEMPLATE_RECEIVE_TRANSFER:
                $subject = "Royal Executive Credit Alert";

                $body = $this->createReceiveTransferBody();
                break;
            case self::TEMPLATE_TRANSFER_CONFIRMATION:
                $subject = "Royal Executive Transfer Confirmation";

                $body = $this->createTransferConfirmationBody();
                break;
            case self::TEMPLATE_TRANSFER_STATUS:
                $subject = "Royal Executive Transfer Update";

                $body = $this->createTransferStatusBody();
                break;
            default:
                break;
        }

        return ["subject" => $subject, "body" => $body];
    }

    private function constructMail()
    {

        ["subject" => $subject, "body" => $body] = $this->getTemplate();

        $mail = new Mailer();
        $mail->from = $this->from;
        $mail->fromName = $this->fromName;
        $mail->to = $this->to;
        $mail->toName = $this->params["username"];
        $mail->subject = $subject;
        $mail->htmlBody = $body["html"];
        $mail->textBody = $body["text"];

        return $mail;
    }

    public function sendMail()
    {
        // ["subject" => $subject, "body" => $body] = $this->getTemplate();

        // // To send HTML mail, the Content-type header must be set
        // $headers[] = 'MIME-Version: 1.0';
        // $headers[] = 'Content-type: text/html; charset=iso-8859-1';

        // // Additional headers
        // $headers[] = 'To: ' . $this->params["username"] . ' <' . $this->to . '>';
        // $headers[] = 'From: ' . $this->fromName . ' <' . $this->from . '>';

        // // $htmlBody = $body["html"];
        // // $textBody = $body["text"];

        // $errLevel = error_reporting(E_ALL ^ E_NOTICE);  // suppress NOTICEs

        // if (@mail($this->to, $subject, $body["html"], implode("\r\n", $headers))) {

        //     error_reporting($errLevel);  // restore old error levels
        //     return ["success" => "Mail sent", "error" => null];
        // }

        // error_reporting($errLevel);  // restore old error levels
        // return ["success" => null, "error" => "Error sending mail"];

        return $this->constructMail()->sendMail();
    }
}
