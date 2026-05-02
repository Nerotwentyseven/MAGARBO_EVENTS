document.addEventListener('DOMContentLoaded', function() {
    renderCalendar();
});

function renderCalendar() {
    const container = document.getElementById('calendar-container');
    const date = new Date();
    const month = date.toLocaleString('default', { month: 'long' });
    const year = date.getFullYear();

    // Dito mo i-inject ang HTML para sa calendar grid
    // Pwedeng gumamit ng external library gaya ng FullCalendar para sa advanced features
    container.innerHTML = `<h3>${month} ${year}</h3><div class="simple-grid">...days...</div>`;
}