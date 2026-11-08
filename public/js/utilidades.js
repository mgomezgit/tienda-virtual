function getDataJson(obj) {
    return jQuery("#" + obj).serializeObject();
}

function Mostrarloader() {
    jQuery("#loader_proceso").css("display", "flex");
}

function Ocultarloader() {
    jQuery("#loader_proceso").css("display", "none");
}

/**
 * Escapa texto para insertarlo en HTML armado a mano.
 *
 * ÚNICA función de escape del proyecto: todo texto que escribe una persona
 * (un cliente desde la página pública, un admin, una fila de un Excel
 * importado) y que se concatena dentro de un string de HTML tiene que pasar
 * por aquí. Si el destino es un nodo concreto, mejor todavía .text().
 *
 * No hace falta en: .text(), .val(), .attr(nombre, valor), textContent, la
 * opción "text" de SweetAlert2 (ahí escapar mostraría entidades literales).
 * SÍ hace falta en: .html(), .append()/.prepend() con strings, los render de
 * DataTables, las opciones "title" y "html" de SweetAlert2 (las dos
 * interpretan HTML), y dentro del valor de un atributo armado a mano
 * (data-*="...", title="...", value="...").
 *
 * Escapa también " y ': antes era jQuery("<div>").text(x).html(), que no
 * toca las comillas, y un valor como  " onmouseover="alert(1)  se salía de
 * data-nombre="..." en el panel del super admin.
 *
 * OJO: .data()/.attr() DECODIFICAN las entidades al leer un atributo. Un
 * valor leído así vuelve a ser texto crudo: si se reinserta como HTML, se
 * escapa otra vez en ese punto.
 */
