const MENSAJE_CIERRE = 'La caja sigue abierta. Debes volver a Caja para cerrarla antes de salir.';
const VENTANA_PERMISO_MS = 1500;

let permitirSalida = false;
let temporizadorPermiso = null;
let avisoVisible = false;
let paginaDescargando = false;

export function debeAdvertirSalida(cajaAbierta, salidaPermitida) {
    return Boolean(cajaAbierta) && !salidaPermitida;
}

export function esNavegacionInterna(href, origenActual) {
    if (!href || !origenActual) {
        return false;
    }

    try {
        const url = new URL(href, origenActual);

        return ['http:', 'https:'].includes(url.protocol) && url.origin === origenActual;
    } catch {
        return false;
    }
}

export function permitirSalidaCaja() {
    permitirSalida = true;

    if (temporizadorPermiso !== null) {
        clearTimeout(temporizadorPermiso);
    }

    temporizadorPermiso = setTimeout(() => {
        permitirSalida = false;
        temporizadorPermiso = null;
    }, VENTANA_PERMISO_MS);
}

async function mostrarAvisoCajaAbierta(cajaUrl) {
    if (avisoVisible) {
        return;
    }

    avisoVisible = true;

    try {
        const { default: Swal } = await import('sweetalert2');
        const oscuro = document.documentElement.classList.contains('dark');

        await Swal.fire({
            icon: 'warning',
            title: 'Caja abierta',
            text: 'La caja sigue abierta. Debes volver a Caja para cerrarla antes de salir.',
            confirmButtonText: 'Volver a Caja',
            confirmButtonColor: '#2563eb',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: false,
            focusConfirm: true,
            background: oscuro ? '#0f172a' : '#ffffff',
            color: oscuro ? '#e2e8f0' : '#1f2937',
        });

        permitirSalidaCaja();
        window.location.assign(cajaUrl);
    } catch {
        window.alert('La caja sigue abierta. Serás redirigido a Caja para cerrarla.');
        permitirSalidaCaja();
        window.location.assign(cajaUrl);
    } finally {
        avisoVisible = false;
    }
}

if (typeof document !== 'undefined') {
    const inicializar = () => {
        const cajaAbierta = document.body?.dataset.cajaAbierta === '1';

        if (!cajaAbierta) {
            return;
        }

        const cajaUrl = document.body?.dataset.cajaUrl || '/caja';

        window.addEventListener('beforeunload', (event) => {
            if (!debeAdvertirSalida(cajaAbierta, permitirSalida)) {
                return;
            }

            event.preventDefault();
            event.returnValue = MENSAJE_CIERRE;

            setTimeout(() => {
                if (!paginaDescargando) {
                    mostrarAvisoCajaAbierta(cajaUrl);
                }
            }, 0);

            return MENSAJE_CIERRE;
        });

        window.addEventListener('pagehide', () => {
            paginaDescargando = true;
        });

        window.addEventListener('pageshow', () => {
            paginaDescargando = false;
        });

        document.addEventListener('click', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const objetivo = event.target instanceof Element ? event.target : null;
            const enlace = objetivo?.closest('a[href]');

            if (!enlace || enlace.hasAttribute('download') || enlace.closest('[data-confirm]')) {
                return;
            }

            if (enlace.target && enlace.target !== '_self') {
                return;
            }

            if (!esNavegacionInterna(enlace.href, window.location.origin)) {
                return;
            }

            permitirSalidaCaja();
        });

        document.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            const formulario = event.target;

            if (!(formulario instanceof HTMLFormElement)) {
                return;
            }

            if (formulario.target && formulario.target !== '_self') {
                return;
            }

            if (formulario.dataset.cajaGuard === 'always') {
                event.preventDefault();
                mostrarAvisoCajaAbierta(cajaUrl);

                return;
            }

            permitirSalidaCaja();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
}
