<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public $Host;
    public $SMTPAuth;
    public $Username;
    public $Password;
    public $SMTPSecure;
    public $Port;
    public $AltBody;
    public $Subject;
    public $Body;
    public $ErrorInfo = 'Fixture transport failure';
    private $recipient;

    public function isSMTP() {}
    public function setFrom($address, $name = '') {}
    public function addReplyTo($address) {}
    public function addBCC($address) {}
    public function isHTML($enabled) {}
    public function addAddress($address, $name = '')
    {
        $this->recipient = $address;
    }

    public function send()
    {
        if ($this->recipient === 'fail@example.invalid') {
            return false;
        }
        return file_put_contents(getenv('QETG_TEST_OUTBOX'), $this->Body) !== false;
    }
}
