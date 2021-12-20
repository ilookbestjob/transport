<?php

interface delivery_company_template
{

    public function save_terminalsdata($city_id = null);
    public function save_deliveryprices($city_id = null);
    public function save_terminalsdata_try($city_id = null);
    public function save_deliveryprices_try($city_id = null);




    public function __construct(array $weights, array $volumes, $postid, $priceslimit, $logdetalization, array $logflags,  $logo = null, $graph = true);
}

class delivery_company implements delivery_company_template
{
    private $dpd;
    private $messagelog;
    private $detalization;
    private $postid;
    private $weights;
    private $delivery;
    private $priceslimit;
    private $volumes;
    private $name;

    public function __construct(array $weights, array $volumes, $postid, $priceslimit,  $logdetalization, array $logflags, $logo = null, $graph = true)
    {

        $this->postid = $postid;
        $this->detalization = $logdetalization;
        $this->priceslimit = $priceslimit;
        $this->weights = $weights;
        $this->volumes = $volumes;

        $this->dpd = new DPD_service();
        $this->messagelog = new messagelog($this->postid, "", $logflags[0], $logflags[1], $logflags[2], $this->detalization);
        $this->delivery = new Delivery($this->weights, $this->postid, $this->detalization);
    }


    public function save_terminalsdata($city_id = null)
    {
        // $this->delivery->printlog("Функионал получения терминалов не рализован");
        echo "Функионал получения стоимости доставки не рализован";
    }
    public function save_deliveryprices($city_id = null)
    {
        // $this->delivery->printlog("Функионал получения стоимости доставки не рализован");
        echo "Функионал получения стоимости доставки не рализован";
    }







    public function save_terminalsdata_try($city_id = null)
    {

        try {

            echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Загрузка данных терминалов</div>";
             $this->save_terminalsdata($city_id);
            echo "</div>";
        } catch (Exception $e) {
            $this->delivery->printlog("Ошибка получения терминалов: $e");
            echo "err";
            echo "</div>";
        }
    }
    public function save_deliveryprices_try($city_id = null)
    {
        try {

            echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Загрузка стоимости доставки до пункта выдачи </div>";
            $this->save_deliveryprices($city_id);
            echo "</div>";
        } catch (Exception $e) {
            $this->delivery->printlog("Ошибка получения стоимости достваки: $e");
            echo "</div>";
        }
    }

    public function save_all()
    {
        $this->save_terminalsdata();
        $this->save_deliveryprices();
    }

    public function save_all_try()
    {
        $this->save_terminalsdata_try();
        $this->save_deliveryprices_try();
    }



    public function log()
    {
        $this->messagelog->totalLog();
    }


    public function lockPost($postid)
    {
        $fp = fopen("id_" . $postid . ".txt", 'w+');

        if (!$fp) {
            echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Невозможно создать файл блокировки!!!</div>";
        } else {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Загрузка возможна</div>";
                return $fp;
            } else {
                echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Загрузка невозможна, работает другой скрипт</div>";
                exit;
            }
        }
    }

    public function trylockPost($postid)
    {
        $fp = fopen("id_" . $postid . ".txt", 'w+');

        if (!$fp) {
            echo "<div style=\"width:auto;margin:0 150px;margin-bottom:15px;margin-left:20px;font-family:tahoma;color:#aaa;font-size:18px;\">Невозможно создать файл блокировки!!!</div>";
        } else {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                flock($fp, LOCK_UN);
                fclose($fp);
                unlink("id_" . $postid . ".txt");
                return true;
            } else {
                fclose($fp);
                return false;
            }
        }
    }

    public function unlockPost($fp, $postid)
    {
        $server = 'localhost';
        $base = 'nordcom';
        $user = 'root';
        $bdpassword = 'pr04ptz3';
        $connection = mysqli_connect($server, $user, $bdpassword, $base);
        $sql = "update `const` set `data`='" . date("Y-m-d H:i:s") . "' where `row_id` in(select const_id from posttype where row_id=" . $postid . ")";
        mysqli_query($connection, $sql);
        mysqli_close($connection);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function checkstop($posttype_id)
    {
        if (file_exists('stopall.txt')) {
            $fp = fopen('stopall.txt', 'rt');
            $stopstring = fgets($fp);


            $stops = explode(',', $stopstring);
            $stopflag = false;

            
            foreach ($stops as $stop) {
                if ($stop == $posttype_id) {
                    $stopflag = true;
                }
            }
            fclose($fp);
            return $stopflag;
        }
    }


    public function toggleStop($posttype_id)
    {

        echo $this->checkstop($posttype_id);
        if ($this->checkstop($posttype_id)) {

            if (file_exists('stopall.txt')) {
                $fp = fopen('stopall.txt', 'w+');
                $stopstring = fgets($fp);


                $stops = explode(',', $stopstring);
                $newstops = [];
                foreach ($stops as $stop) {
                    if ($stop != $posttype_id) {
                        $newstops[] = $stop;
                    }
                }

                fwrite($fp, implode(",", $newstops));

                fclose($fp);
            }
        } else {

            if (file_exists('stopall.txt')) {
                $fp = fopen('stopall.txt', 'w+');
                $stopstring = fgets($fp);
                $stopstring.= ",$posttype_id";

                fwrite($fp, $stopstring);

                fclose($fp);
            }
        }
    }
}
