<?php

(@__DIR__ == '__DIR__') && define('__DIR__',  realpath(dirname(__FILE__)));

function get_template($form = 'principal'){
    $file = __DIR__.'/Html/Devoluciones_'.$form.'.html';
    
    if (file_exists($file)) {
        return file_get_contents($file);
    }
    
    return "<!-- Error: No se encontró la plantilla HTML -->";
}

function retorna_vista($vista, $data = array()){
    $html = '';
    
    switch($vista){
        case 'principal':
            $html = get_template($vista);
            if (isset($data["categoria"])) {
                $html = str_replace("{categoria}", $data["categoria"], $html);
            }
            break;
    }
    
    print $html;
}

function principal(){
    $opc = isset($_GET['opc']) ? htmlspecialchars($_GET['opc']) : "principal";
    
    switch($opc){
       case 'principal':
           retorna_vista($opc);
           break;
       default:
           retorna_vista('principal');
           break;
    }
}

principal();