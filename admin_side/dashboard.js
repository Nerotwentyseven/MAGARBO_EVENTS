document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});

function renderCalendar() {
    const container = document.getElementById('calendar-container');
    const date = new Date();
    const month = date.toLocaleString('default', { month: 'long' });
    const year = date.getFullYear();
    container.innerHTML = `<h3>${month} ${year}</h3><div class="simple-grid">...days...</div>`;
}