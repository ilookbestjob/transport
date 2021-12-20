<?php

class compareBase
{

    private $LocalConnection, $RemoteConnection;


    private $server = '';
    private $base = '';
    private $user = '';
    private $bdpassword = '';


    private $RemoteServer = '';
    private $RemoteBase = '';
    private $RemoteUser = '';
    private $RemoteBdPassword = '';
    private $RemoteBdPort = 12011;

    public function __construct()
    {
        $this->LocalConnection = $this->connectLocal();
        $this->RemoteConnection = $this->connectRemote();
    }

    public function connectLocal()
    {

        return mysqli_connect($this->server, $this->user, $this->bdpassword, $this->base);
    }


    public function connectRemote()
    {

        return mysqli_connect($this->RemoteServer, $this->RemoteUser, $this->RemoteBdPassword, $this->RemoteBase, $this->RemoteBdPort);
    }

    public function checkConnections()
    {

        echo "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:180px;font-family:tahoma;color:gray;font-size:18px;\">Проверка БД</div>";
        $State = true;
        if ($this->LocalConnection) {
            echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:forestgreen;font-size:18px;\">Локальное  соединение установлено</div>";
        } else {
            echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:red;font-size:18px;\">Локальное  соединение НЕ установлено</div>";
            $State = false;
        }


        if ($this->RemoteConnection) {
            echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:forestgreen;font-size:18px;\">Удаленное  соединение установлено</div>";
        } else {
            echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:red;font-size:18px;\">Удаленное  соединение НЕ установлено</div>";
            $State = false;
        }
        return $State;
    }


    public function compareStructure($tables)
    {
        foreach ($tables as $table) {
            echo "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:180px;font-family:tahoma;color:gray;font-size:18px;\">Проверка структуры таблиц $table </div>";

            $sql = "select * from `" . $table . "` limit 1";
            $sql_result = mysqli_query($this->RemoteConnection, $sql);
            $RemoteStructure = [];
            $sql_fields = mysqli_fetch_fields($sql_result);

            foreach ($sql_fields as $field) {
                $RemoteStructure[] = $field->name;
            }
            sort($RemoteStructure);



            $sql = "select * from `" . $table . "` limit 1";
            $sql_result = mysqli_query($this->RemoteConnection, $sql);
            $LocalStructure = [];
            $sql_fields = mysqli_fetch_fields($sql_result);

            foreach ($sql_fields as $field) {
                $LocalStructure[] = $field->name;
            }
            sort($LocalStructure);


            $localCompare = array_diff($LocalStructure, $RemoteStructure);
            $RemoteCompare = array_diff($RemoteStructure, $LocalStructure);

            if ((count($localCompare) == 0) && (count($RemoteCompare) == 0)) {
                echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:forestgreen;font-size:18px;\">Различий в структуре не обнаружено</div>";
            } else {
                echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:red;font-size:18px;\">Обнаружены различия в таблицах</div>";

                if (count($localCompare) != 0) {
                    echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:red;font-size:18px;\">В таблице на сервере отсутвуют следующие поля</div>";
                    foreach ($localCompare as $field) {
                        echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:220px;font-family:tahoma;color:red;font-size:18px;\">$field</div>";
                    }
                }


                if (count($RemoteCompare) != 0) {
                    echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:200px;font-family:tahoma;color:red;font-size:18px;\">В локальной таблице отсутвуют следующие поля</div>";
                    foreach ($RemoteCompare as $field) {
                        echo "<div style=\"width:auto;margin:0 5px;margin-bottom:5px;margin-left:220px;font-family:tahoma;color:red;font-size:18px;\">$field</div>";
                    }
                }
            }
        }
    }

