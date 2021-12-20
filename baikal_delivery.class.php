<?php
//TODO   отладить алгоритм получения расписания
class baikal_delivery extends delivery_company
{

    //Переменные подключения к api
    private $username = '1b3137cbe2bf593c174413dc39255b09';
    private $password = '';
    private $dpd;
    private $messagelog;
    private $detalization;
    private $postid;
    private $weights;
    private $delivery;
    private $priceslimit;
    private $volumes;
    private $name;
    private $APItools;
    private $lastAPI;
    private $logo;

    private $compareBase;


    public function __construct(array $weights, array $volumes, $postid, $priceslimit, $logdetalization, array $logflags, $logo = null, $graph = true)
    {
        $this->postid = $postid;
        $this->detalization = $logdetalization;
        $this->priceslimit = $priceslimit;
        $this->weights = $weights;
        $this->volumes = $volumes;
        $this->logo = $logo;
        $this->dpd = new DPD_service();
        $this->messagelog = new messagelog($this->postid, "", $logflags[0], $logflags[1], $logflags[2], $this->detalization);
        $this->delivery = new Delivery($this->weights, $this->postid, $this->detalization);
        $this->name = $this->delivery->getPostbyId($this->postid);
        $this->compareBase = new compareBase();
        if ($graph) {

            $pending = $this->trylockPost($this->postid);
            if (!$pending) $last_update = date('Y-m-d H:i:s', stat("id_" . $this->postid . ".txt")['ctime']);

            echo "<div style=\"width:auto;border-bottom:solid 1px #ccc;margin:0 150px;margin-bottom:15px;display:grid;grid-template-columns:200px,1fr \"><img src=\"" . $this->logo . "\" style=\"display:block;height:30px;width:auto;margin-bottom:5px\"><div style=\"display:flex;align-items:flex-end;justify-content:flex-end;\">" . ($pending ? "<div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 1);\">Получить все</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 2);\">Получить терминалы</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 3);\">Получить стоимость</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 4);\">Проверить</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 5);\">Сравнить</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 6);\">" . (!$this->delivery->checkstop($postid) ? "Разрешить" : "Стоп") . "</div>" : "Скрипт уже выполняется c $last_update<div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 6);\">Стоп</div>") . "</div></div>";
        }
    }



    ///////////////////////////////////////////////////////////////////////////////
    //////////////////////////////Переопределение наследумеых функций//////////////
    ///////////////////////////////////////////////////////////////////////////////


    function check_Bases()
    {
        $this->delivery->checkPricesintegrity();
        $this->delivery->checkSheduleintegrity();
     
    }

    function compare_Bases()
    {

        $this->compareBase->checkBases($this->postid);
    }




    function save_terminalsdata($city_id = null)
    {
        $fp = $this->lockPost($this->postid);

        //Получаем терминалы
        $affiliates = $this->get_affileates();

     
        //Очищаем флаги обновления данных
        $this->delivery->clear_UpdateFlags();

        //  print_r($affiliates);
        foreach ($affiliates as $affiliate) {


            //Получаем  id  города из таблицы cityfias
            $city_id = $this->delivery->get_TownIdByFias($affiliate->guid);


            //обход результата
            foreach ($affiliate->terminals as $terminal) {
                $terminal_id = $terminal->code;


                if ($terminal_id == "") {
                    $this->messagelog->addLog(1, $this->postid, "Отсутвует код терминала",  "В полученном ответе: " . json_encode($terminal) . " отсутвуют данные о коде терминала ", 0);
                }


                $terminal_name = $terminal->name;
                $terminal_street = $terminal->address;
                $terminal_house = "";
                $terminal_x = explode(',', $terminal->map)[0];
                $terminal_y = explode(',', $terminal->map)[1];
                $schedule = $this->format_TerminalShedule($terminal, 1);


                $post = $this->delivery->save_TerminalData(
                    $city_id,
                    $terminal_id,
                    $terminal_name,
                    $terminal_street,
                    $terminal_house,
                    $terminal_x,
                    $terminal_y,
                    array('flholiday' => $this->checkHolidayWork($schedule), "flkurier" => 0)
                );


                $this->delivery->save_Schedule($post, $schedule);


                $post = $this->delivery->save_TerminalData($city_id, $terminal_id, "Адресная доставка", $terminal_street, $terminal_house, $terminal_x, $terminal_y, array('flholiday' => $this->checkHolidayWork($schedule), "flkurier" => 1));


                $this->delivery->save_Schedule($post, $schedule);
            }
        }

        $this->messagelog->addLog(1, $this->postid, "Информация от таблице Post",  "API " . mb_convert_encoding($this->name['name'], 'utf-8', 'windows-1251') . " содержит следующую информацию: " . $this->delivery->checkFlags(), 0);
        $this->delivery->set_DeleteFlags();

        $this->unlockPost($fp, $this->postid);
    }


