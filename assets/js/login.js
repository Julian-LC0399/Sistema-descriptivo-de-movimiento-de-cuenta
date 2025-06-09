// Mostrar fecha actual con animación
function updateDate() {
    const months = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];
    const now = new Date();
    const day = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();
    const dateStr = `Mantes, ${day} de ${month} del ${year}`;
    document.getElementById('current-date').textContent = dateStr;
}

// Actualizar cada segundo para sincronización precisa
function startTicker() {
    updateDate();
    setInterval(updateDate, 60000); // Actualizar cada minuto
}

document.addEventListener('DOMContentLoaded', startTicker);