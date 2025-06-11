// Función para actualizar la fecha
function updateDate() {
    const daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const now = new Date();
    const dateStr = `Hoy es ${daysOfWeek[now.getDay()]} ${now.getDate()} de ${months[now.getMonth()]} del ${now.getFullYear()}`;
    
    const dateElement = document.getElementById('current-date');
    if (dateElement) {
        dateElement.textContent = dateStr;
    } else {
        console.error('Elemento con ID "current-date" no encontrado');
    }
}

// Iniciar cuando el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    updateDate(); // Mostrar fecha inmediatamente
    setInterval(updateDate, 60000); // Actualizar cada minuto (60000 ms)
});