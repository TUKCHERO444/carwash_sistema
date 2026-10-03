/**
 * Mejora progresiva del carrito: envía los forms por fetch (JSON),
 * actualiza el contador del header y muestra un toast.
 * Sin JS los forms funcionan con redirect normal.
 */
document.addEventListener('submit', async (evento) => {
    const form = evento.target.closest('form[data-carrito]');

    if (! form) {
        return;
    }

    evento.preventDefault();

    const toast = document.querySelector('[data-carrito-toast]');
    const mostrar = (texto, ok) => {
        if (! toast) {
            return;
        }
        toast.textContent = texto;
        toast.classList.remove('hidden');
    };

    let respuesta;
    try {
        respuesta = await fetch(form.action, {
            method: form.method || 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value ?? '',
            },
            body: new FormData(form),
        });
    } catch {
        form.submit();
        return;
    }

    let datos = null;
    try {
        datos = await respuesta.json();
    } catch {
        form.submit();
        return;
    }

    if (! respuesta.ok || ! datos?.ok) {
        const primero = datos?.errors ? Object.values(datos.errors).flat()[0] : null;
        mostrar(primero ?? 'No se pudo actualizar el carrito.', false);
        return;
    }

    document.querySelectorAll('[data-carrito-conteo]').forEach((nodo) => {
        nodo.textContent = String(datos.conteo ?? 0);
    });
    mostrar(datos.mensaje ?? 'Carrito actualizado.', true);

    if (form.action.includes('/carrito/items/') || form.action.endsWith('/carrito')) {
        window.location.reload();
    }
});
