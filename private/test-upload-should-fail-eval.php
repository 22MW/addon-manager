<?php
/**
 * Plugin Name: AM Upload Test (Should Fail)
 * Description: Archivo de prueba para validar bloqueo por patron inseguro.
 * Version: 0.0.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Debe fallar en el validador de subida por patron bloqueado.
eval('$am_test = true;');