function escaparTexto(texto) {
    if (texto === null || texto === undefined) {
        return "";
    }

    return String(texto)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/**
 * Render seguro para columnas de DataTables que muestran texto.
 *
 * DataTables 1.13 mete el valor de la columna como innerHTML: sin esto, un
 * cliente que agende desde la página pública con el nombre
 * "<img src=x onerror=alert(1)>" ejecutaría código en la sesión del admin
 * al abrir la tabla. Solo se escapa lo que se muestra ("display"); el orden
 * y la búsqueda siguen trabajando sobre el valor original.
 */
function renderTextoSeguro(data, tipo) {
    return tipo === "display" ? escaparTexto(data) : data;
}

/**
 * Avisos tipo toast. notificarUsuario() es la puerta de entrada: mantiene su
 * firma de siempre, pero ya no abre SweetAlert (que se reserva para las
 * confirmaciones y decisiones).
 *
 * SEGURIDAD: el titulo y el mensaje se insertan SIEMPRE con textContent, nunca
 * como marcado. Por eso a notificarUsuario() no se le pasa ni HTML ni texto ya
 * escapado: un "&amp;" escapado de antemano se vería literal.
 */
var TOAST_DURACION_MS = 5000;
var TOAST_DURACION_ERROR_MS = 8000;
var TOAST_MAXIMO = 4;
var TOAST_SALIDA_MS = 180;

var TOAST_TIPOS = {
    exito: { titulo: "Listo", trazo: "M5 12.5l4.2 4.2L19 7" },
    aviso: { titulo: "Atención", trazo: "M12 6.5v7M12 17.5h.01" },
    error: { titulo: "No se pudo completar", trazo: "M7 7l10 10M17 7L7 17" },
    info: { titulo: "Información", trazo: "M12 11v6M12 7.2h.01" }
};

// Tipos que ya usaban los llamadores (los de SweetAlert) -> tipo de toast.
var TOAST_EQUIVALENCIAS = { success: "exito", warning: "aviso", error: "error", info: "info" };

var toastsActivos = [];

function toastReducirMovimiento() {
    return !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
}

function toastObtenerContenedor() {
    var contenedor = document.getElementById("contenedor-toasts");

    if (!contenedor) {
        contenedor = document.createElement("div");
        contenedor.id = "contenedor-toasts";
        contenedor.className = "toast-app-pila";
        contenedor.setAttribute("aria-live", "polite");
        document.body.appendChild(contenedor);
    }

    // Debajo de la barra superior flotante (sin taparla ni a sus campanas).
    // (#navbar-landing: la barra fija de la landing pública.)
    var barra = document.querySelector("#topbar, #navbar-landing");
    var arriba = 16;

    if (barra) {
        arriba = Math.max(16, Math.round(barra.getBoundingClientRect().bottom) + 12);
    }

    contenedor.style.top = arriba + "px";

    return contenedor;
}

function toastCrearSvg(ancho, trazo, grosor) {
    var ns = "http://www.w3.org/2000/svg";
    var svg = document.createElementNS(ns, "svg");
    var camino = document.createElementNS(ns, "path");

    svg.setAttribute("viewBox", "0 0 24 24");
    svg.setAttribute("width", ancho);
    svg.setAttribute("height", ancho);
    svg.setAttribute("aria-hidden", "true");
    camino.setAttribute("d", trazo);
    camino.setAttribute("fill", "none");
    camino.setAttribute("stroke", "currentColor");
    camino.setAttribute("stroke-width", grosor);
    camino.setAttribute("stroke-linecap", "round");
    camino.setAttribute("stroke-linejoin", "round");
    svg.appendChild(camino);

    return svg;
}

function toastArmarCuerpo(tipo, titulo, mensaje) {
    var elemento = document.createElement("div");
    elemento.className = "toast-app toast-app-" + tipo;
    elemento.setAttribute("role", tipo === "error" ? "alert" : "status");

    var icono = document.createElement("span");
    icono.className = "toast-app-icono";
    icono.appendChild(toastCrearSvg("18", TOAST_TIPOS[tipo].trazo, "2.4"));

    var texto = document.createElement("div");
    texto.className = "toast-app-texto";

    var tituloEl = document.createElement("p");
    tituloEl.className = "toast-app-titulo";
    tituloEl.textContent = titulo;
    texto.appendChild(tituloEl);

    if (mensaje !== "") {
        var mensajeEl = document.createElement("p");
        mensajeEl.className = "toast-app-mensaje";
        mensajeEl.textContent = mensaje;
        texto.appendChild(mensajeEl);
    }

    var cerrar = document.createElement("button");
    cerrar.type = "button";
    cerrar.className = "toast-app-cerrar";
    cerrar.setAttribute("aria-label", "Cerrar aviso");
    cerrar.appendChild(toastCrearSvg("13", "M6 6l12 12M18 6L6 18", "2.2"));

    var barra = document.createElement("span");
    barra.className = "toast-app-progreso";

    elemento.appendChild(icono);
    elemento.appendChild(texto);
    elemento.appendChild(cerrar);
    elemento.appendChild(barra);

    return { elemento: elemento, cerrar: cerrar, barra: barra };
}

function toastIniciarBarra(toast) {
    toast.barra.style.animation = "none";
    // Fuerza el recálculo para que la animación arranque desde cero.
    void toast.barra.offsetWidth;
    toast.barra.style.animation = "";
    toast.barra.style.animationDuration = toast.duracion + "ms";
    toast.barra.style.animationPlayState = toast.pausado ? "paused" : "running";
}

function toastProgramarCierre(toast) {
    clearTimeout(toast.temporizador);
    toast.inicio = Date.now();
    toast.temporizador = setTimeout(function () {
        toastCerrar(toast);
    }, toast.restante);
}

function toastPausar(toast) {
    if (toast.pausado || toast.cerrado) {
        return;
    }

    toast.pausado = true;
    clearTimeout(toast.temporizador);
    toast.restante = Math.max(0, toast.restante - (Date.now() - toast.inicio));
    toast.barra.style.animationPlayState = "paused";
}

function toastReanudar(toast) {
    if (!toast.pausado || toast.cerrado) {
        return;
    }

    toast.pausado = false;
    toast.barra.style.animationPlayState = "running";
    toastProgramarCierre(toast);
}

function toastCerrar(toast) {
    if (toast.cerrado) {
        return;
    }

    toast.cerrado = true;
    clearTimeout(toast.temporizador);
    toastsActivos = toastsActivos.filter(function (otro) {
        return otro !== toast;
    });

    var retirar = function () {
        if (toast.elemento.parentNode) {
            toast.elemento.parentNode.removeChild(toast.elemento);
        }

        if (typeof toast.alCerrar === "function") {
            toast.alCerrar();
        }
    };

    if (toastReducirMovimiento()) {
        retirar();
    } else {
        toast.elemento.classList.add("toast-app-saliendo");
        setTimeout(retirar, TOAST_SALIDA_MS);
    }
}

/**
 * Muestra un toast. Devuelve { cerrar() }, no una promesa.
 *
 * tipo: exito | aviso | error | info. mensaje: texto plano (un arreglo se
 * muestra como lista de líneas). opciones.alCerrar: función que se ejecuta
 * cuando el toast se cierra (por tiempo, por el botón o al ser reemplazado).
 */
function mostrarToast(tipo, mensaje, opciones) {
    opciones = opciones || {};

    if (!Object.prototype.hasOwnProperty.call(TOAST_TIPOS, tipo)) {
        tipo = "info";
    }

    var texto;

    if (Array.isArray(mensaje)) {
        texto = mensaje.map(function (linea) {
            return "- " + linea;
        }).join("\n");
    } else {
        texto = mensaje === null || mensaje === undefined ? "" : String(mensaje);
    }

    var contenedor = toastObtenerContenedor();
    var duracion = tipo === "error" ? TOAST_DURACION_ERROR_MS : TOAST_DURACION_MS;

    // Un aviso idéntico (mismo tipo y mismo texto) que ya está en pantalla no
    // se apila: se reinicia su tiempo.
    var repetido = toastsActivos.filter(function (toast) {
        return toast.tipo === tipo && toast.texto === texto;
    })[0];

    if (repetido) {
        repetido.restante = repetido.duracion;
        toastIniciarBarra(repetido);

        if (!repetido.pausado) {
            toastProgramarCierre(repetido);
        }

        if (typeof opciones.alCerrar === "function") {
            var anterior = repetido.alCerrar;
            repetido.alCerrar = function () {
                if (typeof anterior === "function") {
                    anterior();
                }
                opciones.alCerrar();
            };
        }

        return { cerrar: function () { toastCerrar(repetido); } };
    }

    // Máximo 4 a la vez: el nuevo reemplaza al más antiguo.
    while (toastsActivos.length >= TOAST_MAXIMO) {
        toastCerrar(toastsActivos[0]);
    }

    var cuerpo = toastArmarCuerpo(tipo, TOAST_TIPOS[tipo].titulo, texto);
    var toast = {
        tipo: tipo,
        texto: texto,
        duracion: duracion,
        restante: duracion,
        inicio: 0,
        pausado: false,
        cerrado: false,
        temporizador: null,
        elemento: cuerpo.elemento,
        barra: cuerpo.barra,
        alCerrar: opciones.alCerrar
    };

    cuerpo.cerrar.addEventListener("click", function () {
        toastCerrar(toast);
    });
    toast.elemento.addEventListener("mouseenter", function () {
        toastPausar(toast);
    });
    toast.elemento.addEventListener("mouseleave", function () {
        if (!toast.elemento.contains(document.activeElement)) {
            toastReanudar(toast);
        }
    });
    toast.elemento.addEventListener("focusin", function () {
        toastPausar(toast);
    });
    toast.elemento.addEventListener("focusout", function () {
        if (!toast.elemento.matches(":hover")) {
            toastReanudar(toast);
        }
    });

    toastsActivos.push(toast);
    contenedor.appendChild(toast.elemento);
    toastIniciarBarra(toast);
    toastProgramarCierre(toast);

    return { cerrar: function () { toastCerrar(toast); } };
}

/**
 * Firma de siempre: (Mensaje, icono, urlRedireccion). Los valores de icono son
 * los de SweetAlert que ya usaban los llamadores: success, warning, error e
 * info (cualquier otro se muestra como info). Con urlRedireccion, la
 * navegación ("reload" recarga) ocurre cuando el toast se cierra.
 *
 * Solo texto plano: nunca HTML ni texto ya escapado (se vería literal).
 * No devuelve promesa: devuelve { cerrar() }.
 */
function notificarUsuario(Mensaje = "", icono = "info", urlRedireccion = "") {
    var tipo = Object.prototype.hasOwnProperty.call(TOAST_EQUIVALENCIAS, icono) ? TOAST_EQUIVALENCIAS[icono] : "info";

    return mostrarToast(tipo, Mensaje, {
        alCerrar: function () {
            if (urlRedireccion === "reload") {
                window.location.reload();
            } else if (urlRedireccion !== "") {
                location.href = UrlGlobal + urlRedireccion;
            }
        }
    });
}

const axiosSipleInterno = async (metodo = "GET", url, parametros = {}, cuerpo = {}, MostrarLoader = false, CallBack = undefined, extraOptions = {}) => {
    // "silenciarError" no es una opción de axios: se separa antes de pasarle el resto.
    // Sirve para peticiones de fondo que el usuario no pidió (por ejemplo un sondeo
    // periódico), donde avisar del fallo una y otra vez molestaría más que ayudar.
    // Por defecto va en false, así que las llamadas de siempre no cambian.
    const { silenciarError = false, ...opcionesAxios } = extraOptions;

    const metodoFormato = metodo.toLocaleLowerCase();
    const options = {
        url: UrlGlobal + url,
        method: metodoFormato,
        params: parametros,
        data: cuerpo
    };
    try {
        MostrarLoader ? Mostrarloader() : null;
        const respuesta = await axios({...options, ...opcionesAxios});
        MostrarLoader ? Ocultarloader() : null;
        CallBack !== undefined ? CallBack(respuesta.data) : null;
        return respuesta.data;
    } catch (error) {
        MostrarLoader ? Ocultarloader() : null;

        if (silenciarError) {
            return false;
        }

        var mensajeFalla = 'No pudimos comunicarnos con el servidor. Revisa tu conexión a internet e inténtalo de nuevo. Si el problema continúa, comunícate con soporte e indica este código: CONEXION';

        if (error && error.response && error.response.status) {
            mensajeFalla = 'Ocurrió un problema técnico y la acción no se completó. Vuelve a intentarlo en unos segundos. Si el problema continúa, comunícate con soporte e indica este código: HTTP-' + error.response.status;
        }

        notificarUsuario(mensajeFalla, 'error');
        return false;
    }
};

jQuery.fn.serializeObject = function () {
    var obj = {};
    jQuery.each(this.serializeArray(), function () {
        obj[this.name] = this.value;
    });
    return obj;
};
