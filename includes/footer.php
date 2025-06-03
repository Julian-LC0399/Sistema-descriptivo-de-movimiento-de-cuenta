<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-<HASH>" 
        crossorigin="anonymous"></script>

<!-- Scripts personalizados mejorados -->
<script>
    // Patrón módulo para evitar contaminación del espacio global
    (function() {
        'use strict';
        
        /**
         * Inicializa los tooltips de Bootstrap
         */
        function initTooltips() {
            const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus',
                    animation: true
                });
            });
        }
        
        /**
         * Agrega confirmación a acciones importantes
         */
        function setupConfirmations() {
            document.querySelectorAll('.confirm-action').forEach(element => {
                element.addEventListener('click', function(e) {
                    const confirmationMessage = this.dataset.confirmMessage || 
                                              '¿Está seguro que desea realizar esta acción?';
                    
                    if (!confirm(confirmationMessage)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            });
        }
        
        /**
         * Auto-cierra alertas después de un tiempo
         */
        function setupAutoDismissAlerts() {
            const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
            alerts.forEach(alert => {
                const delay = parseInt(alert.dataset.autoDismiss) || 5000;
                
                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }, delay);
            });
        }
        
        /**
         * Formatea números como moneda
         * @param {number} value - Valor a formatear
         * @param {string} [currency='VES'] - Código de moneda
         * @param {string} [locale='es-VE'] - Configuración regional
         * @returns {string} Valor formateado como moneda
         */
        function formatCurrency(value, currency = 'VES', locale = 'es-VE') {
            if (isNaN(value)) {
                console.warn('El valor proporcionado no es un número:', value);
                return '--';
            }
            
            try {
                return new Intl.NumberFormat(locale, {
                    style: 'currency',
                    currency: currency,
                    minimumFractionDigits: 2
                }).format(value);
            } catch (error) {
                console.error('Error al formatear moneda:', error);
                return value.toFixed(2);
            }
        }
        
        /**
         * Maneja errores no capturados
         */
        function setupErrorHandling() {
            window.addEventListener('error', (event) => {
                console.error('Error no capturado:', event.error);
                // Aquí podrías mostrar un mensaje al usuario o enviar el error a un servicio de tracking
            });
        }
        
        // Inicialización cuando el DOM está listo
        document.addEventListener('DOMContentLoaded', function() {
            initTooltips();
            setupConfirmations();
            setupAutoDismissAlerts();
            setupErrorHandling();
            
            // Hacer disponible formatCurrency de manera controlada
            window.App = window.App || {};
            window.App.utils = {
                formatCurrency: formatCurrency
            };
        });
        
    })();
</script>
</body>
</html>    