    function save_deliveryprices($city_id = null)
    {
        $fp = $this->lockPost($this->postid);
        $ctr = 0;

        $FIASes = $this->delivery->get_FIAStoUpdatePrices($city_id);

        foreach ($this->weights as $weight) {


            foreach ($FIASes as $FIAS) {


                if ($ctr < $this->priceslimit) {
                    $ctr++;
                    $pricedata = $this->get_price($FIAS->FIAS, $weight, $FIAS->flkurier > 0);


                    if (!isset($pricedata->error)) {
                        $price = $pricedata->total->int;
                        $days = isset($pricedata->transit) ? $pricedata->transit->int : 0;
                        $this->delivery->save_pricedata($FIAS->post_id, $FIAS->flkurier, $price, $weight, 0.1, $days, array());
                    } else {
                        $this->delivery->printlog($pricedata->error . " Транспортная компания: Байкал-Сервис. Пункт назначения: " . mb_convert_encoding($FIAS->city,  'utf-8', 'windows-1251') . ". Вес: " . $weight, 1);
                    }
                    $ctr++;
                }
            }
        }

        $this->unlockPost($fp, $this->postid);
    }



    ///////////////////////////----функции работы с API----/////////////////////////////////

    //Базовая функция вывода
    function get_data($command, $usr, $pwd)
    {

        try {
            $url = "https://api.baikalsr.ru/";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . $command);



            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, "$usr:$pwd");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            echo curl_error($ch);
            curl_close($ch);
        } catch (Exception $e) {
            echo 'УПС! Ошибочка: ',  $e->getMessage(), "\n";
        }


        return $data;
    }




    //Получаем пункты выдачи
    function get_affileates()
    {
        $this->lastAPI = "v1/affiliate";
        $res = json_decode($this->get_data("v1/affiliate", $this->username, $this->password));
        $total = 0;
        foreach ($res as $terminals) {
            $total = $total + count($terminals->terminals);
        }
        $this->delivery->printlog("Получено " . $total . " терминалов. В " . count($res) . " городах", array("margin-left" => "50px"));
        return  $res;
    }

    //Получаем стоимость доставки до каждого пункта груза обемом 1 куб и весом 5,10,25,50,100,150,250,300,400,500,600,700,800,900,1000,1500,2000 кг


    function get_price($guid, $weight, $delivery = false)
    {
        $this->lastAPI = "v1/calculator?from[guid]=ccc34487-8fd4-4e71-b032-f4e6c82fb354&to[guid]=" . $guid . "&cargo[weight]=" . $weight . "&cargo[volume]=0.1&" . ($delivery ? "to[delivery]=1" : "");

        return  json_decode($this->get_data("v1/calculator?from[guid]=ccc34487-8fd4-4e71-b032-f4e6c82fb354&to[guid]=" . $guid . "&cargo[weight]=" . $weight . "&cargo[volume]=0.1&" . ($delivery ? "to[delivery]=1" : "") . "&from[delivery]=1", $this->username, $this->password));

        $this->messagelog->addLog(1, $this->postid, "Строка запроса API",  "v1/calculator?from[guid]=ccc34487-8fd4-4e71-b032-f4e6c82fb354&to[guid]=" . $guid . "&cargo[weight]=" . $weight . "&cargo[volume]=0.1&" . ($delivery ? "to[delivery]=1" : "") . "&from[delivery]=1", 0);
    }


    function get_currentprice($url)
    {

        $fias = $this->delivery->get_FiasbyTownId($url['city']);
        $r = $this->get_price($fias, $url['weight']);
        $ret['days'] = $r->transit->int;
        $ret['price'] = $r->total->int;
        $r = $this->get_price($fias, $url['weight'], true);
        $ret['ddays'] = $r->transit->int;
        $ret['dprice'] = $r->total->int;
        $ret['weight'] = $url['weight'];
        $ret['volume'] = $url['volume'];
        $ret['places'] = $url['places'];

        return $ret;
    }


    function format_TerminalShedule($terminal, $display)
    {
        $localschedule = json_decode(json_encode($terminal), true);
        //print_r($localschedule);

        $previousSchedule = '';

        $result = array();



        for ($t = 1; $t < 7; $t++) {

            $index = 'schedule_' . $t;

            if ($previousSchedule != $localschedule[$index]) {
                $result[]['days'] = pow(2, $t - 1);
                $result[count($result) - 1]['time'] = $localschedule[$index];
                $previousSchedule = $localschedule[$index];
            } else {
                $result[count($result) - 1]['days'] = $result[count($result) - 1]['days'] + pow(2, $t - 1);
                $result[count($result) - 1]['time'] =     $localschedule[$index];
            }
        }



        // print_r($result);


        return $result;
    }
    public function checkHolidayWork($schedule)
    {
        $flholiday = 0;
        $DayBytes = array(127, 96, 64, 32);
      
        foreach ($schedule as $scheduleItem) {
        
            if (in_array($scheduleItem['days'], $DayBytes)) {
                $flholiday = 1;
            }
        }
        return $flholiday;
    }
}
