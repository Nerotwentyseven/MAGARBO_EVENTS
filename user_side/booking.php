<?php
session_name('USERSESSID');
session_start();
require_once 'user_auth.php';
require_once '../db_connection.php';

$serviceTypeFromSession = $_SESSION['service_type'] ?? '';
$isCateringOnly = (trim(strtolower($serviceTypeFromSession)) === 'catering only');

// KUNIN ANG MGA APPROVED DATES MULA SA DATABASE
$booked_dates = [];

$query = "SELECT event_date, COUNT(*) as count
          FROM bookings
          WHERE booking_status = 'Approved'
          GROUP BY event_date";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $booked_dates[$row['event_date']] = (int)$row['count'];
    }
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/
function normalizeCategoryKey($text) {
    $text = strtolower(trim((string)$text));

    if ($text === '') return '';

    $map = [
        'wedding' => ['wedding', 'kasal', 'bridal'],
        'birthday' => ['birthday', 'debut', '18th birthday', '7th birthday', 'kids party', 'kiddie'],
        'christening' => ['christening', 'baptism', 'binyag', 'binyagan'],
        'anniversary' => ['anniversary'],
        'catering' => ['catering', 'food service'],
        'corporate' => ['corporate', 'company', 'business', 'seminar', 'conference'],
    ];

    foreach ($map as $canonical => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return $canonical;
            }
        }
    }

    return $text;
}

