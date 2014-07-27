<?php

function button_to($html_class, $url, $data, $label, $confirm_msg){
    $result = '';
    $result .= "<form method='post' action='$url' style='display: inline;'>";
    foreach($data as $key=>$value){
        $result .= "<input type='hidden' name='$key' value='$value'>";
    }
    
    $result .= (!$confirm_msg) ?
        "<button class='$html_class'>$label</button>" :
        "<button class='$html_class' onclick=\"return confirm('{$confirm_msg}')\">$label</button>" ;        
    $result .= "</form>";
    
    return $result;  
}

function hidden($name, $value){
    echo "<input type='hidden' name='$name' value='$value' />";
}

function look($v){
    exit(var_export($v));
}

function typography($string){
    return nl2br(str_replace(' ','&nbsp;',htmlentities($string)));
}
