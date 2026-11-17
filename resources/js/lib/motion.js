// Preferencia del sistema para reducir movimiento. Se consulta al cargar la vista.
export function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}
