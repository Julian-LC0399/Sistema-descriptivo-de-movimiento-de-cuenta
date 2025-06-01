                <!-- Fin del contenido específico de la página -->
            </main>
        </div>
    </div>

    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personalizados -->
    <script>
        // Activar tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Confirmación para acciones importantes
            document.querySelectorAll('.confirm-action').forEach(element => {
                element.addEventListener('click', function(e) {
                    if (!confirm('¿Está seguro que desea realizar esta acción?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Auto-ocultar mensajes después de 5 segundos
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        // Función para formatear números como moneda
        function formatCurrency(value) {
            return new Intl.NumberFormat('es-VE', {
                style: 'currency',
                currency: 'VES',
                minimumFractionDigits: 2
            }).format(value);
        }
    </script>
</body>
</html>