function cleanSuggestionText($text, $eventType = '') {
    $text = trim((string)$text);

    if ($text === '') return '';

    $text = strip_tags($text);
    $text = preg_replace('/\r\n|\r/', "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{2,}/', "\n", $text);
    $text = trim($text);

    if ($text === '' || mb_strlen($text) < 5) {
        return '';
    }

    return $text;
}

function getRelatedCategories($selected) {
    $selected = normalizeCategoryKey($selected);
    return [$selected];
}

//THEME SUGGESTIONS FROM gallery_themes
$offersByCategory = [];

$themeSql = "
    SELECT 
        gt.id,
        tc.category_name AS category,
        gt.theme_name,
        gt.theme_photo,
        gt.theme_details,
        gt.pick_count,
        gt.is_active
    FROM gallery_themes gt
    INNER JOIN theme_categories tc ON gt.category_id = tc.id
    WHERE gt.is_active = 1
    ORDER BY gt.pick_count DESC, gt.created_at DESC, gt.id DESC
";
$themeRes = mysqli_query($conn, $themeSql);

if ($themeRes) {
    while ($row = mysqli_fetch_assoc($themeRes)) {
        $categoryKey = normalizeCategoryKey($row['category'] ?? '');
        if ($categoryKey === '' || $categoryKey === 'catering') continue;

        if (!isset($offersByCategory[$categoryKey])) {
            $offersByCategory[$categoryKey] = [];
        }

        $offersByCategory[$categoryKey][] = [
            'id' => (int) ($row['id'] ?? 0),
            'img' => !empty($row['theme_photo']) ? '../' . ltrim($row['theme_photo'], '/') : '',
            'name' => trim((string)($row['theme_name'] ?? '')),
            'theme' => trim((string)($row['theme_name'] ?? '')),
            'details' => trim((string)($row['theme_details'] ?? '')),
            'count' => (int) ($row['pick_count'] ?? 0)
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Magarbo Events | Professional Reservation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="booking.css?v=<?php echo time(); ?>" />
</head>
<body>
    <div class="book-details-wrapper">
        <div class="nav-header">
            <div class="back-home" onclick="location.href='index.php'">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Home</span>
            </div>
        </div>

        <div class="header-section">
            <h1 class="main-title">Magarbo Events Reservation</h1>
            <p class="sub-title">Please provide your event specifications to secure your booking.</p>
        </div>

        <div class="stepper-container">
            <div class="step-circle active">1</div>
            <div class="step-circle active">2</div>
            <div class="step-circle active">3</div>
            <div class="step-circle inactive">4</div>
        </div>

        <div class="main-booking-card">
            <form id="bookingForm" action="process.php" method="POST" onsubmit="return validateBookingForm()">
                <div class="form-section-label">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Event Details</span>
                </div>

                <div class="form-grid">
                    <div class="field-group">
                        <label>Event Category</label>
                        <input type="text" name="event_type" id="categoryInput" list="categoryOptions" placeholder="Select or type category" oninput="handleCategoryInput(this.value)" required>
                        <datalist id="categoryOptions">
                            <option value="Anniversary">
                            <option value="Birthday">
                            <option value="Catering">
                            <option value="Christening">
                            <option value="Corporate">
                            <option value="Wedding">
                        </datalist>
                    </div>

                    <div class="field-group">
                        <label>Preferred Time</label>
                        <input type="time" name="event_time" required />
                    </div>

                    <div class="field-group">
                        <label>Event Date</label>
                        <input type="date" name="event_date" id="eventDate" required min="<?php echo date('Y-m-d'); ?>" />
                    </div>

                    <div class="field-group full-width">
                        <label>Venue Location</label>

                        <input type="hidden" name="venue" id="venueInput" required>

                        <div class="location-grid shop-address-grid">
                            <div class="address-card">
                                <label>Province</label>
                                <div class="custom-dropdown" id="provinceDropdownWrap">
                                    <button type="button" class="custom-dropdown-trigger" id="provinceTrigger" onclick="toggleCustomDropdown('province')">
                                        <span id="provinceTriggerText">Select Province</span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    <div class="custom-dropdown-panel" id="provincePanel">
                                        <input type="text" class="custom-dropdown-search" id="provinceSearch" placeholder="Search province..." oninput="filterCustomOptions('province')">
                                        <div class="custom-dropdown-options" id="provinceOptions"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="provinceSelect" value="">
                            </div>

                            <div class="address-card">
                                <label>City / Municipality</label>
                                <div class="custom-dropdown disabled" id="municipalityDropdownWrap">
                                    <button type="button" class="custom-dropdown-trigger" id="municipalityTrigger" onclick="toggleCustomDropdown('municipality')">
                                        <span id="municipalityTriggerText">Select City / Municipality</span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    <div class="custom-dropdown-panel" id="municipalityPanel">
                                        <input type="text" class="custom-dropdown-search" id="municipalitySearch" placeholder="Search city / municipality..." oninput="filterCustomOptions('municipality')">
                                        <div class="custom-dropdown-options" id="municipalityOptions"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="municipalitySelect" value="">
                            </div>

                            <div class="address-card">
                                <label>Barangay</label>
                                <div class="custom-dropdown disabled" id="barangayDropdownWrap">
                                    <button type="button" class="custom-dropdown-trigger" id="barangayTrigger" onclick="toggleCustomDropdown('barangay')">
                                        <span id="barangayTriggerText">Select Barangay</span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    <div class="custom-dropdown-panel" id="barangayPanel">
                                        <input type="text" class="custom-dropdown-search" id="barangaySearch" placeholder="Search barangay..." oninput="filterCustomOptions('barangay')">
                                        <div class="custom-dropdown-options" id="barangayOptions"></div>
                                    </div>
                                </div>
                                <input type="hidden" id="barangaySelect" value="">
                            </div>

                            <div class="address-card full-span">
                                <label for="streetInput">House No. / Street / Purok / Landmark</label>
                                <input type="text" id="streetInput" placeholder="Enter exact event location">
                                <small class="input-hint">Include landmarks like church, hall, or nearby establishment.</small>
                            </div>
                        </div>

                        <small id="venuePreview" class="venue-preview-text">
                            Complete your address details.
                        </small>
                    </div>
                </div>

                <div id="religionSection" class="special-section" style="display: none; margin-top: 20px;">
                    <div class="info-box">
                        <label>Religious Customization</label>
                        <input type="text" name="religion" id="religionSelect" list="religionOptions" placeholder="Select or type religion">

                        <datalist id="religionOptions">
                            <option value="Catholic">
                            <option value="Christian">
                            <option value="Iglesia ni Cristo">
                            <option value="Muslim">
                        </datalist>

                    </div>
                </div>

                <div class="field-group full-width" style="margin-top: 20px;">
                    <label>Client Requests & Special Instructions</label>
                    <textarea name="request" id="requestField" placeholder="Mention themes, colors, or specific needs..."></textarea>
                    <div id="requestLoading" class="request-loading" style="display:none;">
                        <span class="loader"></span>
                        Generating smart request...
                    </div>
                    <input type="hidden" name="selected_theme_id" id="selectedThemeIdInput" value="">
                    <input type="hidden" name="selected_theme" id="selectedThemeInput" value="">
                    <div id="selectedThemeWrapper" style="display:none; margin-top:8px; align-items:center; gap:10px; flex-wrap:wrap;">
                        <small id="selectedThemeText" style="color:#bf9225; font-weight:600;"></small>
                        <button type="button" id="clearThemeBtn" onclick="clearSelectedTheme()" style="border:none; background:#f3f3f3; color:#333; padding:6px 10px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600;">
                            Cancel Selected Theme
                        </button>
                    </div>
                </div>

                <div class="dynamic-container" id="layoutContainer">
                    <div class="suggestions-panel" id="suggestionBox" style="display: none;">
                        <div id="suggestionList" class="suggestion-items-grid"></div>
                    </div>

                    <div class="calendar-wrapper" id="calendarSection">
                        <div class="calendar-card">
                            <div class="calendar-title">Availability Calendar</div>
                            <div class="calendar-nav">
                                <button type="button" class="nav-btn" onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                                <span id="monthDisplay"></span>
                                <button type="button" class="nav-btn" onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                            <div class="calendar-weekdays">
                                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                            </div>
                            <div id="calendarGrid" class="calendar-grid"></div>
                        </div>
                    </div>

                    <div class="note-panel" id="guidelineNote">
                        <div class="panel-header">Booking Guidelines</div>
                        <p class="note-body">
                            Select an available date. Dates with two dots are fully booked.
                            Use the arrows to browse other months.
                        </p>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 30px;">
                    <button type="button" class="btn-secondary" onclick="window.history.back()">Previous</button>
                    <button type="submit" class="btn-primary">Next Step</button>
                </div>
            </form>
        </div>
    </div>

    <div id="imageModal" class="modal-overlay" onclick="closeModal()">
        <span class="modal-close"><i class="fa-solid fa-times"></i></span>
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <img class="modal-image" id="modalImage" src="" alt="Magarbo Theme Preview">
            <div class="modal-caption" id="modalCaption"></div>
            <button class="modal-apply-btn" id="modalApplyBtn">Apply this theme</button>
        </div>
    </div>


    <div id="bookingAlertModal" class="booking-alert-overlay">
        <div class="booking-alert-card">
            <div id="bookingAlertIcon" class="booking-alert-icon info-mode">
                <i class="fa-solid fa-circle-info"></i>
            </div>

            <h3 id="bookingAlertTitle">Notice</h3>
            <p id="bookingAlertMessage">Message here</p>

            <div class="booking-alert-actions">
                <button type="button" class="booking-alert-btn ok" onclick="closeBookingAlert()">OK</button>
            </div>
        </div>
    </div>

 

    <script>
        let currentDisplayDate = new Date();

        

        const bookedDates = <?php echo json_encode($booked_dates); ?>;
        const offersDB = <?php echo json_encode($offersByCategory); ?>;
        const isCateringOnly = <?php echo json_encode($isCateringOnly); ?>;

        const PSGC_PROVINCES_URL = 'https://psgc.gitlab.io/api/provinces/';
        const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';

        let provinceListData = [];
        let municipalityListData = [];
        let barangayListData = [];

        let selectedProvinceName = '';
        let selectedMunicipalityName = '';
        let selectedBarangayName = '';

        function normalizeCategoryKey(text) {
            const value = String(text || '').toLowerCase().trim();

            if (!value) return '';

            const map = {
                wedding: ['wedding', 'kasal', 'bridal'],
                birthday: ['birthday', 'debut', '18th birthday', '7th birthday', 'kids party', 'kiddie'],
                christening: ['christening', 'baptism', 'binyag', 'binyagan'],
                anniversary: ['anniversary'],
                catering: ['catering', 'food service'],
                corporate: ['corporate', 'company', 'business', 'seminar', 'conference']
            };

            for (const key in map) {
                for (const word of map[key]) {
                    if (value.includes(word)) return key;
                }
            }

            return value;
        }

        function getRelatedCategories(selected) {
            return [selected];
        }

        

        function getOffersForCategory(categoryKey) {
            const related = getRelatedCategories(categoryKey);
            let results = [];

            related.forEach(cat => {
                if (Array.isArray(offersDB[cat])) {
                    results = results.concat(offersDB[cat]);
                }
            });

            return results;
        }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const monthYear = document.getElementById('monthDisplay');
            grid.innerHTML = '';

            const year = currentDisplayDate.getFullYear();
            const month = currentDisplayDate.getMonth();

            monthYear.innerText = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(currentDisplayDate);

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                grid.innerHTML += '<div class="day-cell empty"></div>';
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const dots = bookedDates[dateStr] || 0;
                let dotsHtml = '';

                for (let i = 0; i < Math.min(dots, 2); i++) {
                    dotsHtml += '<div class="red-dot"></div>';
                }

                const isFull = dots >= 2;

                grid.innerHTML += `
                    <div class="day-cell ${isFull ? 'full' : ''}" onclick="${isFull ? "alert('Fully Booked')" : "selectDate('"+dateStr+"')" }">
                        <span>${d}</span>
                        <div class="dots-container">${dotsHtml}</div>
                    </div>
                `;
            }
        }

        function changeMonth(dir) {
            currentDisplayDate.setMonth(currentDisplayDate.getMonth() + dir);
            renderCalendar();
        }

        function selectDate(date) {
            if (bookedDates[date] && bookedDates[date] >= 2) {
                openBookingAlert(
                    'Date Unavailable',
                    'This date is already fully booked.',
                    'error'
                );
                document.getElementById('eventDate').value = "";
                return;
            }

            document.getElementById('eventDate').value = date;
        }

        document.getElementById('eventDate').addEventListener('change', function () {
            const selectedDate = this.value;

            if (bookedDates[selectedDate] && bookedDates[selectedDate] >= 2) {
                openBookingAlert(
                'Date Unavailable',
                'This date is already fully booked.',
                'error'
            );
                this.value = "";
            }
        });

        function closeAllCustomDropdowns() {
    document.querySelectorAll('.custom-dropdown').forEach(drop => {
        drop.classList.remove('open');
    });
}

function toggleCustomDropdown(type) {
    const wrap = document.getElementById(type + 'DropdownWrap');
    if (!wrap || wrap.classList.contains('disabled')) return;

    const isOpen = wrap.classList.contains('open');
    closeAllCustomDropdowns();

    if (!isOpen) {
        wrap.classList.add('open');
        const search = document.getElementById(type + 'Search');
        if (search) {
            search.value = '';
            filterCustomOptions(type);
            setTimeout(() => search.focus(), 50);
        }
    }
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.custom-dropdown')) {
        closeAllCustomDropdowns();
    }
});

