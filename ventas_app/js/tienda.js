// Tienda: ventanita "¿Quieres ir al carrito?" y confirmación de "Vaciar carrito".

// Cierra la ventanita de abajo a la derecha (botón ×). Se oculta sola a los 8 segundos.
var avisoCarrito = document.getElementById('aviso-carrito');
if (avisoCarrito) {
    var cerrarAviso = function () {
        avisoCarrito.classList.add('aviso-carrito-saliendo');
        setTimeout(function () { avisoCarrito.remove(); }, 200);
    };
    avisoCarrito.querySelector('[data-cerrar-aviso]').addEventListener('click', cerrarAviso);
    setTimeout(cerrarAviso, 8000);
}

// Botones que abren una ventana de confirmación (<dialog>) en el centro de la página.
document.querySelectorAll('[data-abrir-dialogo]').forEach(function (boton) {
    var dialogo = document.getElementById(boton.dataset.abrirDialogo);
    if (!dialogo) {
        return;
    }
    boton.addEventListener('click', function () {
        dialogo.showModal();
    });
    // Clic en el fondo oscuro = "No".
    dialogo.addEventListener('click', function (evento) {
        if (evento.target === dialogo) {
            dialogo.close();
        }
    });
});
