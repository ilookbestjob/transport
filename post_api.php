<?php header("Access-Control-Allow-Origin:*");


$grapth = true;



if (isset($_GET['a'])) $action = 1000 + $_GET['a'];
else $action = 1000;
$action = $_GET['action'];


if (isset($_GET['display'])) {

    $grapth = true;

?>
    <html>
    <header>
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1251">
        <style>
            body {

                background-color: darkgrey;

            }

            .container {
                margin: 70px 150px;
                width: auto;
                height: auto;
                background-color: #fff;
                padding: 50px;
                padding-top: 100px;
            }

            .button {

                background: forestgreen linear-gradient(rgba(255, 255, 255, 0.3), forestgreen);
                color: #fff;
                font-family: tahoma;
                margin: 3px;
                padding: 5px;
                border-radius: 4px;
                cursor: pointer;

            }

            .button:hover {
                background: red linear-gradient(rgba(255, 255, 255, 0.3), red);
                ;
            }
        </style>


        <script>
            const sendAction = (companyid, action) => {
                window.location = "post_api.php?display&companyid=" + companyid + "&action=" + action;



            }
        </script>


    </header>

    <body>
        <div class="container">
        <?php
    } else {
        $grapth = false;
    }
    //   phpinfo();
    //Настройка отображения ошибок
    ini_set('display_errors', 1);

    set_time_limit(36000);
    ini_set('max_execution_time', 36000);
    ignore_user_abort(true);
    ini_set('memory_limit', '2200M');

    error_reporting(E_ALL);

    include "delivery.php"; //класс работы БД
    include "messagelog.class.php"; //Класс логирования сообщений
    include "delivery_company.interface.php"; //родительский класс от которого должны наследоваться все остальные транспортные компании
    include "APItools.class.php"; //Класс для анализа API
    include "comparebase.class.php"; //Класс сравнения баз


    //////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////Переменные по умолчанию для создания экземпляра класса транспотрной компании/////////////////
    /////////////////////////////////////////////////////////////////////////////////////////////////////





    /////веса для получения стоимости доставки
    $weights = array(5, 10, 25, 50, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900, 1000, 1500, 2000);

    /////объемы для получения стоимости доставки
    $volumes = array(0.1);
    ////Id транспортной компании из таблицы Posts
    $postid = 1;
    ////Лимит запрашиваемых строк с со стоимостью достваки
    $priceslimit = 100000;
    ////Уровень детализации
    $detalization = 0;
    ///Флаги для логирования 
    ///   [0]=>true/false  выводить информацию на экран
    ///   [1]=>true/false  выводить информацию в файл
    ///   [2]=>true/false  выводить информацию в БД
    $logflags = [true, true, true];




    ////////////////////////////////////////////////////////////////////
    ///////              ПОДКЛЮЧЕНИЕ ТРАНСПОРТНЫХ КОМПАНИЙ        //////
    ////////////////////////////////////////////////////////////////////

    ////стандартные методы классов транспортных компаний

    ///
    ///    save_terminalsdata()  сохранить данные терминала в таблицу posts и таблицу post_work
    ///


    ///    save_deliveryprices() сохранить стоимости доствки в зависимости от параметров при создаии экземпляра класса
    ///
    ///     ---$weights         веса которые нужно получить
    ///     ---$priceslimit     максимальный лимит строк со стоимостью доставки


    ///
    ///    save_all()  сохраняет данные терминалов и данные стоимости доставки
    ///



    ///
    ///    log() выводит log в зависимости отнастроек
    ///     ---$logflags
    ///             [0]=>true/false  выводить информацию на экран
    ///             [1]=>true/false  выводить информацию в файл
    ///

    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///        Подключение класа транспортной компании DPD          ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    include "dpd_delivery.class.php";
    $dpd_delivery = new dpd_delivery($weights, $volumes, $postid, $priceslimit, $detalization, $logflags, "https://www.dpd.ru/template2/newimages/logo.png", $grapth);

    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////




    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///    Подключение класа транспортной компании Байкал-Сервис    ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    include "baikal_delivery.class.php";
    $postid = 24;
    $priceslimit = 1000000;
    $baikal_delivery = new baikal_delivery($weights, $volumes, $postid, $priceslimit, $detalization, $logflags, "https://petrozavodsk.baikalsr.ru/local/templates/main/i/logo.svg", $grapth);



    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////



    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///    Подключение класа транспортной компании Байкал-Сервис    ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    include "boxberry_delivery.class.php";
    $postid = 13;
    $weights = array(1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000, 11000, 12000, 13000, 14000, 15000);
    $boxberry_delivery = new boxberry_delivery($weights, $volumes, $postid, $priceslimit, $detalization, $logflags, "img\boxberry.png", $grapth);


    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////



    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///    Подключение класа транспортной компании Деловые линии    ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    include "dl_delivery.class.php";
    $postid = 3;
    $weights = array(5, 10, 25, 50, 100, 150, 200, 250, 5, 10, 25, 50, 100, 150, 200, 250, 5, 10, 25, 50, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900, 1000, 1500, 2000);
    $volumes = array(0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1,  0.2, 0.2, 0.2, 0.2, 0.2, 0.2, 0.2, 0.2,  0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

    $dl_delivery = new dl_delivery($weights, $volumes, $postid, $priceslimit, $detalization, $logflags, "https://novtehkomponent.ru/upload/medialibrary/fe3/fe3eeae273e7802667b60813a895bb69.png", $grapth);


    ////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////// 



    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///    Подключение класа транспортной компании СДЭК             ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    include "cdek_delivery.class.php";
    $postid = 31;
    $weights = array(5, 10, 25, 50, 100, 150, 200, 250, 5, 10, 25, 50, 100, 150, 200, 250, 5, 10, 25, 50, 100, 150, 200, 250, 300, 400, 500, 600, 700, 800, 900, 1000, 1500, 2000);
    $volumes = array(0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1,  0.2, 0.2, 0.2, 0.2, 0.2, 0.2, 0.2, 0.2,  0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 0.3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);

    $cdek_delivery = new cdek_delivery($weights, $volumes, $postid, $priceslimit, $detalization, $logflags, "https://www.waypark.ru/upload/iblock/911/91101b34736345fc6d3968398b0cfcf2.jpg", $grapth);


    ////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////// 

    ////////////////////////////////////////////////////////////////////
    ///                                                             ////
    ///                      Обработка API                          ////
    ///                                                             ////
    ////////////////////////////////////////////////////////////////////


    if ((isset($_GET['action'])) && (isset($_GET['companyid']))) {
        $company = '';


        switch ($_GET['companyid']) {

            case 13:
                $company = $boxberry_delivery;
                break;
            case 24:
                $company = $baikal_delivery;
                break;
            case 1:
                $company = $dpd_delivery;
                break;
            case 3:
                $company = $dl_delivery;
                break;
            case 31:
                $company = $cdek_delivery;
                break;
        }

        switch ($_GET['action']) {

            case 1:
                $company->save_all_try();
                break;
            case 2:
                $company->save_terminalsdata_try();
                break;
            case 3:
                $company->save_deliveryprices_try();
                break;
            case 4:
                $company->check_Bases();
                break;
            case 5:
                $company->compare_Bases();
                break;
            case 6:
                $company->toggleStop($_GET['companyid']);
                break;
            case 1001:
                echo json_encode($company->get_currentprice($_GET));
                break;
            case 1002:
                $company->save_deliveryprices_try($_GET['city2']);
                break;
            case 1003:
                $company->save_terminalsdata_try($_GET['city2']);
                break;
        }
    }




    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////


    if (isset($_GET['display'])) {
        ?>



        </div>
    </body>

    </html>
<?php
    } ?>