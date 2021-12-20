<?php
//TODO   отладить алгоритм получения расписания
class boxberry_delivery extends delivery_company
{

    //Переменные подключения к api
    private $token = "01aa5da97b953b426ca1bb5f69ccc78f";
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
    private $Starttime;
    private $Endtime;
    private $Duration;



    public function __construct(array $weights, array $volumes, $postid, $priceslimit,  $logdetalization, array $logflags, $logo = null, $graph = true)
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






    function save_terminalsdata($cityid = null)
    {

        $fp = $this->lockPost($this->postid);
        //Получаем терминалы
        $Terminals = $this->get_getterminals();
   
        //Очищаем флаги обновления данных
        $this->delivery->clear_UpdateFlags($cityid);
        //print_r($Terminals);
        foreach ($Terminals as $terminal) {


            //Получаем  id  города из таблицы cityfias
            $city_id = $this->delivery->get_TownIdByField("boxberrycode", $terminal->CityCode);

            //обход результата

            if ($cityid) {
                if (($cityid) && ($cityid == $city_id)) {
                    echo "cityid $cityid";
                    echo "city_id $city_id";
                    echo "Загрузка терминала " . $terminal->Name;

                    $terminalinfo = $this->get_getterminalinfo($terminal->Code);
                    $terminal_id =    $terminal->Code;
                    $terminal_name = $terminalinfo->Name;
                    $terminal_street = $terminal->Address;
                    $terminal_house = "";
                    $terminal_x = explode(',', $terminal->GPS)[0];
                    $terminal_y = explode(',', $terminal->GPS)[1];
                    $schedule = "";



                    $post = $this->delivery->save_TerminalData($city_id, $terminal_id, $terminal_name, $terminal_street, $terminal_house, $terminal_x, $terminal_y, array('flterminal' => ($terminal->Acquiring == 'Yes' ? 1 : 0), 'period' => $terminal->DeliveryPeriod, 'limitvolume' => $terminal->VolumeLimit, 'limitload' => $terminal->LoadLimit));

                    $this->delivery->save_Schedule($post, $this->format_TerminalShedule(json_decode($terminalinfo->schedule, true)['CurrentWorkHours']['day']));
                }
            } else {
                echo "cityid $cityid";
                echo "city_id $city_id";
                echo "Загрузка терминала(все) " . $terminal->Name;
                $terminalinfo = $this->get_getterminalinfo($terminal->Code);
                $terminal_id =    $terminal->Code;
                $terminal_name = $terminalinfo->Name;
                $terminal_street = $terminal->Address;
                $terminal_house = "";
                $terminal_x = explode(',', $terminal->GPS)[0];
                $terminal_y = explode(',', $terminal->GPS)[1];
                $schedule = "";



                $post = $this->delivery->save_TerminalData($city_id, $terminal_id, $terminal_name, $terminal_street, $terminal_house, $terminal_x, $terminal_y, array('flterminal' => ($terminal->Acquiring == 'Yes' ? 1 : 0), 'period' => $terminal->DeliveryPeriod, 'limitvolume' => $terminal->VolumeLimit, 'limitload' => $terminal->LoadLimit));
                $this->delivery->save_Schedule($post, $this->format_TerminalShedule(json_decode($terminalinfo->schedule, true)['CurrentWorkHours']['day']));
            }
        }

        $this->delivery->check_TerminalChanges();



        $this->messagelog->addLog(1, $this->postid, "Информация от таблице Post",  "API " . mb_convert_encoding($this->name['name'], 'utf-8', 'windows-1251') . " содержит следующую информацию: " . $this->delivery->checkFlags(), 0);


        $this->delivery->set_DeleteFlags();

        $this->unlockPost($fp, $this->postid);
    }


