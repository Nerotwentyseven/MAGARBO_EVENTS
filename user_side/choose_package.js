const packageData = {
    classic: {
        title: "Classic Elegance",
        price: "₱35,000-45,000",
        img: "img/image.png",
        features: ["Church/Ceremony Styling", "Ceremony Entrance Design", "Aisle Decor w/ Architectural Accents", "Bride Reveal Styling", "Professional setup team", "Complete teardown service"]
    },
    modern: {
        title: "Modern Romance",
        price: "₱45,000",
        img: "img/rectangle-133.png",
        features: ["Ceremony Backdrop Design", "Luxury Seating Area for VIP", "Prestige Table Decor", "Tabletop Floral & Signage", "Cake & Couple's Floral Accents", "Elegant Treatment Ceiling", "Dramatic Entranceway & Signage"]
    }
    // Idagdag ang Grand dito...
};

function openModal(type) {
    const data = packageData[type];
    const modal = document.getElementById('pkgModal');
    const container = document.getElementById('modal-data');

    let featuresList = data.features.map(f => `<li><i class="fa-solid fa-check"></i> ${f}</li>`).join('');

    container.innerHTML = `
        <div class="modal-body" style="padding:20px;">
            <h2 style="font-size:24px;">${data.title}</h2>
            <p style="color:#666; font-size:14px; margin-bottom:15px;">Timeless and sophisticated styling...</p>
            <img src="${data.img}" style="width:100%; border-radius:10px;">
            <div style="background:#eee; padding:15px; border-radius:10px; margin:15px 0;">
                <span style="font-size:12px; color:#999;">Starting from</span>
                <h3 style="color:#BF9225; margin:0;">${data.price}</h3>
            </div>
            <div class="info-box" style="background:#FFF5E6; border:1px solid #FFD1B3; padding:15px; border-radius:10px; font-size:12px;">
                <p style="color:#D9534F;"><i class="fa-solid fa-circle-exclamation"></i> <strong>Important Booking Info:</strong></p>
                <ul style="list-style:none; padding:0; margin-top:10px;">
                    <li>• ₱2,000 Down Payment Required</li>
                    <li>• No Refund Policy</li>
                </ul>
            </div>
            <h4 style="margin:20px 0 10px;">Package Inclusions</h4>
            <ul class="features-list" style="list-style:none; padding:0;">${featuresList}</ul>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:20px;">
                <button onclick="closeModal()" style="padding:12px; border-radius:10px; border:none; background:#BF9225; color:#fff;">Close</button>
                <button onclick="selectPkg('${data.title}'); closeModal();" style="padding:12px; border-radius:10px; border:none; background:#BF9225; color:#fff;">Select Package</button>
            </div>
        </div>
    `;
    modal.style.display = "block";
}

function closeModal() {
    document.getElementById('pkgModal').style.display = "none";
}

function selectPkg(name) {
    alert("You selected: " + name);
    document.getElementById('nextBtn').disabled = false;
    document.getElementById('nextBtn').style.opacity = "1";
    // I-save sa session or localstorage kung kailangan
}

// Isara ang modal kapag clinick sa labas
window.onclick = function(event) {
    if (event.target == document.getElementById('pkgModal')) closeModal();
}