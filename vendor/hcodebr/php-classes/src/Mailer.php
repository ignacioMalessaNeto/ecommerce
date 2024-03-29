<?php

namespace Hcode;

error_reporting(E_ALL);
ini_set('display_errors', 1);

use Rain\Tpl;

class Mailer{

    const USERNAME = "igmalessa@gmail.com";

    const PASSWORD = "whyr mdsw gfej motl";

    const NAME_FROM = "igcommerce";

    private $mail;

    public function __construct($toAdress, $toName, $subject, $tplName, $data = array())
    {

        $config = array(
            "tpl_dir"       => $_SERVER["DOCUMENT_ROOT"] . "/views/email/",
            "cache_dir"     => $_SERVER["DOCUMENT_ROOT"] . "/views-cache/",
            "debug"         =>  false
        );

        Tpl::configure($config);

        $tpl = new Tpl;

        foreach ($data as $key => $value) {
            $tpl->assign($key, $value);
        }

        $html = $tpl->draw($tplName, true);

        $this->mail = new \PHPMailer();

        $this->mail->isSMTP();

        $this->mail->SMTPDebug = 0;

        $this->mail->Host = 'smtp.gmail.com';

        $this->mail->Port = 465;

        $this->mail->SMTPSecure = 'ssl';
        
        $this->mail->SMTPAuth = true;

        $this->mail->Username = Mailer::USERNAME;

        $this->mail->Password = Mailer::PASSWORD;

        $this->mail->setFrom(Mailer::USERNAME, Mailer::NAME_FROM);

        $this->mail->addAddress($toAdress, $toName);

        $this->mail->Subject = $subject;

        $this->mail->msgHTML($html);

        $this->mail->AltBody = 'This is a plain-text message body';
    }


    public function send(){
        return $this->mail->send();
    }
}