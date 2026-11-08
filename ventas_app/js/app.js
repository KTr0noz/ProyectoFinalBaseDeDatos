// JavaScript de toda la página (panel del administrador y tienda).
// Todo funciona con atributos en el HTML, así el PHP no lleva código JavaScript mezclado.

// 1. Formularios con data-confirmar="¿Eliminar…?": preguntan antes de enviarse.
document.querySelectorAll('form[data-confirmar]').forEach(function (formulario) {
    formulario.addEventListener('submit', function (evento) {
        if (!confirm(formulario.dataset.confirmar)) {
            evento.preventDefault();
        }
    });
});

// 2. Botones con data-abrir-dialogo="id": abren esa ventana <dialog> en el centro
//    (por ejemplo, "¿Estás seguro que quieres vaciar el carrito?"). Clic en el fondo = cerrar.
document.querySelectorAll('[data-abrir-dialogo]').forEach(function (boton) {
    var dialogo = document.getElementById(boton.dataset.abrirDialogo);
    if (!dialogo) {
        return;
    }
    boton.addEventListener('click', function () {
        dialogo.showModal();
    });
    dialogo.addEventListener('click', function (evento) {
        if (evento.target === dialogo) {
            dialogo.close();
        }
    });
});

// 3. Ventanita "¿Quieres ir al carrito?" (abajo a la derecha): se cierra con la × o sola a los 8 s.
var avisoCarrito = document.getElementById('aviso-carrito');
if (avisoCarrito) {
    var cerrarAviso = function () {
        avisoCarrito.classList.add('aviso-carrito-saliendo');
        setTimeout(function () { avisoCarrito.remove(); }, 200);
    };
    avisoCarrito.querySelector('[data-cerrar-aviso]').addEventListener('click', cerrarAviso);
    setTimeout(cerrarAviso, 8000);
}

// 4. Nueva venta: al elegir el cliente, escribe su dirección en "Dirección de envío".
//    <select data-copiar-direccion="id_del_campo"> con <option data-direccion="...">.
document.querySelectorAll('select[data-copiar-direccion]').forEach(function (lista) {
    lista.addEventListener('change', function () {
        var opcion = lista.options[lista.selectedIndex];
        if (opcion && opcion.dataset.direccion) {
            document.getElementById(lista.dataset.copiarDireccion).value = opcion.dataset.direccion;
        }
    });
});
