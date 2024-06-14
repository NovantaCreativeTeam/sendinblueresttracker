<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 *  Funzione che effettua l'upgrade del modulo alla versione 0.9.8
 *  Registra l'hook actionGetProductPropertiesAfter per il calcolo dei tempi di spedizione
 */
function upgrade_module_1_0_1($module)
{
    if($module->registerHook('displayHeader') == false) {
        return false;
    }

    return true;
}