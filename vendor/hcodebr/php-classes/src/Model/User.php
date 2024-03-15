<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;

use \Hcode\Model;

use \Hcode\Mailer;

class User extends Model
{

    const SESSION = "User";

    const SECRET = "HcodePhp8_Secret";

    public static function getFromSession()
    {
        $user = new User();


        if(isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0){
            $user->setData($_SESSION[User::SESSION]);

            return $user;
        }

        return $user;
        
    }

    public static function checkLogin($inadmin = true){
    
        $user = new User();
    
        if(!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]['iduser'] > 0 || (bool)$_SESSION[User::SESSION]['inadmin'] !== $inadmin){
            //Não está logado
            return false;
        } else{
            if ($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true){
                return true;
            } else if ($inadmin === false){
                return true;
            }else {
                return false;
            }
        }
    }

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
            ":LOGIN" => $login
        ));

        if (count($results) === 0) {
            throw new \Exception("Usuário inexistente ou senha inválida.", 1);
        }

        $data = $results[0];

        if (password_verify($password, $data["despassword"]) === true) {
            $user = new User();

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        } else {
            throw new \Exception("Usuário inexistente ou senha inválida.", 1);
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if (User::checkLogin($inadmin)) {
            header("Location: /admin/login");
            exit;
        }
    }

    public static function logout()
    {
        $_SESSION[User::SESSION] = NULL;
    }

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");
    }


    public function save()
    {
        $sql = new Sql();

        $results = $sql->select("CALL sp_users_save( :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":desperson" => $this->getdesperson(),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => $this->getdespassword(),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ));

        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(
            ":iduser" => $iduser
        ));

        $this->setData($results[0]);
    }

    public function update()
    {
        $sql = new Sql();

        $results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":iduser" => $this->getiduser(),
            ":desperson" => $this->getdesperson(),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => $this->getdespassword(),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ));

        $this->setData($results[0]);
    }

    public function delete()
    {

        $sql = new Sql();

        $sql->query("CALL sp_users_delete(:iduser)", array(
            ":iduser" => $this->getiduser()
        ));
    }

    public static function getForgot($email)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email", array(
            ":email" => $email
        ));


        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha.", 1);
        } else {

            $data = $results[0];

            $results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser" => $data["iduser"],
                ":desip" => $_SERVER["REMOTE_ADDR"]
            ));

            if (count($results2) === 0) {
                throw new \Exception("Não foi possível recuperar a senha.", 1);
            } else {
                $dataRecovery = $results2[0];

                // Gerando um código seguro usando openssl_encrypt
                $cipher = "aes-128-cbc"; // Algoritmo de criptografia
                $iv_length = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($iv_length); // Gerando um IV aleatório

                // Criptografando o ID de recuperação usando a chave secreta e o IV
                $code = openssl_encrypt($dataRecovery["idrecovery"], $cipher, User::SECRET, OPENSSL_RAW_DATA, $iv);

                // Codificando o resultado em base64 para ser usado em URLs
                $code = base64_encode($iv . $code);

                $link = "http://www.igcommerce.com.br/admin/forgot/reset?code=$code";

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir senha igcommerce", "forgot", array(
                    "name" => $data["desperson"],
                    "link" => $link
                ));

                $mailer->send();

                return $data;
            }
        }
    }

    public static function validForgotDecrypt($code)
    {

        $decoded = base64_decode($code);

        // Obter o IV (Vetor de Inicialização) do início do código decodificado
        $iv_size = openssl_cipher_iv_length("aes-128-cbc");

        $iv = substr($decoded, 0, $iv_size);

        // Obter o texto cifrado após o IV
        $ciphertext = substr($decoded, $iv_size);

        // Descriptografar o texto cifrado usando openssl_decrypt
        $decrypted = openssl_decrypt($ciphertext, "aes-128-cbc", User::SECRET, OPENSSL_RAW_DATA, $iv);

        $idrecovery = intval($decrypted);

        $sql = new Sql();

        $results = $sql->select(
            "SELECT *  FROM db_ecommerce.tb_userspasswordsrecoveries a INNER JOIN db_ecommerce.tb_users b USING(iduser) INNER JOIN db_ecommerce.tb_persons c USING(idperson) WHERE  a.idrecovery = :idrecovery",
            array(
                ":idrecovery" => $idrecovery
            )
        );

        if (count($results) === 0) {
            // throw new \Exception("Não foi possível recuperar a senha.", 1);
        } else {
            return $results[0];
        }
    }

    public static function setForgotUsed($idrecovery)
    {
        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
            ":idrecovery" => $idrecovery
        ));
    }

    public function setPassword($password)
    {
        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
            ":password" => $password,
            ":iduser" => $this->getiduser()
        ));
    }
}
