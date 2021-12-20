<?php



class APItools
{
    public static $lastcomand, $lastresponse;



    public  static function prepareResponseStructure($newAPI, $level = 0, $gkey = "")
    {

        $array = json_decode(json_encode($newAPI), true);
        if ($level == 0) {
            //$array = array_multisort($array);
        }
        $llevel = $level;
        $style = "style=\"margin-left:" . (40 + $level * 20) . "px;display:block;width:400px;margin-top:20px;\"";
        $innerstyle = "style=\"margin-left:" . (40 + $level * 20 + 20) . "px;display:block;width:400px;border-left:solid 1px navy;padding-left:20px;margin-top:20px;\"";

        $result = array();


        $result['html'] = $level == 0 ? "<div style=\"background-color:#CCCCCC50;font-family:tahoma;color:navy;width:auto;border:solid 1px #cccccc;margin:100px;margin-right:30%;margin-top:50px;pading:50px;max-height:600px;overflow:scroll; \">" : "";


        $result['text'] = ($level == 0 ? "Корневой объект содержит следующие поля: " : "Объект $gkey содержит следующие поля: ");
        $result['html'] .= ($level == 0 ? "<div $style><strong>Ответ API</strong></div><div $innerstyle>" : (is_integer($gkey) ? "<div $style><strong>Массив [0..n]</strong></div><div $innerstyle>" : "<div $style><strong>Объект $gkey</strong></div><div $innerstyle>"));

        foreach ($array as $key => $arrayItem) {
            if (is_array($arrayItem)) {
                $array = ksort($arrayItem);
                $result['html'] .= "</div>";
                $level++;
                $result['text'] .= self::prepareResponseStructure($arrayItem, $level, $key)['text'];
                $result['html'] .= self::prepareResponseStructure($arrayItem, $level, $key)['html'];
                $result['html'] .= "<div $innerstyle>";
             
            } else {
                //$result = array_merge($result, array($key => ''));

                $result['text'] .= $key . ",";

                $result['html'] .= (is_integer($key)) ? ($key > 0 ? "Массив [0..n]" : "") : "<div>" . $key . "</div>";
            }
        }

        $result['html'] .= "</div>";
        $result['html'] .= $llevel == 0 ? "</div>" : "";
        return $result;
    }
    public static function displayResponseStructure($command, $response)
    {
        $r = self::prepareResponseStructure($response)['html'];
        if (($command != self::$lastcomand) || ($r != self::$lastresponse)) {

            echo "Ответ: " . ($r != self::$lastresponse) . "</br></br></br>Последний ответ:  " . $r;
            print_r($r);
            echo "trtrtrt" . $r;


            self::$lastcomand =  $command;
            self::$lastresponse = $r;
        }
    }
}
