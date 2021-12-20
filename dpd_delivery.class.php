<?php

include "dpd_service.class.php"; //класс от разаработчиков DPD

class dpd_delivery extends delivery_company
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
            echo "<div style=\"width:auto;border-bottom:solid 1px #ccc;margin:0 150px;margin-bottom:15px;display:grid;grid-template-columns:200px,1fr \"><img src=\"" . $this->logo . "\" style=\"display:block;height:30px;width:auto;margin-bottom:5px\"><div style=\"display:flex;align-items:flex-end;justify-content:flex-end;\"><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 1);\">Получить все</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 2);\">Получить терминалы</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 3);\">Получить стоимость</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 4);\">Проверить БД</div><div class=\"button\" onclick=\"sendAction(" . $this->postid . ", 5);\">Сравнить БД</div></div></div>";
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



    function save_terminalsdata($cityid = null)
    {
        $fp = $this->lockPost($this->postid);
        //Получаем терминалы
        $affiliates = $this->dpd->getTerminalList();
        print_r($affiliates);

        $this->delivery->printlog("Получено " . count($affiliates->terminal) . " терминалов.", array("margin-left" => "50px"));

        //APItools::displayResponseStructure("getTerminalList()", $affiliates);
        //Очищаем флаги обновления данных
        $this->delivery->clear_UpdateFlags($cityid);

        foreach ($affiliates->terminal as $terminal) {
            // echo "erer</br>";
            //Получаем  id  города из таблицы cityfias
            $city_id = $this->delivery->get_TownIdByField("dpdcode", $terminal->address->cityCode);
            if ($city_id == '') {
                $this->messagelog->addLog(2, $this->postid, "Проверка наличия в таблице городов", "Города " . $terminal->address->cityName . "(" . $terminal->address->countryCode . ") из региона " . $terminal->address->regionName . "(" . $terminal->address->regionCode . ") c кодом dpdcity=\"" . $terminal->address->cityCode . "\" не обнаружено в таблице city", 0);
            }


            //обход результата

            $terminal_id = $terminal->terminalCode;
            $terminal_name = $terminal->terminalName;
            $terminal_street = $terminal->address->cityName . ", " . (isset($terminal->address->streetAbbr) ? $terminal->address->streetAbbr : "") . " " . $terminal->address->street . ", " . (isset($terminal->address->houseNo) ? $terminal->address->houseNo : "");
            $terminal_house = isset($terminal->address->houseNo) ? $terminal->address->houseNo : "";
            $terminal_x = '';
            $terminal_y = '';

            if ($cityid) {
                if ($cityid == $city_id) {
             
                   // echo "Загрузка терминала " . $terminal->terminalName . " DPD города " . $terminal->address->cityName."</br></br></br>";

                    $post = $this->delivery->save_TerminalData($city_id, $terminal_id, $terminal_name, $terminal_street, $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 0));
                    $this->delivery->save_Schedule($post, $this->format_TerminalShedule($terminal->schedule));

                    
                $post = $this->delivery->save_TerminalData($city_id,'', 'Адресная доставка в г.'.$terminal->address->cityName, 'Адресная доставка в г.'.$terminal->address->cityName, $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 1));
                }
            } else {
             //   echo "cityid $cityid";
              //  echo "city_id $city_id";
                //echo "Загрузка терминала " . $terminal->terminalName . " DPD города " . $terminal->address->cityName."</br></br></br>";
                
                $post = $this->delivery->save_TerminalData($city_id, $terminal_id, $terminal_name, $terminal_street, $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 0));
                $this->delivery->save_Schedule($post, $this->format_TerminalShedule($terminal->schedule));

                $post = $this->delivery->save_TerminalData($city_id,'', 'Адресная доставка в г.'.$terminal->address->cityName, 'Адресная доставка в г.'.$terminal->address->cityName, $terminal_house, $terminal_x, $terminal_y, array("flkurier" => 1));
            }
          
                

        }
        echo "OK";
        $this->delivery->set_DeleteFlags();
        $this->unlockPost($fp, $this->postid);
    }

    function save_deliveryprices($city_id = null)
    {
        $fp = $this->lockPost($this->postid);
        $ctr = 0;

        $Start=date("Y-m-d H:i:s");


        $Cities = $this->delivery->get_CitytoUpdatePrices($city_id);

        foreach ($this->weights as $weight) {
            $this->delivery->printlog("Вес: " . $weight, 1);

            foreach ($Cities as $City) {
                $ctr++;

                if ($ctr < $this->priceslimit) {



                    $arData = array();
                    $arData['delivery']['cityId'] = "" . $City->dpdid;
                    $arData['weight'] = $weight;
                    if ($City->flkurier>0) $arData['selfDelivery'] = false;
                    $arData['serviceCode'] = "ECN";
                    $pricedata = $this->dpd->getServiceCost($arData);
                    if (isset($pricedata->error)) {
                        $this->messagelog->addLog(2, $this->postid, "Получение данных стоимости", $pricedata->error, 0);
                    } else {
                        $price = $pricedata->return->cost;
                        $days = $pricedata->return->days;

                       //echo  "Получен ответ для терминала " . $City->post_id . " города " . mb_convert_encoding($City->city, 'utf-8', 'windows-1251') . ":цена " . $price . " вес " . $weight . " объем 0.1 срок " . $days."</BR></BR>";

                        $this->delivery->save_pricedata($City->post_id, $City->flkurier, $price, $weight, 0.1, $days, array("postservice_id" => 2));
                    }
                }
            }
        }
        echo "OK";
        $this->delivery->set_DeleteFlags();
        $this->delivery->set_PriceDeleteFlags($Start);
        $this->unlockPost($fp, $this->postid);
    }



    function get_currentprice($url)
    {




        $City = $this->delivery->get_TownArrIdByField("row_id", $url['city']);
        $arData = array();
        $arData['delivery']['cityId'] = "" . $City['dpdcityId'];

        $arData['weight'] = $url['weight'];
        $arData['serviceCode'] = "ECN";

        $pricedata = $this->dpd->getServiceCost($arData);


        $ret['days'] = $pricedata->return->days;
        $ret['price'] = $pricedata->return->cost;

        $arData['selfDelivery'] = false;
        $pricedata = $this->dpd->getServiceCost($arData);
        $ret['ddays'] = $pricedata->return->days;
        $ret['dprice'] = $pricedata->return->cost;

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
        $localschedule = json_decode(json_encode($schedule), true);

        //   print_r($localschedule);
        foreach ($localschedule as $key => $sch) {

            if ($sch['operation'] == "SelfPickup") {

                if (isset($sch['timetable']['weekDays'])) {
                    $mask = $this->count_bitmask($sch['timetable']['weekDays']);
                    $result[]['days'] = $mask;
                    $result[count($result) - 1]['time'] = $sch['timetable']['workTime'];
                } else {

                    foreach ($sch['timetable'] as $timetableitem) {

                        if ($timetableitem['weekDays'] != "") {
                            $mask = $this->count_bitmask(trim($timetableitem['weekDays']));
                            $result[]['days'] = $mask;
                            $result[count($result) - 1]['time'] = $timetableitem['workTime'];
                        }
                    }
                }
            }
        }

        return count($result) > 0 ? $result : null;
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
