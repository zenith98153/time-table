(function () {
    "use strict";

    const root = document.querySelector(".ctt-timetable-root");
    if (!root) return;

    const dateInput = document.getElementById("ctt-date-input");

    // Modal setup
    let modal = document.getElementById("ctt-modal");
    if (!modal) {
        modal = document.createElement("div");
        modal.id = "ctt-modal";
        modal.className = "ctt-modal";
        modal.hidden = true;
        modal.innerHTML = `
            <div class="ctt-modal-backdrop"></div>
            <div class="ctt-modal-box">
                <div class="ctt-modal-header">
                    <button type="button" class="ctt-modal-close" aria-label="Close">&times;</button>
                    <h2 class="ctt-modal-title"></h2>
                    <div class="ctt-modal-meta"></div>
                </div>
                <div class="ctt-modal-body">
                    <div class="ctt-modal-organizer" aria-hidden="true"></div>
                    <div class="ctt-modal-content"></div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const modalTitle   = modal.querySelector(".ctt-modal-title");
    const modalMeta    = modal.querySelector(".ctt-modal-meta");
    const modalContent = modal.querySelector(".ctt-modal-content");
    const modalClose   = modal.querySelector(".ctt-modal-close");
    const backdrop     = modal.querySelector(".ctt-modal-backdrop");
    const orgBox       = modal.querySelector(".ctt-modal-organizer");

    const fmtTime = (t) => (t ? String(t).slice(0, 5) : "");

    function toMin(hhmm) {
        const [h, m] = String(hhmm || "0:0").split(":").map((v) => parseInt(v || "0", 10));
        return (isNaN(h) ? 0 : h) * 60 + (isNaN(m) ? 0 : m);
    }
    const clamp = (n, min, max) => Math.max(min, Math.min(max, n));
    const getSlotMin       = () => parseInt(root.dataset.slot || "30", 10);
    const roundDownToSlot  = (mins, slot) => Math.floor(mins / slot) * slot;
    const roundUpToSlot    = (mins, slot) => Math.ceil(mins / slot) * slot;

    function openModal(ev) {
        modalTitle.textContent = ev.title || "";
        const org = ev.organizer || {};
        const hasImg = !!(org.img && org.img.url);
        const hasName = !!(org.name && org.name.trim());
        const hasCreds = !!(org.creds && org.creds.trim());
        const hasLinkedIn = !!(org.linkedin && org.linkedin.trim());

        if (hasImg || hasName || hasCreds || hasLinkedIn) {
            const imgHTML = hasImg ? `<img src="${org.img.url}" alt="Organizer" class="ctt-org-avatar">` : "";
            const nameHTML = hasName ? `<div class="ctt-org-name">${org.name}</div>` : "";
            const credsHTML = hasCreds ? `<div class="ctt-org-creds">${org.creds.replace(/\n/g, "<br>")}</div>` : "";
            const linkedInHTML = hasLinkedIn
                ? `<a href="${org.linkedin}" target="_blank" rel="noopener noreferrer" class="ctt-org-linkedin" aria-label="LinkedIn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                        </svg>
                    </a>`
                : "";
            orgBox.innerHTML = `<div class="ctt-org-stack">${imgHTML}<div class="ctt-org-info">${nameHTML}${credsHTML}${linkedInHTML}</div></div>`;
            orgBox.removeAttribute("aria-hidden");
        } else {
            orgBox.innerHTML = "";
            orgBox.setAttribute("aria-hidden", "true");
        }

        let meta = `${fmtTime(ev.start)} – ${fmtTime(ev.end)}`;
        if (ev.room) meta += ` • ${ev.room}`;
        modalMeta.textContent = meta;
        modalContent.innerHTML = ev.content || "";

        modal.hidden = false;
        modal.offsetHeight;
        requestAnimationFrame(() => modal.classList.add("is-open"));
        document.body.classList.add("ctt-modal-open");
    }

    function closeModal() {
        modal.classList.remove("is-open");
        setTimeout(() => {
            if (!modal.classList.contains("is-open")) {
                modal.hidden = true;
                document.body.classList.remove("ctt-modal-open");
            }
        }, 300);
    }
    modalClose.addEventListener("click", closeModal);
    backdrop.addEventListener("click", closeModal);
    document.addEventListener("keyup", (e) => { if (e.key === "Escape" && !modal.hidden) closeModal(); });

    // Mock fetch functions for demo
    async function fetchDates() {
        return ["2026-05-12", "2026-05-13"];
    }

    async function fetchEvents(date) {
        // Return mock data based on date
        return MOCK_DATA[date] || { events: [], info_blocks: [] };
    }

    function renderTimetable(data, startMin, endMin, slotMin) {
        const events = data.events || [];
        const infoBlocks = data.info_blocks || [];

        const rowHeight  = parseInt(getComputedStyle(document.documentElement).getPropertyValue("--row-height")) || 60;
        const trackWidth = parseInt(getComputedStyle(document.documentElement).getPropertyValue("--track-width")) || 180;
        const rows = Math.max(1, Math.ceil((endMin - startMin) / slotMin));

        // --- Dynamic row heights: each slot expands to fit tallest Info Box at that slot ---
        const slotList = [];
        for (let r = 0; r < rows; r++) slotList.push(startMin + r * slotMin);

        // base row height from CSS
        const baseRowHeight = rowHeight;

        // Compute reserved height per slot from info blocks (max of all blocks at that slot)
        const reservedBySlot = {}; // {slotMinute: reservedPx}
        infoBlocks.forEach(block => {
            const pos = toMin(block.position || "00:00");
            if (pos < startMin || pos > endMin) return;
            const snapped = roundDownToSlot(pos, slotMin);
            const h = parseInt(block.height) || 60;
            reservedBySlot[snapped] = Math.max(reservedBySlot[snapped] || 0, h);
        });

        // Build slot heights and cumulative tops
        const slotHeights = {};      // {slotMinute: heightPx}
        const cumulativeTop = {};    // {slotMinute: topPx from grid start}
        let acc = 0;
        slotList.forEach((min, idx) => {
            const reserved = reservedBySlot[min] || 0;
            const h = Math.max(baseRowHeight, reserved);   // use max of base height or reserved height
            slotHeights[min] = h;
            cumulativeTop[min] = acc;
            acc += h;
        });

        // Helper to get top for a snapped start minute
        function topForSlot(snappedMin) {
            return cumulativeTop[snappedMin] || 0;
        }


        // TIME SLOTS (left column)
        const timeSlots = root.querySelector(".ctt-time-slots");
        timeSlots.innerHTML = "";

        // Build label replacements from info blocks USING SNAPPED ROWS
        // But don't affect grid positioning
        const timeLabelMap = {};
        infoBlocks.forEach(block => {
            if (block.time_label && block.time_label.trim()) {
                const pos = toMin(block.position);
                const snapped = roundDownToSlot(pos, slotMin);
                const slotIndex = Math.floor((snapped - startMin) / slotMin);
                if (slotIndex >= 0 && slotIndex < rows) {
                    timeLabelMap[slotIndex] = block.time_label.trim();
                }
            }
        });

        for (let r = 0; r < rows; r++) {
            const tMin = startMin + r * slotMin;
            const slot = document.createElement("div");
            slot.className = "ctt-time-slot";
            slot.style.height = (slotHeights[tMin] || rowHeight) + "px";
            if (timeLabelMap[r]) {
                slot.textContent = timeLabelMap[r];
                slot.classList.add("has-info-label");
            } else {
                const h = Math.floor(tMin / 60).toString().padStart(2, "0");
                const m = (tMin % 60).toString().padStart(2, "0");
                slot.textContent = `${h}:${m}`;
            }
            timeSlots.appendChild(slot);
        }

        // TRACK GRID
        const trackGrid = root.querySelector(".ctt-track-grid");
        trackGrid.innerHTML = "";

        // Calculate total dynamic height (sum of all slot heights)
        const totalDynamicHeight = acc; // acc is the accumulated height from slot calculation
        trackGrid.style.minHeight = `${totalDynamicHeight}px`;
        trackGrid.style.position = "relative";

        // Row guides - each guide should match its slot's dynamic height
        const guides = document.createElement("div");
        guides.className = "ctt-row-guides";
        guides.style.height = `${totalDynamicHeight}px`;
        for (let r = 0; r < rows; r++) {
            const tMin = startMin + r * slotMin;
            const guide = document.createElement("div");
            guide.className = "ctt-row-guide";
            guide.style.height = `${slotHeights[tMin] || rowHeight}px`;
            guides.appendChild(guide);
        }
        trackGrid.appendChild(guides);

        // INFO BLOCKS - INDEPENDENT POSITIONING (not affected by grid)
        // These float on top and don't interfere with event positioning
        infoBlocks.forEach(block => {
            const pos = toMin(block.position || "00:00");
            if (pos < startMin || pos > endMin) return;

            const snapped = roundDownToSlot(pos, slotMin);
            const topPos = topForSlot(snapped);

            const trackStart = parseInt(block.track_start) || 1;
            const trackEnd   = parseInt(block.track_end)   || 6;
            const leftPos  = (trackStart - 1) * trackWidth;
            const widthSpan= (trackEnd - trackStart + 1) * trackWidth;

            // Use custom height from meta field
            const customHeight = parseInt(block.height) || 60;

            const infoDiv = document.createElement("div");
            infoDiv.className = "ctt-info-block";
            infoDiv.style.position = "absolute";
            infoDiv.style.top = `${topPos}px`;
            infoDiv.style.left = `${leftPos}px`;
            infoDiv.style.width = `${widthSpan - 8}px`;
            infoDiv.style.height = `${customHeight}px`; // Custom height
            infoDiv.style.backgroundColor = block.bg_color || "#f0f4f8";
            infoDiv.style.color = block.text_color || "#1e293b";
            infoDiv.style.zIndex = "1000"; // HIGH z-index - Info Boxes appear above events
            infoDiv.style.pointerEvents = "none"; // Can't interact with info blocks

            let innerHTML = block.title || "";
            if (block.content && block.content.trim()) {
                innerHTML += `<div class="ctt-info-block-content">${block.content}</div>`;
            }
            infoDiv.innerHTML = innerHTML;
            trackGrid.appendChild(infoDiv);
        });

        // EVENTS - These render on top of info blocks
        events.forEach(ev => {
            const trackNum  = parseInt(ev.track || 1);
            const trackSpan = Math.min(parseInt(ev.track_span || 1), 7 - trackNum);

            const s = clamp(toMin(ev.start || "00:00"), startMin, endMin);
            const e = clamp(toMin(ev.end   || "00:00"), startMin, endMin);
            const dur = Math.max(e - s, slotMin);

            const snappedStart = roundDownToSlot(s, slotMin);
            const baseTopPos = topForSlot(snappedStart);
            const withinSlotOffset = (reservedBySlot[snappedStart] || 0); // ensure below Info Box for same slot
            const topPos = baseTopPos + withinSlotOffset;
            // compute height by summing dynamic slot heights across duration
            const endSnappedExclusive = roundUpToSlot(e, slotMin);
            let height = 0;
            for (let t = snappedStart; t < endSnappedExclusive; t += slotMin) {
                height += (slotHeights[t] || rowHeight);
            }
            // subtract reserved area from the first slot so card sits below info area
            height -= (reservedBySlot[snappedStart] || 0);


            const hasOrgImg = !!(ev.organizer && ev.organizer.img && ev.organizer.img.url);
            const avatarHTML = hasOrgImg ? `<img class="ctt-card__avatar" src="${ev.organizer.img.url}" alt="Organizer">` : "";

            const card = document.createElement("button");
            card.type = "button";
            card.className = "ctt-card" + (hasOrgImg ? " has-org" : "");
            card.style.position = "absolute";
            card.style.top = `${topPos}px`;
            card.style.height = `${height - 4}px`;
            card.style.left = `${(trackNum - 1) * trackWidth + 4}px`;
            card.style.width = `${trackSpan * trackWidth - 8}px`;

            const timeDisplay = ev.time_label || `${fmtTime(ev.start)}–${fmtTime(ev.end)}`;
            const roomText = ev.room ? ` • ${ev.room}` : "";
            const speakerText = ev.speaker ? `<div class="ctt-card__speaker">${ev.speaker}</div>` : "";

            card.innerHTML = `${avatarHTML}<div class="ctt-card__title">${ev.title || ""}</div><div class="ctt-card__meta">${timeDisplay}${roomText}</div>${speakerText}`;
            card.addEventListener("click", () => openModal(ev));
            trackGrid.appendChild(card);
        });
    }

    function computeWindowFromEvents(data) {
        const events = data.events || [];
        const infoBlocks = data.info_blocks || [];
        const slotMin = getSlotMin();

        // Include BOTH events AND info blocks in window calculation
        // This ensures info blocks are visible even if before first event
        if (events.length || infoBlocks.length) {
            let minStart = Infinity;
            let maxEnd = -Infinity;

            events.forEach(event => {
                const s = toMin(event.start || "00:00");
                const e = toMin(event.end || "00:00");
                if (!isNaN(s)) minStart = Math.min(minStart, s);
                if (!isNaN(e)) maxEnd   = Math.max(maxEnd, e);
            });

            infoBlocks.forEach(block => {
                const pos = toMin(block.position || "00:00");
                if (!isNaN(pos)) {
                    minStart = Math.min(minStart, pos);
                    // Info blocks contribute to the timeline but don't extend maxEnd
                    // unless they're the only content
                    if (events.length === 0) {
                        maxEnd = Math.max(maxEnd, pos + slotMin);
                    }
                }
            });

            if (isFinite(minStart) && isFinite(maxEnd) && maxEnd > minStart) {
                const startMin = Math.max(0, roundDownToSlot(minStart, slotMin));
                const endMin   = roundUpToSlot(maxEnd + 1, slotMin);
                return { startMin, endMin, slotMin };
            }
        }
        return computeShortcodeWindow(slotMin);
    }

    function computeShortcodeWindow(slotMin) {
        let startMin = toMin(root.dataset.start || "09:00");
        let endMin   = toMin(root.dataset.end   || "18:00");
        if (endMin <= startMin) endMin = startMin + 8 * 60;
        startMin = roundDownToSlot(startMin, slotMin);
        endMin   = roundUpToSlot(endMin + 1, slotMin);
        return { startMin, endMin, slotMin };
    }

    function setDate(val) {
        if (val && /^\d{4}-\d{2}-\d{2}$/.test(val) && dateInput) dateInput.value = val;
    }

    async function init() {
        const dates = await fetchDates();
        let chosen = root.dataset.initialDate;
        if (!chosen || (dates.length && !dates.includes(chosen))) chosen = dates[0] || "2026-05-12";
        setDate(chosen);

        const first = await fetchEvents(dateInput ? dateInput.value : chosen);
        const win = computeWindowFromEvents(first);
        renderTimetable(first, win.startMin, win.endMin, win.slotMin);

        if (dateInput) {
            dateInput.addEventListener("change", async (e) => {
                const data = await fetchEvents(e.target.value);
                const w = computeWindowFromEvents(data);
                renderTimetable(data, w.startMin, w.endMin, w.slotMin);
            });
        }
    }

    init();
})();
