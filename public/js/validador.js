var system_validarcampos = function (div_contenedor, mostrar_mensaje) {
    var Eventos = 0;
    jQuery("#" + div_contenedor + " #system_validador").remove();
    jQuery("#" + div_contenedor + " input").each(function (index) {
        var MensajeError = '';
        if (jQuery(this).hasClass('system_validador_vacio')) {
            if (jQuery(this).val() == "" || jQuery(this).val() == " ") {
                jQuery(this).addClass('input_vacio');
                MensajeError = "No debe estar vacio";
                Eventos++;
            } else {
                jQuery(this).removeClass('input_vacio');
            }
            if (jQuery(this).attr("maxlength") != null) {
                if (jQuery(this).attr("maxlength") != 0) {
                    if (jQuery(this).val().length > jQuery(this).attr("maxlength")) {
                        jQuery(this).addClass('input_vacio');
                        MensajeError = MensajeError + " - El texto es muy largo. Máximo: " + jQuery(this).attr("maxlength");
                        Eventos++;
                    } else {
                        jQuery(this).removeClass('input_vacio');
                    }
                }
            }
        }
        if (jQuery(this).hasClass('system_validador_email')) {
            var expr = /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
            if (!expr.test(jQuery(this).val())) {
                MensajeError = MensajeError + " - " + "Email inválido";
                Eventos++;
            } else {
                jQuery(this).removeClass('input_vacio');
            }
        }
        if (mostrar_mensaje == 1) { if (MensajeError != "") { jQuery(this).after('<span id="system_validador" class="text-danger small">' + MensajeError + '</span>'); } }
    });
    jQuery("#" + div_contenedor + " select").each(function (index) {
        if (jQuery(this).hasClass('system_validador_vacio')) {
            if (jQuery(this).val() == "" || jQuery(this).val() == " ") {
                if (mostrar_mensaje == 1) { jQuery(this).parent().append('<span id="system_validador" class="text-danger small">Seleccione una opción</span>'); }
                jQuery(this).addClass('input_vacio');
                Eventos++;
            } else {
                jQuery(this).removeClass('input_vacio');
            }
        }
    });
    jQuery("#" + div_contenedor + " textarea").each(function (index) {
        if (jQuery(this).hasClass('system_validador_vacio')) {
            if (jQuery(this).val() == "" || jQuery(this).val() == " ") {
                if (mostrar_mensaje == 1) { jQuery(this).parent().append('<span id="system_validador" class="text-danger small">No debe estar vacío.</span>'); }
                jQuery(this).addClass('input_vacio');
                Eventos++;
            } else {
                jQuery(this).removeClass('input_vacio');
            }
        }
    });
    return Eventos == 0;
};

jQuery("body").on("keydown", ".system_validador_numerico", function (event) {
    if (event.shiftKey) { event.preventDefault(); }
    if (event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9) { return; }
    if (event.keyCode < 95) {
        if (event.keyCode < 48 || event.keyCode > 57) { event.preventDefault(); }
    } else {
        if (event.keyCode < 96 || event.keyCode > 105) { event.preventDefault(); }
    }
});
