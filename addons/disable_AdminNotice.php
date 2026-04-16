<?php
/**
 * Plugin Name: Disable Admin Notices
 * Description: Oculta avisos del admin y permite mostrarlos/ocultarlos desde un botón en la barra superior.
 * Marketing Description: Limpia el escritorio de WordPress y deja visibles solo los avisos cuando tú decidas.
 * Parameters: Sin configuración: activa el módulo y controla avisos desde la barra superior.
 * Version: 2.3.0
 * Author: 22 MW
 */

add_action('admin_bar_menu', 'dan_add_admin_bar_item', 100);
function dan_add_admin_bar_item($admin_bar) {
    $admin_bar->add_menu(array(
        'id'    => 'dan-toggle-notices',
        'title' => '<span class="dan-count">0</span> Mostrar Avisos',
        'href'  => '#',
        'meta'  => array(
            'class' => 'dan-toggle-btn'
        )
    ));
}

add_action('admin_head', 'dan_hide_notices');
function dan_hide_notices() {
    echo '<style>
        body.dan-hide-notices .notice,
        body.dan-hide-notices .updated,
        body.dan-hide-notices .error,
        body.dan-hide-notices .update-nag {
            display: none !important;
        }
        #wp-admin-bar-dan-toggle-notices .ab-item {
            cursor: pointer !important;
        }
        #wp-admin-bar-dan-toggle-notices .dan-count {
            background: #dc3232;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: bold;
            margin-right: 5px;
            font-size: 11px;
        }
    </style>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.classList.add("dan-hide-notices");
            
            function updateCount() {
                var notices = document.querySelectorAll(".notice, .updated, .error, .update-nag");
                var count = notices.length;
                var countEl = document.querySelector(".dan-count");
                if (countEl) {
                    countEl.textContent = count;
                }
            }
            
            updateCount();
            
            document.getElementById("wp-admin-bar-dan-toggle-notices").addEventListener("click", function(e) {
                e.preventDefault();
                document.body.classList.toggle("dan-hide-notices");
                
                var btn = this.querySelector(".ab-item");
                var countEl = this.querySelector(".dan-count");
                if (document.body.classList.contains("dan-hide-notices")) {
                    btn.innerHTML = countEl.outerHTML + " Mostrar Avisos";
                } else {
                    btn.innerHTML = countEl.outerHTML + " Ocultar Avisos";
                }
            });
        });
    </script>';
}
