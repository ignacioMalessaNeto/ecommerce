<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Model\Cart;

class Order extends Model
{
    const SUCCESS = "Order-Success";
    const ERROR = "Order-Error";


    public function save()
    {

        $sql = new Sql();

        $results = $sql->select("CALL sp_orders_save(:idorder, :idcart, :iduser, :idstatus, :idaddress, :vltotal)", [
            ':idorder' => $this->getidorder(),
            ':idcart' => $this->getidcart(),
            ':iduser' => $this->getiduser(),
            ':idstatus' => $this->getidstatus(),
            ':idaddress' => $this->getidaddress(),
            ':vltotal' => $this->getvltotal()
        ]);

        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function get($idorder)
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
            WHERE a.idorder = :idorder',
            [
                ':idorder' => $idorder
            ]
        );
        if (count($results) > 0) {
            $this->setData($results[0]);
        }
    }

    public function delete()
    {
        $sql = new Sql();

        $sql->query("DELETE FROM tb_orders WHERE idorder = :idorder", [
            ':idorder'=>$this->getidorder()
        ]);

    }
    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select(
        'SELECT * 
        FROM tb_orders a 
        INNER JOIN tb_ordersstatus b USING(idstatus)
        INNER JOIN tb_carts c USING(idcart)
        INNER JOIN tb_users d ON d.iduser = a.iduser
        INNER JOIN tb_addresses e USING(idaddress)
        INNER JOIN tb_persons f ON  f.idperson = d.idperson
        ORDER BY a.dtregister DESC'
        );
    }

    public function getCart():Cart{
        $cart = new Cart();

        $cart->get((int)$this->getidcart());

        return $cart;
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[Order::ERROR]) && $_SESSION[Order::ERROR]) ? $_SESSION[Order::ERROR] : '';

        User::clearError();

        return $msg;
    }

    public static function setError($msg)
    {
        $_SESSION[Order::ERROR] = $msg;
    }

    public static function clearError()
    {
        $_SESSION[Order::ERROR] = NULL;
    }

    public static function getSuccess()
    {
        $msg = (isset($_SESSION[Order::SUCCESS]) && $_SESSION[Order::SUCCESS]) ? $_SESSION[Order::SUCCESS] : '';

        Order::clearSuccess();

        return $msg;
    }

    public static function setSuccess($msg)
    {
        $_SESSION[Order::SUCCESS] = $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[Order::SUCCESS] = NULL;
    }

    public static function getPage($page = 1, $itemsPerPage = 10)
    {
        $start = ($page - 1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT SQL_CALC_FOUND_ROWS *
        FROM tb_orders a 
        INNER JOIN tb_ordersstatus b USING(idstatus)
        INNER JOIN tb_carts c USING(idcart)
        INNER JOIN tb_users d ON d.iduser = a.iduser
        INNER JOIN tb_addresses e USING(idaddress)
        INNER JOIN tb_persons f ON  f.idperson = d.idperson
        ORDER BY a.dtregister DESC
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
        FROM tb_orders a 
        INNER JOIN tb_ordersstatus b USING(idstatus)
        INNER JOIN tb_carts c USING(idcart)
        INNER JOIN tb_users d ON d.iduser = a.iduser
        INNER JOIN tb_addresses e USING(idaddress)
        INNER JOIN tb_persons f ON  f.idperson = d.idperson
        WHERE a.idorder = :id OR f.desperson LIKE :search
        ORDER BY a.dtregister DESC
        LIMIT $start, $itemsPerPage;
        ", [
            ':search'=>'%' .$search. '%',
            ':id'=>$search
        ]);

        $totalResults = $sql->select("SELECT FOUND_ROWS() AS nrtotal;");

        return [
            'data'=>$results,
            'total'=>(int)$totalResults[0]["nrtotal"],
            'pages'=>ceil($totalResults[0]["nrtotal"] / $itemsPerPage)
        ];

    }

}
