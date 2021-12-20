<?php

include 'dlclient.php'; //класс от разаработчиков DPD


class dl_delivery extends delivery_company
{
    private $delline;
    private $messagelog;
    private $detalization;
    private $postid;
    private $weights;
    private $delivery;
    private $priceslimit;
    private $volumes;
    private $name;
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
        $this->delline = new DLClient('4518ADB2-731A-11E5-A6BB-00505683A6D3', 'https://api.dellin.ru', 'json', 'array', 'array');
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
        // $this->delivery->checkPricesDate(5);
        // $this->delivery->checkScheduleDate(15);
        $this->delivery->checkPricesintegrity();
        $this->delivery->checkSheduleintegrity();
        //  $this->compareBase->checkBases($this->postid);
    }
    function compare_Bases()
    {

        $this->compareBase->checkBases($this->postid);
    }



    function save_terminalsdata($city_id = null)
    {

        //Получаем терминалы
        $fp = $this->lockPost($this->postid);

        $affiliates = $this->delline->getTerminalsListJSON();
        // print_r($affiliates['city']);


        // $this->delivery->printlog("Получено ".count($affiliates->terminal)." терминалов.",array("margin-left"=>"50px"));

        //  APItools::displayResponseStructure("getTerminalList()", $affiliates);
        //Очищаем флаги обновления данных
        $this->delivery->clear_UpdateFlags();


        foreach ($affiliates['city'] as $city) {

            foreach ($city['terminals']['terminal'] as $terminal) {



                $city_id = $this->delivery->get_TownIdByField("kladr", substr($city['code'], 0, 13));




                //обход результата

                $terminal_id = mb_convert_encoding($city['code'], 'windows-1251', 'utf-8') . "|" . mb_convert_encoding($terminal['id'], 'windows-1251', 'utf-8');
                $terminal['id'];
                $terminal_name = $terminal['name'];
                $terminal_street = $terminal['fullAddress'];
                $terminal_house = '';
                $terminal_x = $terminal['latitude'];
                $terminal_y = $terminal['longitude'];

                $post = $this->delivery->save_TerminalData($city_id, $terminal_id, $terminal_name, $terminal_street, $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 0));


                $this->delivery->save_Schedule($post, $this->format_TerminalShedule($terminal['worktables']['worktable']));
            }
            $post = $this->delivery->save_TerminalData($city_id, "", "Адресная доставка в г. ". $city['name'], "Адресная доставка в г. ". $city['name'], $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 1));
        }
        $this->delivery->set_DeleteFlags();
        $this->unlockPost($fp, $this->postid);
    }

    function save_deliveryprices($city_id = null)
    {



        $fp = $this->lockPost($this->postid);


        $ctr = 0;
        $delay = 0;
        $delaystep = 5;
        $delaylimit = 300;
        $attempt = 0;


        $Cities = $this->delivery->get_CitytoUpdatePrices($city_id);

        $this->messagelog->addLog(10, $this->postid, "Информация БД",  "Получены следующие города " . mb_convert_encoding(json_encode($Cities), 'utf-8', 'windows-1251'), 0);

        foreach ($this->weights as $key => $weight) {

            $ctr2 = 0;

            while ($ctr2 < count($Cities)) {
                //foreach($Cities as $City) {
                //echo $ctr++;
                $attempt++;

                $this->delivery->printlog("Попытка $attempt получения стоимости достаки груза весом $weight кг. и объемом " . $this->volumes[$key] . "  для терминала " . $Cities[$ctr2]->post_id . " города " . mb_convert_encoding($Cities[$ctr2]->city, 'utf-8', 'windows-1251'));


                $this->messagelog->addLog(10, $this->postid, "Попытка получения данных",  "Попытка $attempt получения стоимости достаки груза весом $weight кг. и объемом " . $this->volumes[$key] . "  для терминала " . $Cities[$ctr2]->post_id . " города " . mb_convert_encoding($Cities[$ctr2]->city, 'utf-8', 'windows-1251'), 0);



                //echo "</br>";

                if ($ctr < $this->priceslimit) {

                    $request = array(
                        "derivalPoint" =>     "1000000100000000000000000",
                        "derivalDoor" =>     false,
                        "arrivalPoint" =>     $Cities[$ctr2]->kladr . "000000000000",
                        "arrivalDoor" =>     $Cities[$ctr2]->flkurier > 0,
                        "sizedVolume" =>      $this->volumes[$key],
                        "sizedWeight" =>      $weight
                    );


                    $ret = $this->delline->calculator($request);
                    if (!$ret) {
                        $delay = $delay + $delaystep;
                        if ($delay > $delaylimit) {
                            $this->delivery->printlog("Возвращен пустой ответ. Превышен лимит ожидания($delaylimit). Получение следующей записи </br></br>");
                            $this->messagelog->addLog(0, $this->postid, "Ошибка получения данных", "Возвращен пустой ответ. Превышен лимит ожидания($delaylimit). Получение следующей записи", 0);

                            $ctr2++;
                            $attempt = 0;
                            $delay = 0;
                        } else {
                            $this->messagelog->addLog(0, $this->postid, "Ошибка получения данных", "Возвращен пустой ответ. Задержка $delay сек.", 0);

                            $this->delivery->printlog("Возвращен пустой ответ. Задержка $delay сек. </br></br>");
                            sleep($delay);
                        }
                    } else {

                        $this->messagelog->addLog(0, $this->postid, "Получение данных", "Получен ответ для терминала " . $Cities[$ctr2]->post_id . " города " . mb_convert_encoding($Cities[$ctr2]->city, 'utf-8', 'windows-1251') . ":цена " . $ret['price'] . " вес " . $weight . " объем " . $this->volumes[$key] . " срок " . $ret['time']['value'], 0);

                        $res = $this->delivery->save_pricedata($Cities[$ctr2]->post_id, 0, $ret['price'], $weight,  $this->volumes[$key], $ret['time']['value']);

                        echo "</br></br>ntvn: $res";

                        $fileLink = fopen("log.txt", 'a');
                        fwrite($fileLink, $res . PHP_EOL);
                        fclose($fileLink);

                        $this->messagelog->addLog(0, $this->postid, "Ошибка записи БД", $res, 0);
                        $delay = 0;
                        $attempt = 0;
                        $ctr2++;
                        //  $this->delivery->printlog("Получены данные:  ".json_encode($ret)." </br></br>");
                    }
                }
            }
        }

        $this->delivery->set_DeleteFlags();
        $this->unlockPost($fp, $this->postid);
    }






    function get_currentprice($url)
    {




        $City = $this->delivery->get_TownArrIdByField("row_id", $url['city']);


        $request = array(
            "derivalPoint" =>     "1000000100000000000000000",
            "derivalDoor" =>      false,
            "arrivalPoint" =>     $City['kladr'] . "000000000000",
            "arrivalDoor" =>      false,
            "sizedVolume" =>      0.1,
            "sizedWeight" =>      $url['weight']
        );


        $price = $this->delline->calculator($request);


        $ret['price'] = $price['price'];
        $ret['days'] = $price['time']['value'];

        $request = array(
            "derivalPoint" =>     "1000000100000000000000000",
            "derivalDoor" =>      false,
            "arrivalPoint" =>     $City['kladr'] . "000000000000",
            "arrivalDoor" =>      true,
            "sizedVolume" =>      0.1,
            "sizedWeight" =>      $url['weight']
        );



        $ret['dprice'] = $price['price'];
        $ret['ddays'] = $price['time']['value'];

        $ret['weight'] = $url['weight'];
        $ret['volume'] =  $url['volume'];;
        $ret['places'] = $url['places'];;

        return $ret;
    }


    ///////////////////////////////////////////////////////////////////////////////
    //////////////////////////////Вспомогательные функции класса dpd_delivery//////
    ///////////////////////////////////////////////////////////////////////////////




    function format_TerminalShedule($schedule)
    {

        $result = [];

        $previousSchedule = "";
        foreach ($schedule as $key => $scheduletype) {
            $t = 0;
            if ($scheduletype['department'] == "Приём и выдача груза") {

                foreach ($scheduletype as $key => $scheduleitem) {
                    if (in_array($key, array("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"))) {
                        $t++;
                        if ($previousSchedule != $scheduleitem) {
                            $result[]['days'] = pow(2, $t - 1);
                            $result[count($result) - 1]['time'] = $scheduleitem;
                            $previousSchedule = $scheduleitem;
                        } else {
                            $result[count($result) - 1]['days'] = $result[count($result) - 1]['days'] + pow(2, $t - 1);
                            $result[count($result) - 1]['time'] = $scheduleitem;
                        }
                    }
                }
            }
        }

        return count($result) > 0 ? $result : "null";
    }

    function count_bitmask($workdays)
    {
        $days = array("пн", 'вт', 'ср', 'чт', 'пт', 'сб', 'вс');
        $workdays = explode(",", $workdays);
        $result = 0;
        foreach ($workdays as $workday) {
            $workday = mb_strtolower($workday);
            $MaskPosition = array_search($workday, $days);
            $result = $result + pow(2, $MaskPosition);
        }

        return $result;
    }
}
