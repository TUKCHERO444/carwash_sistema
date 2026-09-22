<script>
    // Bootstrap de animaciones de entrada (docs/design.md §13).
    // Debe correr en el <head>, ANTES del primer paint, para no parpadear
    // contenido "visible → oculto" en el hero. El gate `anim-listo` activa el
    // CSS de entrada SOLO si el navegador soporta IntersectionObserver y el
    // usuario no pidió movimiento reducido.
    //
    // Seguridad: si el bundle JS no llegara a arrancar (p. ej. build roto),
    // se retira el gate a los 3 s y todo el contenido permanece visible.
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.classList.add('anim-listo');
        setTimeout(function () {
            if (!document.documentElement.hasAttribute('data-anim-iniciado')) {
                document.documentElement.classList.remove('anim-listo');
            }
        }, 3000);
    }
</script>