/**
 * Módulo: reparto.js
 * Responsabilidad: reparte una lista de categorías en el mosaico 2-1-2 del
 * tema de referencia (columna izquierda de 2 tiles, centro de 1 tile y
 * columna derecha de 2 tiles, por grupos de 5).
 * - Posición dentro del grupo (índice % 5): 0 y 1 → izquierda; 2 → centro;
 *   3 y 4 → derecha.
 * - Función pura: no muta la entrada y conserva el multiset al aplanar.
 */

/**
 * Reparte los elementos en columnas con patrón 2-1-2 por grupos de 5.
 * @param {Array} items - Lista de elementos a repartir.
 * @returns {{izquierda: Array, centro: Array, derecha: Array}}
 */
export function repartir(items) {
    const izquierda = [];
    const centro = [];
    const derecha = [];
    const grupo = 5;

    [...items].forEach((item, i) => {
        const posicion = i % grupo;

        if (posicion === 0 || posicion === 1) {
            izquierda.push(item);
        } else if (posicion === 2) {
            centro.push(item);
        } else {
            derecha.push(item);
        }
    });

    return { izquierda, centro, derecha };
}