function filterCustomOptions(type) {
    const searchEl = document.getElementById(type + 'Search');
    const optionsEl = document.getElementById(type + 'Options');
    const keyword = (searchEl?.value || '').toLowerCase().trim();

    const sourceMap = {
        province: provinceListData,
        municipality: municipalityListData,
        barangay: barangayListData
    };

    const data = sourceMap[type] || [];

    optionsEl.innerHTML = '';

    const filtered = data.filter(item =>
        String(item.name || '').toLowerCase().includes(keyword)
    );

    if (!filtered.length) {
        optionsEl.innerHTML = `<div class="custom-dropdown-empty">No results found</div>`;
        return;
    }

    filtered.forEach(item => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'custom-dropdown-option';
        option.textContent = item.name;

        option.onclick = function () {
            if (type === 'province') {
                document.getElementById('provinceSelect').value = item.code;
                document.getElementById('provinceTriggerText').textContent = item.name;
                selectedProvinceName = item.name;
                handleProvinceChange(item.code);
            }

            if (type === 'municipality') {
                document.getElementById('municipalitySelect').value = item.code;
                document.getElementById('municipalityTriggerText').textContent = item.name;
                selectedMunicipalityName = item.name;
                handleMunicipalityChange(item.code);
            }

            if (type === 'barangay') {
                document.getElementById('barangaySelect').value = item.name;
                document.getElementById('barangayTriggerText').textContent = item.name;
                selectedBarangayName = item.name;
                updateVenueField();
            }

            closeAllCustomDropdowns();
        };

        optionsEl.appendChild(option);
    });
}

