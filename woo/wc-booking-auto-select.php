<?php
/**

 * Plugin Name: WooCommerce Booking Auto-Select with WPML v2.5
 * Description: Auto-selecciona franja horaria en productos Bookings cuando solo existe una opción (compatible con WPML).
 * Ubicación: /wp-content/mu-plugins/wc-booking-auto-select.php
 * Version: 2.5
 * Author: 22MW
 */

add_action('wp_footer', 'wc_booking_auto_select_wpml');

function wc_booking_auto_select_wpml() {
    if (!is_singular('product')) return;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        //console.log('🟢 WC Booking Auto-Select WPML: Script iniciado');
        
        var bookingForm = $('.wc-bookings-booking-form, #wc-bookings-booking-form');
        if (bookingForm.length === 0) {
            //console.log('❌ No se encontró formulario de booking');
            return;
        }
        
        var autoSelectEnabled = true;
        
        function autoSelectSingleBlock() {
            if (!autoSelectEnabled) return;
            
            //console.log('🚀 autoSelectSingleBlock ejecutándose...');
            
            var availableBlocks = $('.block-picker li.block a');
            //console.log('📋 Bloques encontrados:', availableBlocks.length);
            
            if (availableBlocks.length === 0) {
                //console.log('⚠️ No hay bloques aún, esperando...');
                return;
            }
            
            availableBlocks.each(function(i) {
                var dataValue = $(this).data('value');
                var text = $(this).text().trim();
                //console.log('  📌 Bloque ' + (i+1) + ':', dataValue, '|', text);
            });
            
            if (availableBlocks.length === 1) {
                var singleBlock = availableBlocks.first();
                var blockValue = singleBlock.data('value');
                var blockText = singleBlock.text().trim();
                
                //console.log('🎯 ¡SOLO UN BLOQUE DISPONIBLE!', blockValue);
                
                if (!singleBlock.closest('li').hasClass('selected') && !singleBlock.hasClass('selected')) {
                    //console.log('🔥 EJECUTANDO AUTO-SELECCIÓN...');
                    
                    autoSelectEnabled = false;
                    singleBlock.trigger('click');
                    
                    // Detectar idioma actual de WPML
                    var currentLang = 'es'; // Default español
                    
                    // Método 1: Por body class de WPML
                    if ($('body').hasClass('wpml-en')) currentLang = 'en';
                    else if ($('body').hasClass('wpml-de')) currentLang = 'de';
                    
                    // Método 2: Por URL si no funciona el anterior
                    if (currentLang === 'es') {
                        var url = window.location.href;
                        if (url.includes('/en/') || url.includes('?lang=en')) currentLang = 'en';
                        else if (url.includes('/de/') || url.includes('?lang=de')) currentLang = 'de';
                    }
                    
                    // Método 3: Por html lang attribute
                    if (currentLang === 'es') {
                        var htmlLang = $('html').attr('lang');
                        if (htmlLang && htmlLang.includes('en')) currentLang = 'en';
                        else if (htmlLang && htmlLang.includes('de')) currentLang = 'de';
                    }
                    
                    // Mensajes según idioma
                    var messages = {
                        'es': 'HORARIO SELECCIONADO AUTOMÁTICAMENTE: ',
                        'en': 'TIME AUTOMATICALLY SELECTED: ',
                        'de': 'ZEIT AUTOMATISCH AUSGEWÄHLT: '
                    };
                    
                    var message = messages[currentLang] || messages['es'];
                    //console.log('🌍 Idioma detectado:', currentLang, '| Mensaje:', message);
                    
                    var msg = '<div class="auto-select-notification" style="background: #210e1b;color: #f5e3c4;padding: 22px 12px;margin: 0px;font-weight: bold;animation: 0.5s ease 0s 1 normal none running slideIn;text-align: center;position: absolute;width: 100%;left: 10%;bottom: 40vh;z-index: 9999999;width: 80%;font-size: 16px;border: 2px solid #f5e3c4;">' + message + blockText.split('(')[0].trim() + '</div>';
                    
                    $('.auto-select-notification').remove();
                    $('.block-picker').before(msg);
                    
                    setTimeout(function() {
                        $('.auto-select-notification').fadeOut(800);
                        autoSelectEnabled = true;
                    }, 4000);
                    
                    //console.log('🎉 ¡AUTO-SELECCIÓN COMPLETADA!');
                } else {
                    //console.log('ℹ️ El bloque ya está seleccionado');
                }
            } else {
                //console.log('ℹ️ Múltiples opciones (' + availableBlocks.length + '), no auto-selecciono');
            }
        }
        
        $(document).on('click', '.ui-datepicker-calendar td a', function() {
            //console.log('📅 FECHA CLICKEADA, esperando carga de horarios...');
            
            var attempts = 0;
            var maxAttempts = 8;
            
            function checkForBlocks() {
                attempts++;
                //console.log('🔄 Intento ' + attempts + ' de ' + maxAttempts);
                
                var blocks = $('.block-picker li.block a');
                if (blocks.length > 0) {
                    //console.log('✅ Bloques cargados, ejecutando auto-select');
                    autoSelectSingleBlock();
                } else if (attempts < maxAttempts) {
                    //console.log('⏳ Bloques aún no cargados, reintentando...');
                    setTimeout(checkForBlocks, attempts * 300);
                }
            }
            
            setTimeout(checkForBlocks, 500);
        });
        
        var formObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            if ($(node).hasClass('block-picker') || $(node).find('.block-picker').length) {
                                //console.log('🆕 Block-picker agregado al DOM');
                                setTimeout(autoSelectSingleBlock, 600);
                            }
                            if ($(node).hasClass('block') || $(node).find('.block').length) {
                                //console.log('🆕 Nuevos bloques detectados');
                                setTimeout(autoSelectSingleBlock, 400);
                            }
                        }
                    });
                }
            });
        });
        
        formObserver.observe(bookingForm[0], {
            childList: true,
            subtree: true
        });
        
        $('head').append('<style>@keyframes slideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}</style>');
        
        //console.log('🟢 Auto-Select WPML: Configuración completada');
    });
    </script>
    <?php
}
