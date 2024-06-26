<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;

use \Hcode\Model;

use \Hcode\Mailer;

class User extends Model
{

    const SESSION = "User";
    const SECRET = "HcodePhp8_Secret";

    const ERROR = "UserError";

    const ERROR_REGISTER = "UserErrorRegister";

    const SUCCESS = "UserSuccess";
    public static function getFromSession()
    {
        $user = new User();

        if (isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0) {
            $userId = (int)$_SESSION[User::SESSION]['iduser'];
            $user->get($userId); // Carregar os dados completos do usuário usando o método get()

            return $user;
        }

        return $user;
    }


    public static function checkLogin($inadmin = true)
    {

        if (
            !isset($_SESSION[User::SESSION])
            ||
            !$_SESSION[User::SESSION]
            ||
            !(int)$_SESSION[User::SESSION]['iduser'] > 0
        ) {
            //Não está logado
            return false;
        } else {
            if ($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true) {
                return true;
            } else if ($inadmin === false) {
                return true;
            } else {
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
        if (!User::checkLogin($inadmin)) {
            if ($inadmin) {
                header("Location: /admin/login");
            } else {
                header("Location: /login");
            }
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
            ":desperson" => utf8_encode($this->getdesperson()),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => User::getPasswordHash($this->getdespassword()),
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
            ":despassword" => User::getPasswordHash($this->getdespassword()),
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

    public static function getForgot($email, $inadmin = true)
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
                $cipher = "aes-128-cbc";
                $iv_length = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($iv_length); // Gerando um IV aleatório

                // Criptografando o ID de recuperação
                $code = openssl_encrypt($dataRecovery["idrecovery"], $cipher, self::SECRET, OPENSSL_RAW_DATA, $iv);

                // Concatenando IV e texto cifrado antes de codificar em base64
                $code = base64_encode($iv . $code);

                if ($inadmin === true) {
                    $link = "http://www.igcommerce.com.br/admin/forgot/reset?code=$code";
                } else {
                    $link = "http://www.igcommerce.com.br/forgot/reset?code=$code";
                }


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

        // Garantindo que o tamanho do IV seja obtido corretamente
        $iv_size = openssl_cipher_iv_length("aes-128-cbc");
        $iv = substr($decoded, 0, $iv_size);

        // Obtendo o texto cifrado após o IV
        $ciphertext = substr($decoded, $iv_size);

        // Descriptografando o texto cifrado
        $decrypted = openssl_decrypt($ciphertext, "aes-128-cbc", self::SECRET, OPENSSL_RAW_DATA, $iv);

        $idrecovery = intval($decrypted);

        // Remova var_dump e exit em produção; eles são usados aqui apenas para depuração
        // var_dump($decrypted);
        // exit;

        $sql = new Sql();

        $results = $sql->select(
            "SELECT *  FROM db_ecommerce.tb_userspasswordsrecoveries a INNER JOIN db_ecommerce.tb_users b USING(iduser) INNER JOIN db_ecommerce.tb_persons c USING(idperson) WHERE  a.idrecovery = :idrecovery",
            array(
                ":idrecovery" => $idrecovery
            )
        );

        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha.", 1);
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


    // public static function clearErrorRegister(){
    //     $_SESSION[User::ERROR_REGISTER] = NULL;
    // }


    // public static function getErrorRegister()
    // {
    //     $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';

    //     User::clearErrorRegister();

    //     return $msg;
    // } 

    // public static function setErrorRegister($msg)
    // {
    //     $_SESSION[User::ERROR_REGISTER] = $msg;
    // }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = null;
    }

    public static function getErrorRegister()
    {
        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';

        User::clearErrorRegister();

        return $msg;
    }

    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public function setPassword($password)
    {
        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
            ":password" => $password,
            ":iduser" => $this->getiduser()
        ));
    }

    public static function checkLoginExists($login)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
            ':deslogin' => $login
        ]);

        return (count($results) > 0);
    }

    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost' => 12
        ]);
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR]) ? $_SESSION[User::ERROR] : '';

        User::clearError();

        return $msg;
    }

    public static function setError($msg)
    {
        $_SESSION[User::ERROR] = $msg;
    }

    public static function clearError()
    {
        $_SESSION[User::ERROR] = NULL;
    }

    public static function getSuccess()
    {
        $msg = (isset($_SESSION[User::SUCCESS]) && $_SESSION[User::SUCCESS]) ? $_SESSION[User::SUCCESS] : '';

        User::clearSuccess();

        return $msg;
    }

    public static function setSuccess($msg)
    {
        $_SESSION[User::SUCCESS] = $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[User::SUCCESS] = NULL;
    }

    public function getOrders()
    {
        $sql = new Sql();
        $results = $sql->select(
            'SELECT * 
            FROM tb_orders a 
            INNER JOIN tb_ordersstatus b USING(idstatus)
            INNER JOIN tb_carts c USING(idcart)
            INNER JOIN tb_users d ON d.iduser = a.iduser
            INNER JOIN tb_addresses e USING(idaddress)
            INNER JOIN tb_persons f ON  f.idperson = d.idperson
            WHERE a.iduser = :iduser',
            [
                ':iduser' => $this->getiduser()
            ]
        );
        return $results;
    }

    public static function getPage($page = 1, $itemsPerPage = 10)
    {
        $start = ($page - 1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT SQL_CALC_FOUND_ROWS *
        FROM tb_users a 
        INNER JOIN tb_persons b USING(idperson) 
        ORDER BY b.desperson
        LIMIT $start, $itemsPerPage;
        ");

        $totalResults = $sql->select("SELECT FOUND_ROWS() AS nrtotal;");

        return [
            'data'=>$results,
            'total'=>(int)$totalResults[0]["nrtotal"],
            'pages'=>ceil($totalResults[0]["nrtotal"] / $itemsPerPage)
        ];

    }

    public static function getPageSearch($search,$page = 1, $itemsPerPage = 10)
    {
        $start = ($page - 1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT SQL_CALC_FOUND_ROWS *
        FROM tb_users a 
        INNER JOIN tb_persons b USING(idperson) 
        WHERE b.desperson LIKE :search OR b.desemail = :search OR a.deslogin LIKE :search
        ORDER BY b.desperson
        LIMIT $start, $itemsPerPage;
        ", [
            ':search'=>'%' .$search. '%'
        ]);

        $totalResults = $sql->select("SELECT FOUND_ROWS() AS nrtotal;");

        return [
            'data'=>$results,
            'total'=>(int)$totalResults[0]["nrtotal"],
            'pages'=>ceil($totalResults[0]["nrtotal"] / $itemsPerPage)
        ];

    }

}