    function save_deliveryprices($city_id = null)
    {
        $fp = $this->lockPost($this->postid);
        $ctr = 0;

        $Start=date("Y-m-d H:i:s");


    

        $FIASes = $this->delivery->get_CitytoUpdatePrices($city_id);
        if (count($FIASes) == 0) {
            $this->messagelog->addLog(1, $this->postid, "Ошибка получения списка терминалов",  "Попытка получить  список терминалов для обновления цен не вернула ни одного результата", 0);
        }

        $this->messagelog->addLog(0, $this->postid, "информация об обновлении цен", "Получено следующее количество строк для обновления цен доставки:" . count($FIASes), 0);
        //  print_r($FIASes);

        foreach ($this->weights as $weight) {
            foreach ($FIASes as $FIAS) {
                $ctr++;
                if ($ctr < $this->priceslimit) {
                    if ($FIAS->boxberrycode == '') {
                        $this->messagelog->addLog(1, $this->postid, "Ошибка получения кода терминала",  "Попытка  получить код терминала в городе " . $FIAS->city . " для обновления цен вернула пустой результат", 0);
                    }

                    $pricedata = $this->get_price($FIAS->code, $weight, false);
                    echo  "</BR></BR></BR>";
                    print_r($pricedata);

                    echo  "</BR></BR></BR>";



                    if (!isset($pricedata->err)) {
                        $price = $pricedata->price;
                        $days = $pricedata->delivery_period;

                        echo  "Получен ответ для терминала " . $FIAS->post_id . " города " . mb_convert_encoding($FIAS->city, 'utf-8', 'windows-1251') . ":цена " . $price . " вес " . ($weight / 1000) . " объем 0.1  срок " . $days . "</br></br>";


                        $this->delivery->save_pricedata($FIAS->post_id, 0, $price, $weight / 1000, 0.1, $days, array());
                    } else {
                        $this->messagelog->addLog(1, $this->postid, "Ошибка получения стоимости",  "Попытка получить информацию о стоимости доствки груза весом $weight до " . $FIAS->city . " завершилось неудачей.Ответ API:$pricedata->err", 0);
                    }
                }
            }
        }
        $this->Endtime = time();
        echo "OK";
        $this->delivery->set_DeleteFlags();
        $this->delivery->set_PriceDeleteFlags($Start);
        $this->unlockPost($fp, $this->postid);
    }



    function get_currentprice($url)
    {




        $City = $this->delivery->get_TownArrIdByField("row_id", $url['city']);
        $pricedata = $this->get_price($City['boxberrycode'], $url['weight'], false);


        $ret['price'] = $pricedata->price;
        $ret['days'] = $pricedata->delivery_period;


        $ret['weight'] = $url['weight'];
        $ret['volume'] =  $url['volume'];;
        $ret['places'] = $url['places'];;

        return $ret;
    }


    ///////////////////////////----функции работы с API----/////////////////////////////////

    //Базовая функция вывода
    function get_data($command, $token)
    {
        echo "</BR></BR></BR></BR>";
        echo "Запрос";
       echo $url = 'http://api.boxberry.ru/json.php?token=' . $token . '&method=' . $command;
       echo "</BR></BR></BR></BR>";
        $data = file_get_contents($url);
        return $data;
    }



    //Получаем пункты выдачи
    function get_getterminals()
    {
        return  json_decode($this->get_data("ListPoints&prepaid=1", $this->token));
    }

    //Получаем стоимость доставки до каждого пункта груза обемом 1 куб и весом 5,10,25,50,100,150,250,300,400,500,600,700,800,900,1000,1500,2000 кг

    public function get_getterminalinfo($terminal)
    {

        return  json_decode($this->get_data("PointsDescription&code=" . $terminal, $this->token));
    }


    function get_price($target, $weight, $delivery = false)
    {

         return  json_decode($this->get_data("DeliveryCosts&weight=" . $weight . "&targetstart=10051&target=" . $target, $this->token));
    }

    function format_TerminalShedule($schedule)
    {
        $ctr = -1;
        $localschedule = $schedule[0];
        $mask = 0;
        $result = array();
        foreach ($schedule as $key => $scheduleitem) {
            if (($localschedule['workEnd'] != $scheduleitem['workEnd']) || ($localschedule['workStart'] != $scheduleitem['workStart'])) {
                $result[]['days'] = $mask;
                $result[count($result) - 1]['time'] = $localschedule['workStart'] . '-' . $localschedule['workEnd'];
                $localschedule = $scheduleitem;
                $mask = 0;
            }

            if (($scheduleitem['workStart'] != "") && ($scheduleitem['workEnd'] != "")) {
                $mask = $mask + pow(2, $key);
            }


            if ((count($schedule) - 1 == $key) && ($mask != 0)) {
                $result[]['days'] = $mask;
                $result[count($result) - 1]['time'] = $localschedule['workStart'] . '-' . $localschedule['workEnd'];
            }
        }
        return $result;
    }
}
