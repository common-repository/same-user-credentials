// nel file admin/script.js

jQuery(document).ready(function($) {
    // Mostra le impostazioni corrispondenti quando un radio button viene selezionato
    $('.sucw-option input[type="radio"]').change(function() {
        if ($('#sucw_server').is(':checked')) {
            $('#server-settings').show();
            $('#client-settings').hide();
        } else if ($('#sucw_client').is(':checked')) {
            $('#client-settings').show();
            $('#server-settings').hide();
        }
    });

    // Mostra le impostazioni corrispondenti al caricamento della pagina
    if ($('#sucw_server').is(':checked')) {
        $('#server-settings').show();
    } else if ($('#sucw_client').is(':checked')) {
        $('#client-settings').show();
    } else {
        // Nascondi entrambi i div delle impostazioni se nessun radio button è selezionato
        $('#server-settings').hide();
        $('#client-settings').hide();
    }
    // Aggiunge al bottone sucw_ask_connection un evento click per chiamare il server per il permesso di connettersi
    $('#sucw_ask_connection').click(function() {
        ask_connection_to_server();
    });
});

function sucw_submit_form() {
    if (jQuery('#sucw_server').is(':checked')) {
    } else if (jQuery('#sucw_client').is(':checked')) {
    } else {
        alert('Choose whether the site is a server or a client.');
        return;
    }
    jQuery('#submit').parent().append('Saving...');
    jQuery('#submit').css('display', 'none');
    jQuery('#sucwSubmitForm').submit();
}


function SUCW_isValidURL(url) {
    try {
        const fccUrl = new URL(url);
        return fccUrl.protocol === "http:" || fccUrl.protocol === "https:";
    } catch (e) {
        return false;
    }
}