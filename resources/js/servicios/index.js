/**
 * Módulo: servicios/index.js
 * Responsabilidad: Inicializa el toggle de estado activo/inactivo
 * del listado de servicios (módulo compartido toggle-status.js).
 */

import { initToggleStatus } from '../toggle-status';

document.addEventListener('DOMContentLoaded', () => initToggleStatus());