    public function compareBaseData($table, $equalfields, $fieldstocompare = array(), $filter = array(), $limit = 1000)
    {
        echo "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:180px;font-family:tahoma;color:gray;font-size:18px;\">Сравнение данных таблиц $table </div>";

        mysqli_query($this->LocalConnection, "SET NAMES cp1251");
        mysqli_query($this->RemoteConnection, "SET NAMES cp1251");


        if (count($filter) !== 0) {
            $where = " where ";
        } else {
            $where = "";
        }

        foreach ($filter as $key => $filteritem) {
            if ($where == " where ") {
                $where .= "`" . $key . "`='" . $filteritem . "' ";
            } else {
                $where .= " and `" . $key . "`='" . $filteritem . "' ";
            }
        }




        $sqllocal = "select * from `" . $table . "`" . $where;

        $sqllocal_result = mysqli_query($this->LocalConnection, $sqllocal);
        //  echo mysqli_num_rows($sqllocal_result);





        $iterations = 0;
        while ($sqllocal_row = mysqli_fetch_array($sqllocal_result)) {



            if (count($equalfields) !== 0) {
                $where = " where ";
            } else {
                $where = "";
            }



            $iterations++;
            foreach ($equalfields as $fielditem) {

                if (($iterations > $limit) && ($limit !== 0)) {
                    exit;
                }
                if ($where == " where ") {
                    $where .= "`" . $fielditem . "`='" . $sqllocal_row[$fielditem] . "' ";
                } else {
                    $where .= " and `" . $fielditem . "`='" .  $sqllocal_row[$fielditem] . "' ";
                }
            }



            $sqlremote = "select * from `" . $table . "`" . $where;



            $sqlremote_result = mysqli_query($this->RemoteConnection, $sqlremote);
            $sqlremote_row = mysqli_fetch_array($sqlremote_result);
      

            echo  "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:200px;font-family:tahoma;color:darkgray;font-size:18px;\">Данне строки " . $iterations . " </div>";

            if ($sqlremote_row) {
                foreach ($fieldstocompare as $fieldtocompare) {
                    if ($sqlremote_row[$fieldtocompare] != $sqllocal_row[$fieldtocompare]) {

                        echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:red;font-size:15px;\">Поля <strong>$fieldtocompare</strong> не равны. </div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:red;font-size:15px;\">     <strong>Локальная база  </strong>" . mb_convert_encoding($sqllocal_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:red;font-size:15px;\">     <strong>База на сервере </strong>" . mb_convert_encoding($sqlremote_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div>";
                    } else {

                        if ($sqlremote_row[$fieldtocompare] != "") {
                            echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:forestgreen;font-size:15px;\">Поля <strong>$fieldtocompare</strong>  равны. </div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:forestgreen;font-size:15px;\">  <strong> Локальная база</strong>  " . mb_convert_encoding($sqllocal_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:forestgreen;font-size:15px;\"> <strong>    База на сервере</strong> " . mb_convert_encoding($sqlremote_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div>";
                        } else {
                            echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:orange;font-size:15px;\">Поля <strong>$fieldtocompare</strong>  пустые. </div>";
                        }
                    }
                }
            } else {

                echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:red;font-size:15px;\">Запись не обнаружена на сервере</div>";
            }
        }
    }


    public function compareBaseDataJoin($table, $equalfields, $fieldstocompare = array(), $filter = array(), $limit = 1000)
    {
        echo "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:180px;font-family:tahoma;color:gray;font-size:18px;\">Сравнение данных таблиц $table </div>";

        mysqli_query($this->LocalConnection, "SET NAMES cp1251");
        mysqli_query($this->RemoteConnection, "SET NAMES cp1251");


        if (count($filter) !== 0) {
            $where = " where ";
        } else {
            $where = "";
        }

        foreach ($filter as $key => $filteritem) {
            if ($where == " where ") {
                $where .= "`" . $key . "`='" . $filteritem . "' ";
            } else {
                $where .= " and `" . $key . "`='" . $filteritem . "' ";
            }
        }




         $sqllocal = "select * from `post` inner join " . $table . " on post.row_id=$table.post_id " . $where;
         ;


         echo  $sqllocal."</br>";

        $sqllocal_result = mysqli_query($this->LocalConnection, $sqllocal);






        $iterations = 0;
        while ($sqllocal_row = mysqli_fetch_array($sqllocal_result)) {



            if (count($equalfields) !== 0) {
                $where = " where ";
            } else {
                $where = "";
            }



            $iterations++;
            foreach ($equalfields as $fielditem) {

                if (($iterations > $limit) && ($limit !== 0)) {
                    exit;
                }
                if ($where == " where ") {
                    $where .= $fielditem . "='" . $sqllocal_row[$fielditem] . "' ";
                } else {
                    $where .= " and " . $fielditem . "='" .  $sqllocal_row[$fielditem] . "' ";
                }
            }



             $sqlremote = "select * from `post` inner join " . $table . " on post.row_id=$table.post_id " . $where;

              echo $iterations."| ".$sqlremote."</br></br>";


            $sqlremote_result = mysqli_query($this->RemoteConnection, $sqlremote);
            $sqlremote_row = mysqli_fetch_array($sqlremote_result);
        

            echo  "<div style=\"width:auto;margin:50px 50px;margin-bottom:15px;margin-left:200px;font-family:tahoma;color:darkgray;font-size:18px;\">Данне строки " . $iterations . " </div>";

            if ($sqlremote_row) {
                foreach ($fieldstocompare as $fieldtocompare) {
                    if ($sqlremote_row[$fieldtocompare] != $sqllocal_row[$fieldtocompare]) {

                        echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:red;font-size:15px;\">Поля <strong>$fieldtocompare</strong> не равны. </div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:red;font-size:15px;\">     <strong>Локальная база  </strong>" . mb_convert_encoding($sqllocal_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:red;font-size:15px;\">     <strong>База на сервере </strong>" . mb_convert_encoding($sqlremote_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div>";
                    } else {

                        if ($sqlremote_row[$fieldtocompare] != "") {
                            echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:forestgreen;font-size:15px;\">Поля <strong>$fieldtocompare</strong>  равны. </div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:forestgreen;font-size:15px;\">  <strong> Локальная база</strong>  " . mb_convert_encoding($sqllocal_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div><div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:240px;font-family:tahoma;color:forestgreen;font-size:15px;\"> <strong>    База на сервере</strong> " . mb_convert_encoding($sqlremote_row[$fieldtocompare], 'utf-8', 'windows-1251') . "</div>";
                        } else {
                            echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:orange;font-size:15px;\">Поля <strong>$fieldtocompare</strong>  пустые. </div>";
                        }
                    }
                }
            } else {

                echo  "<div style=\"width:auto;margin:5px 50px;margin-bottom:15px;margin-left:220px;font-family:tahoma;color:red;font-size:15px;\">Запись не обнаружена на сервере</div>";
            }
        }
    }


    public function checkBases($posttype_id)
    {
        if ($this->checkConnections()) {
           
            $this->compareBaseDataJoin("postcalc", array("posttype_id", "code", "weight"), array("del", "post_id", "weight", "price", "pricekg", "days", "volume", "Pref", "fl", "flkurier", "postservice_id", "dataupd"), array("posttype_id" => $posttype_id), 0);
        }
    }
}
