(function () {
    const modal = document.getElementById("registerModal");
    const openBtn = document.getElementById("openRegister");
    const closeBtn = document.getElementById("closeRegister");
    const dateInput = document.getElementById("dateInput");
    const prevDate = document.getElementById("prevDate");
    const nextDate = document.getElementById("nextDate");
    const filterForm = document.getElementById("filterForm");
    const areaSelect = document.getElementById("areaSelect");
    const scheduleViewport = document.getElementById("scheduleViewport");
    const scheduleZoomBtn = document.getElementById("toggleScheduleZoom");
    const bookingDetailModal = document.getElementById("bookingDetailModal");
    const closeBookingDetail = document.getElementById("closeBookingDetail");
    const registerStartDate = document.getElementById("registerStartDate");
    const registerStartTime = document.getElementById("registerStartTime");
    const registerEndDate = document.getElementById("registerEndDate");
    const registerEndTime = document.getElementById("registerEndTime");
    const registerMeetingRoom = document.getElementById("registerMeetingRoom");
    const bookingForm = modal ? modal.querySelector("form") : null;
    const statusMap = {
        PENDING: "Chờ duyệt",
        APPROVED: "Đã duyệt",
        REJECTED: "Từ chối",
        CANCELLED: "Đã hủy",
    };

    const hoverCard = document.createElement("div");
    hoverCard.className = "meeting-hover-card";
    document.body.appendChild(hoverCard);

    function parseDate(value) {
        if (!value) {
            return null;
        }

        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function formatDate(date) {
        const d = String(date.getDate()).padStart(2, "0");
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const y = date.getFullYear();
        return `${y}-${m}-${d}`;
    }

    function updateScheduleZoomButton(isZoomed) {
        if (!scheduleZoomBtn) {
            return;
        }

        scheduleZoomBtn.textContent = isZoomed ? "🗕" : "⛶ ";
        scheduleZoomBtn.setAttribute("aria-pressed", isZoomed ? "true" : "false");
    }

    function syncScheduleZoomState() {
        const isZoomed = document.fullscreenElement === scheduleViewport;
        updateScheduleZoomButton(isZoomed);
        window.requestAnimationFrame(syncSlotWidth);
    }

    function setScheduleZoom(isZoomed) {
        if (!scheduleViewport) {
            return;
        }

        if (isZoomed) {
            if (document.fullscreenElement !== scheduleViewport) {
                scheduleViewport.requestFullscreen().catch(function () {
                    updateScheduleZoomButton(false);
                });
            }

            return;
        }

        if (document.fullscreenElement) {
            document.exitFullscreen().catch(function () {
                syncScheduleZoomState();
            });
        }
    }

    function buildDateTime(dateText, timeText) {
        if (!dateText || !timeText) {
            return null;
        }

        const value = new Date(`${dateText}T${timeText}:00`);
        return Number.isNaN(value.getTime()) ? null : value;
    }

    function validateBookingStartTime(showAlert) {
        if (!registerStartDate || !registerStartTime) {
            return true;
        }

        const startAt = buildDateTime(registerStartDate.value, registerStartTime.value);
        const message = "Không thể đăng ký lịch họp trước thời gian hiện tại.";
        const isPast = Boolean(startAt && startAt < new Date());

        registerStartTime.setCustomValidity(isPast ? message : "");

        if (showAlert && isPast) {
            window.alert(message);
            registerStartTime.reportValidity();
        }

        return !isPast;
    }

    function autoDismissNotices() {
        const notices = document.querySelectorAll(".notice.success, .notice.danger");

        notices.forEach(function (notice) {
            window.setTimeout(function () {
                notice.classList.add("is-hiding");

                window.setTimeout(function () {
                    notice.remove();
                }, 350);
            }, 5000);
        });
    }

    function shiftDate(delta) {
        if (!dateInput || !filterForm) {
            return;
        }

        const baseDate = parseDate(dateInput.value) || new Date();
        baseDate.setDate(baseDate.getDate() + delta);
        dateInput.value = formatDate(baseDate);
        filterForm.submit();
    }

    if (openBtn && modal) {
        openBtn.addEventListener("click", function () {
            modal.classList.add("open");
        });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener("click", function () {
            modal.classList.remove("open");
        });
    }

    if (modal) {
        modal.addEventListener("click", function (event) {
            if (event.target === modal) {
                modal.classList.remove("open");
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal) {
            modal.classList.remove("open");
        }
    });

    if (prevDate) {
        prevDate.addEventListener("click", function () {
            shiftDate(-1);
        });
    }

    if (nextDate) {
        nextDate.addEventListener("click", function () {
            shiftDate(1);
        });
    }

    if (areaSelect && filterForm) {
        areaSelect.addEventListener("change", function () {
            filterForm.submit();
        });
    }

    if (dateInput && filterForm) {
        dateInput.addEventListener("change", function () {
            filterForm.submit();
        });
    }

    autoDismissNotices();

    if (scheduleZoomBtn) {
        syncScheduleZoomState();

        scheduleZoomBtn.addEventListener("click", function () {
            setScheduleZoom(document.fullscreenElement !== scheduleViewport);
        });

        document.addEventListener("fullscreenchange", syncScheduleZoomState);
    }

    [registerStartDate, registerStartTime].forEach(function (field) {
        if (field) {
            field.addEventListener("change", function () {
                validateBookingStartTime(false);
            });
        }
    });

    if (bookingForm) {
        bookingForm.addEventListener("submit", function (event) {
            if (!validateBookingStartTime(true)) {
                event.preventDefault();
            }
        });
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value || "-";
        }
    }

    function toTimeText(hour) {
        return `${String(hour).padStart(2, "0")}:00`;
    }

    function escapeHtml(text) {
        const temp = document.createElement("div");
        temp.textContent = text || "-";
        return temp.innerHTML;
    }

    function moveHoverCard(event) {
        const offset = 14;
        const cardWidth = 320;
        const cardHeight = 200;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let left = event.clientX + offset;
        let top = event.clientY + offset;

        if (left + cardWidth > viewportWidth - 8) {
            left = event.clientX - cardWidth - offset;
        }

        if (top + cardHeight > viewportHeight - 8) {
            top = event.clientY - cardHeight - offset;
        }

        hoverCard.style.left = `${Math.max(8, left)}px`;
        hoverCard.style.top = `${Math.max(8, top)}px`;
    }

    function showHoverCard(item, event) {
        const status = item.dataset.status || "-";
        const readableStatus = statusMap[status] || status;
        hoverCard.innerHTML = `
            <div class="hover-title">${escapeHtml(item.dataset.title || "-")}</div>
            <div class="hover-row"><span>Thời gian:</span><strong>${escapeHtml(item.dataset.time || "-")}</strong></div>
            <div class="hover-row"><span>Phòng:</span><strong>${escapeHtml(item.dataset.room || "-")}</strong></div>
            <div class="hover-row"><span>Trạng thái:</span><strong>${escapeHtml(readableStatus)}</strong></div>
            <div class="hover-row"><span>Người đăng ký:</span><strong>${escapeHtml(item.dataset.organizer || "-")}</strong></div>
        `;
        hoverCard.classList.add("show");
        moveHoverCard(event);
    }

    function hideHoverCard() {
        hoverCard.classList.remove("show");
    }

    document.querySelectorAll(".meeting").forEach(function (item) {
        item.addEventListener("mouseenter", function (event) {
            showHoverCard(item, event);
        });

        item.addEventListener("mousemove", function (event) {
            moveHoverCard(event);
        });

        item.addEventListener("mouseleave", function () {
            hideHoverCard();
        });

        item.addEventListener("click", function () {
            hideHoverCard();
            setText("detailTitle", item.dataset.title);
            setText("detailTime", item.dataset.time);
            setText("detailRoom", item.dataset.room);
            setText("detailStatus", item.dataset.status);
            setText("detailOrganizer", item.dataset.organizer);
            setText("detailInternal", item.dataset.internal);
            setText("detailExternal", item.dataset.external);
            setText("detailNotes", item.dataset.notes);

            const linkEl = document.getElementById("detailLink");
            if (linkEl) {
                if (item.dataset.link) {
                    linkEl.innerHTML = "";
                    const a = document.createElement("a");
                    a.href = item.dataset.link;
                    a.textContent = item.dataset.link;
                    a.target = "_blank";
                    a.rel = "noopener noreferrer";
                    linkEl.appendChild(a);
                } else {
                    linkEl.textContent = "-";
                }
            }

            if (bookingDetailModal) {
                bookingDetailModal.classList.add("open");
            }

            const cancelWrap = document.getElementById("cancelBookingWrap");
            const cancelForm = document.getElementById("cancelBookingForm");
            if (cancelWrap && cancelForm) {
                const cancelUrl = item.dataset.cancelUrl;
                if (cancelUrl) {
                    cancelForm.action = cancelUrl;
                    cancelWrap.style.display = "";
                } else {
                    cancelWrap.style.display = "none";
                }
            }
        });
    });

    document.querySelectorAll(".grid-row .slot").forEach(function (slot) {
        slot.addEventListener("click", function () {
            const row = slot.closest(".grid-row");
            const roomId = row ? row.dataset.roomId : "";
            const startHour = Number(slot.dataset.startHour);
            const endHour = Number(slot.dataset.endHour);
            const selectedDate = dateInput ? dateInput.value : "";

            if (registerMeetingRoom && roomId) {
                registerMeetingRoom.value = roomId;
            }

            if (registerStartDate && selectedDate) {
                registerStartDate.value = selectedDate;
            }

            if (registerEndDate && selectedDate) {
                registerEndDate.value = selectedDate;
            }

            if (registerStartTime && Number.isFinite(startHour)) {
                registerStartTime.value = toTimeText(startHour);
            }

            if (registerEndTime && Number.isFinite(endHour)) {
                registerEndTime.value = toTimeText(endHour);
            }

            validateBookingStartTime(false);

            if (modal) {
                modal.classList.add("open");
            }
        });
    });

    if (closeBookingDetail && bookingDetailModal) {
        closeBookingDetail.addEventListener("click", function () {
            bookingDetailModal.classList.remove("open");
        });
    }

    if (bookingDetailModal) {
        bookingDetailModal.addEventListener("click", function (event) {
            if (event.target === bookingDetailModal) {
                bookingDetailModal.classList.remove("open");
            }
        });
    }

    document.addEventListener("scroll", hideHoverCard, true);

    function syncSlotWidth() {
        const sampleSlot = document.querySelector(".grid-row .slot");
        if (!sampleSlot) {
            return;
        }

        const width = sampleSlot.getBoundingClientRect().width;
        if (width > 0) {
            document.documentElement.style.setProperty("--slot-w", `${width}px`);
        }
    }

    syncSlotWidth();
    window.addEventListener("resize", syncSlotWidth);

    const nowLine = document.querySelector(".timeline-now");
    if (nowLine) {
        function updateNowLine() {
            const now = new Date();
            const minutesFrom8 = (now.getHours() - 8) * 60 + now.getMinutes();
            const clamped = Math.max(0, Math.min(600, minutesFrom8));
            nowLine.style.setProperty("--left-minutes", clamped);
            nowLine.style.display = minutesFrom8 >= 0 && minutesFrom8 <= 600 ? "" : "none";
        }

        updateNowLine();
        setInterval(updateNowLine, 60000);
    }
})();