function setDropdownDisabled(type, disabled = true) {
    const wrap = document.getElementById(type + 'DropdownWrap');
    if (!wrap) return;

    if (disabled) {
        wrap.classList.add('disabled');
        wrap.classList.remove('open');
    } else {
        wrap.classList.remove('disabled');
    }
}

function resetDropdown(type, placeholder) {
    const hidden = document.getElementById(type + 'Select');
    const triggerText = document.getElementById(type + 'TriggerText');
    const options = document.getElementById(type + 'Options');
    const search = document.getElementById(type + 'Search');

    if (hidden) hidden.value = '';
    if (triggerText) triggerText.textContent = placeholder;
    if (options) options.innerHTML = '';
    if (search) search.value = '';

    if (type === 'province') selectedProvinceName = '';
    if (type === 'municipality') selectedMunicipalityName = '';
    if (type === 'barangay') selectedBarangayName = '';
}

        async function loadProvinces() {
    resetDropdown('province', 'Loading provinces...');
    resetDropdown('municipality', 'Select City / Municipality');
    resetDropdown('barangay', 'Select Barangay');

    setDropdownDisabled('municipality', true);
    setDropdownDisabled('barangay', true);

    try {
        const res = await fetch(`${PSGC_BASE_URL}/provinces/`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        if (!Array.isArray(data)) throw new Error('Invalid provinces response');

        provinceListData = data.map(province => ({
            code: province.code,
            name: province.name
        }));

        // SORT A-Z
        provinceListData.sort((a, b) => 
            a.name.localeCompare(b.name)
        );

        resetDropdown('province', 'Select Province');
        filterCustomOptions('province');
    } catch (error) {
        document.getElementById('provinceTriggerText').textContent = 'Failed to load provinces';
        console.error('Province load error:', error);
    }
}

async function handleProvinceChange(provinceCode) {
    resetDropdown('municipality', 'Loading city / municipality...');
    resetDropdown('barangay', 'Select Barangay');
    setDropdownDisabled('municipality', true);
    setDropdownDisabled('barangay', true);

    document.getElementById('streetInput').value = '';
    selectedMunicipalityName = '';
    selectedBarangayName = '';
    municipalityListData = [];
    barangayListData = [];

    if (!provinceCode) {
        resetDropdown('municipality', 'Select City / Municipality');
        updateVenueField();
        return;
    }

    try {
        const res = await fetch(`${PSGC_BASE_URL}/provinces/${provinceCode}/cities-municipalities/`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        if (!Array.isArray(data)) throw new Error('Invalid municipality response');

        municipalityListData = data.map(item => ({
            code: item.code,
            name: item.name
        }));

        // SORT A-Z
        municipalityListData.sort((a, b) => 
            a.name.localeCompare(b.name)
        );

        resetDropdown('municipality', 'Select City / Municipality');
        setDropdownDisabled('municipality', false);
        filterCustomOptions('municipality');
    } catch (error) {
        document.getElementById('municipalityTriggerText').textContent = 'Failed to load city / municipality';
        console.error('Municipality load error:', error);
    }

    updateVenueField();
}

async function handleMunicipalityChange(municipalityCode) {
    resetDropdown('barangay', 'Loading barangays...');
    setDropdownDisabled('barangay', true);

    document.getElementById('streetInput').value = '';
    selectedBarangayName = '';
    barangayListData = [];

    if (!municipalityCode) {
        resetDropdown('barangay', 'Select Barangay');
        updateVenueField();
        return;
    }

    try {
        const res = await fetch(`${PSGC_BASE_URL}/cities-municipalities/${municipalityCode}/barangays/`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        if (!Array.isArray(data)) throw new Error('Invalid barangay response');

        barangayListData = data.map(item => ({
            name: item.name
        }));

        // SORT A-Z
        barangayListData.sort((a, b) => 
            a.name.localeCompare(b.name)
        );

        resetDropdown('barangay', 'Select Barangay');
        setDropdownDisabled('barangay', false);
        filterCustomOptions('barangay');
    } catch (error) {
        document.getElementById('barangayTriggerText').textContent = 'Failed to load barangays';
        console.error('Barangay load error:', error);
    }

    updateVenueField();
}

function updateVenueField() {
    const streetInput = document.getElementById('streetInput');
    const venueInput = document.getElementById('venueInput');
    const venuePreview = document.getElementById('venuePreview');

    const street = streetInput.value.trim();

    const finalVenue = [street, selectedBarangayName, selectedMunicipalityName, selectedProvinceName]
        .filter(Boolean)
        .join(', ');

    venueInput.value = finalVenue;
    venuePreview.textContent = finalVenue
        ? `Complete Address: ${finalVenue}`
        : 'Complete your address details.';
}

        function handleCategoryInput(val) {
            const container = document.getElementById('layoutContainer');
            const suggestBox = document.getElementById('suggestionBox');
            const suggestList = document.getElementById('suggestionList');
            const note = document.getElementById('guidelineNote');
            const religion = document.getElementById('religionSection');

            const normalized = normalizeCategoryKey(val);

            const religionSelect = document.getElementById('religionSelect');

            religion.style.display = (normalized === 'wedding') ? 'block' : 'none';

            if (religionSelect) {
                if (normalized === 'wedding') {
                    religionSelect.setAttribute('required', 'required');
                } else {
                    religionSelect.removeAttribute('required');
                    religionSelect.value = '';
                }
            }

            if (!normalized) {
                container.classList.remove('is-active');
                suggestBox.style.display = 'none';
                note.style.display = 'block';
                suggestList.innerHTML = '';
                clearSelectedTheme();
                return;
            }

            if (isCateringOnly || normalized === 'catering') {
                container.classList.remove('is-active');
                suggestBox.style.display = 'none';
                note.style.display = 'block';
                suggestList.innerHTML = '';
                clearSelectedTheme();
                return;
            }
            const dynamicOffers = getOffersForCategory(normalized);

            if (dynamicOffers.length === 0) {
                container.classList.remove('is-active');
                suggestBox.style.display = 'none';
                note.style.display = 'block';
                suggestList.innerHTML = '';
                clearSelectedTheme();
                return;
            }

            container.classList.add('is-active');
            suggestBox.style.display = 'block';
            note.style.display = 'none';

            let html = '';

            if (dynamicOffers.length > 0) {
                const popularThemes = dynamicOffers.slice(0, 3);
                const moreThemes = dynamicOffers.slice(3);

                html += `<div class="panel-header"><i class="fa-solid fa-crown"></i> Popular Themes</div>`;
                html += `<div class="offers-grid">`;

                popularThemes.forEach(offer => {
                    const safeImg = String(offer.img || '').replace(/'/g, "\\'");
                    const safeName = String(offer.name || '').replace(/'/g, "\\'");
                    const safeId = Number(offer.id || 0);

                    html += `
                        <div class="offer-card" onclick="openModal('${safeImg}', '${safeName}', ${safeId})">
                            ${offer.img ? `<img src="${offer.img}" alt="${offer.name}">` : `<div class="no-preview">No Preview</div>`}
                            <div class="offer-overlay"><span>${offer.name}</span></div>
                        </div>
                    `;
                });

                html += `</div>`;

                if (moreThemes.length > 0) {
                    html += `
                        <div class="panel-header panel-header-row" style="margin-top:20px;">
                            <span><i class="fa-solid fa-images"></i> More Themes</span>
                            <small class="scroll-guide"><i class="fa-solid fa-arrows-left-right"></i> Swipe or scroll to see more</small>
                        </div>
                    `;

                    html += `<div class="theme-scroll-row">`;

                    moreThemes.forEach(offer => {
                        const safeImg = String(offer.img || '').replace(/'/g, "\\'");
                        const safeName = String(offer.name || '').replace(/'/g, "\\'");
                        const safeId = Number(offer.id || 0);

                        html += `
                            <div class="theme-scroll-card" onclick="openModal('${safeImg}', '${safeName}', ${safeId})">
                                ${offer.img ? `<img src="${offer.img}" alt="${offer.name}">` : `<div class="no-preview">No Preview</div>`}
                                <div class="offer-overlay"><span>${offer.name}</span></div>
                            </div>
                        `;
                    });

                    html += `</div>`;
                }
            }

            

            suggestList.innerHTML = html;
        }

        function openModal(imgSrc, themeName, themeId = 0) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const modalCap = document.getElementById('modalCaption');
            const applyBtn = document.getElementById('modalApplyBtn');

            modal.style.display = "flex";
            modalImg.src = imgSrc;
            modalCap.innerText = themeName;

            applyBtn.onclick = function() {
                applyThemeFromModal(themeName, themeId);
            };

            document.body.style.overflow = "hidden";
        }

        function closeModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }

        async function applyThemeFromModal(themeName, themeId = 0) {
            const requestField = document.getElementById('requestField');
            const selectedThemeText = document.getElementById('selectedThemeText');
            const selectedThemeWrapper = document.getElementById('selectedThemeWrapper');
            const selectedThemeInput = document.getElementById('selectedThemeInput');
            const selectedThemeIdInput = document.getElementById('selectedThemeIdInput');

            const cleanTheme = String(themeName || '').trim();

            if (selectedThemeText) {
                selectedThemeText.innerText = `Selected theme: ${cleanTheme}`;
            }

            if (selectedThemeWrapper) {
                selectedThemeWrapper.style.display = 'flex';
            }

            if (selectedThemeInput) {
                selectedThemeInput.value = cleanTheme;
            }

            if (selectedThemeIdInput) {
                selectedThemeIdInput.value = themeId > 0 ? themeId : '';
            }

            const loadingEl = document.getElementById('requestLoading');

            if (requestField) {
                requestField.value = '';
                requestField.placeholder = 'Generating smart request...';
                requestField.disabled = true;
                requestField.classList.add('loading');
            }

            if (loadingEl) {
                loadingEl.style.display = 'flex';
            }

            closeModal();

            try {
                const categoryInput = document.getElementById('categoryInput');
                const eventType = categoryInput ? categoryInput.value.trim() : '';

                const formData = new FormData();
                formData.append('theme_id', themeId);
                formData.append('theme_name', cleanTheme);
                formData.append('event_type', eventType);

                const res = await fetch('theme_request_suggestion.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                console.log('AI suggestion response:', data);

                if (data.success && data.suggestion) {
                    requestField.value = data.suggestion;
                    requestField.disabled = false;
                    requestField.classList.remove('loading');

                    if (loadingEl) {
                        loadingEl.style.display = 'none';
                    }

                    requestField.setAttribute('data-selected-theme', cleanTheme);
                    requestField.placeholder = 'You can still edit or add more details here...';
                                    } else {
                        requestField.value = '';
                        requestField.disabled = false;
                        requestField.classList.remove('loading');

                        if (loadingEl) {
                            loadingEl.style.display = 'none';
                        }

                        requestField.placeholder = 'Mention themes, colors, or specific needs...';
                        openBookingAlert(
                            'Suggestion Failed',
                            data.message || 'Failed to generate request suggestion.',
                            'error'
                        );
                    }
                    } catch (error) {
                        console.error(error);

                        requestField.value = '';
                        requestField.disabled = false;
                        requestField.classList.remove('loading');

                        if (loadingEl) {
                            loadingEl.style.display = 'none';
                        }

                        requestField.placeholder = 'Mention themes, colors, or specific needs...';
                        openBookingAlert(
                            'Connection Failed',
                            'Failed to connect to the suggestion service.',
                            'error'
                        );
                    }
                }

        function clearSelectedTheme() {
            const requestField = document.getElementById('requestField');
            const selectedThemeText = document.getElementById('selectedThemeText');
            const selectedThemeWrapper = document.getElementById('selectedThemeWrapper');
            const selectedThemeInput = document.getElementById('selectedThemeInput');

            const selectedThemeIdInput = document.getElementById('selectedThemeIdInput');
            if (selectedThemeIdInput) {
                selectedThemeIdInput.value = '';
            }

            requestField.removeAttribute('data-selected-theme');
            requestField.value = '';
            requestField.placeholder = 'Mention themes, colors, or specific needs...';

            if (selectedThemeText) {
                selectedThemeText.innerText = '';
            }

            if (selectedThemeWrapper) {
                selectedThemeWrapper.style.display = 'none';
            }

            if (selectedThemeInput) {
                selectedThemeInput.value = '';
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                closeModal();
            }
        });

        function openBookingAlert(title, message, type = 'info', focusEl = null) {
            const modal = document.getElementById('bookingAlertModal');
            const icon = document.getElementById('bookingAlertIcon');
            const titleEl = document.getElementById('bookingAlertTitle');
            const messageEl = document.getElementById('bookingAlertMessage');

            if (!modal || !icon || !titleEl || !messageEl) return;

            icon.className = 'booking-alert-icon';

            let iconHtml = '<i class="fa-solid fa-circle-info"></i>';

            if (type === 'error') {
                icon.classList.add('error-mode');
                iconHtml = '<i class="fa-solid fa-xmark"></i>';
            } else if (type === 'success') {
                icon.classList.add('success-mode');
                iconHtml = '<i class="fa-solid fa-check"></i>';
            } else {
                icon.classList.add('info-mode');
                iconHtml = '<i class="fa-solid fa-circle-info"></i>';
            }

            icon.innerHTML = iconHtml;
            titleEl.innerText = title;
            messageEl.innerText = message;
            modal.style.display = 'flex';

            modal.dataset.focusTarget = focusEl && focusEl.id ? focusEl.id : '';
        }

        function closeBookingAlert() {
            const modal = document.getElementById('bookingAlertModal');
            if (!modal) return;

            const focusId = modal.dataset.focusTarget;
            modal.style.display = 'none';

            if (focusId) {
                const el = document.getElementById(focusId);
                if (el) el.focus();
            }
        }

        function syncSelectedThemeBeforeSubmit() {
            const selectedThemeInput = document.getElementById('selectedThemeInput');
            const selectedThemeIdInput = document.getElementById('selectedThemeIdInput');
            const selectedThemeText = document.getElementById('selectedThemeText');
            const requestField = document.getElementById('requestField');

            if (!selectedThemeInput || !selectedThemeIdInput) return true;

            // Kung may theme name pero walang theme id, ibig sabihin walang valid theme selection
            if (selectedThemeInput.value.trim() !== '' && selectedThemeIdInput.value.trim() === '') {
                openBookingAlert(
                'Theme Required',
                'Please reselect the theme before continuing.',
                'info'
            );
            return false;
            }

            // kung parehong meron na, okay na
            if (selectedThemeInput.value.trim() !== '' && selectedThemeIdInput.value.trim() !== '') {
                return true;
            }

            // fallback 1: kunin sa data-selected-theme
            const dataTheme = requestField?.getAttribute('data-selected-theme') || '';
            if (dataTheme.trim() !== '') {
                selectedThemeInput.value = dataTheme.trim();
            }

            // fallback 2: kunin sa text na "Selected theme: ..."
            if (selectedThemeInput.value.trim() === '') {
                const textTheme = (selectedThemeText?.innerText || '')
                    .replace('Selected theme:', '')
                    .trim();

                if (textTheme !== '') {
                    selectedThemeInput.value = textTheme;
                }
            }

            // final check: kung may theme name pero walang id, huwag pasubmit
            if (selectedThemeInput.value.trim() !== '' && selectedThemeIdInput.value.trim() === '') {
                openBookingAlert(
                'Theme Selection Incomplete',
                'Selected theme ID is missing. Please click the theme again and press "Apply this theme".',
                'error'
            );
            return false;
            }

            return true;
        }

        function validateBookingForm() {
    const themeSyncOk = syncSelectedThemeBeforeSubmit();
    if (!themeSyncOk) {
        return false;
    }

    const categoryInput = document.getElementById('categoryInput');
    const religionSection = document.getElementById('religionSection');
    const religionSelect = document.getElementById('religionSelect');
    const normalized = normalizeCategoryKey(categoryInput?.value || '');

    if (normalized === 'wedding') {
        if (!religionSelect || !religionSelect.value.trim()) {
            if (religionSection) {
                religionSection.style.display = 'block';
            }

            openBookingAlert(
                'Religion Required',
                'Please select a religion for Wedding events.',
                'info',
                religionSelect
            );
            return false;
        }
    }

    const provinceSelect = document.getElementById('provinceSelect');
    const municipalitySelect = document.getElementById('municipalitySelect');
    const barangaySelect = document.getElementById('barangaySelect');
    const streetInput = document.getElementById('streetInput');
    const venueInput = document.getElementById('venueInput');

    if (!provinceSelect.value) {
        openBookingAlert(
        'Province Required',
        'Please select a province.',
        'info',
        provinceSelect
    );
    return false;
    }

    if (!municipalitySelect.value) {
        openBookingAlert(
            'City / Municipality Required',
            'Please select a city or municipality.',
            'info',
            municipalitySelect
        );
        return false;
    }

    if (!barangaySelect.value) {
        openBookingAlert(
            'Barangay Required',
            'Please select a barangay.',
            'info',
            barangaySelect
        );
        return false;
    }

    if (!streetInput.value.trim()) {
        openBookingAlert(
            'Street Address Required',
            'Please enter the house no., street, purok, or landmark.',
            'info',
            streetInput
        );
        return false;
    }

    updateVenueField();

    if (!venueInput.value.trim()) {
        openBookingAlert(
            'Venue Incomplete',
            'Please complete the venue location.',
            'info'
        );
        return false;
    }

    return true;
}

        renderCalendar();
        loadProvinces();
        updateVenueField();
    </script>
</body>
</html>