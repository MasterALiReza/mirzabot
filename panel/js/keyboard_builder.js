document.addEventListener("DOMContentLoaded", () => {
    const telegramBoard = document.getElementById("telegram-board");
    const unusedKeysContainer = document.getElementById("unused-keys");
    const saveBtn = document.getElementById("save-keyboard-btn");
    
    // Modal Elements
    const addModal = document.getElementById("add-btn-modal");
    const closeModalBtn = document.getElementById("close-modal-btn");
    const modalUnusedList = document.getElementById("modal-unused-list");
    
    // Load data from global variable injected by PHP
    const data = window.KEYBOARD_INITIAL_DATA || { keylist: [], userlist: [], text: {} };
    const textDict = data.text;
    
    let rowSortables = [];
    let currentRowForAdd = null; // Track which row requested the + button

    // Initialize layout
    renderUnusedKeys(data.keylist);
    renderActiveKeyboard(data.userlist);
    ensureEmptyRowAtBottom();
    refreshAddInlineButtons();
    initSortables();

    function createButtonElement(keyName, isActive = false) {
        const btn = document.createElement("div");
        btn.className = "kb-btn telegram-btn";
        btn.dataset.key = keyName;
        // The span helps with text overflow
        btn.innerHTML = `<span>${textDict[keyName] || keyName}</span>`;
        
        // Add remove button
        const removeBtn = document.createElement("div");
        removeBtn.className = "remove-btn";
        removeBtn.innerHTML = "✖";
        removeBtn.onclick = (e) => {
            e.stopPropagation();
            moveToUnused(btn);
        };
        btn.appendChild(removeBtn);
        
        return btn;
    }

    function createRowElement() {
        const row = document.createElement("div");
        row.className = "telegram-row";
        return row;
    }

    function renderUnusedKeys(keylist) {
        unusedKeysContainer.innerHTML = "";
        keylist.forEach(item => {
            if (item && item.length > 0 && item[0].text) {
                unusedKeysContainer.appendChild(createButtonElement(item[0].text, false));
            }
        });
    }

    function renderActiveKeyboard(userlist) {
        telegramBoard.innerHTML = "";
        userlist.forEach(rowArr => {
            const rowEl = createRowElement();
            let hasItems = false;
            rowArr.forEach(item => {
                if (item.text) {
                    rowEl.appendChild(createButtonElement(item.text, true));
                    hasItems = true;
                }
            });
            if (hasItems) {
                telegramBoard.appendChild(rowEl);
            }
        });
    }

    function moveToUnused(btn) {
        unusedKeysContainer.appendChild(btn);
        ensureEmptyRowAtBottom();
        refreshAddInlineButtons();
    }

    function openAddModal(targetRow) {
        currentRowForAdd = targetRow;
        modalUnusedList.innerHTML = "";
        
        const unusedBtns = Array.from(unusedKeysContainer.children);
        if (unusedBtns.length === 0) {
            modalUnusedList.innerHTML = "<div style='text-align:center; padding: 20px; color: #64748b;'>هیچ دکمه غیرفعالی وجود ندارد.</div>";
        } else {
            unusedBtns.forEach(btn => {
                const keyName = btn.dataset.key;
                const modalItem = document.createElement("div");
                modalItem.className = "modal-btn";
                modalItem.innerText = textDict[keyName] || keyName;
                modalItem.onclick = () => {
                    targetRow.insertBefore(btn, targetRow.querySelector('.add-inline-btn'));
                    closeModal();
                    ensureEmptyRowAtBottom();
                    refreshAddInlineButtons();
                };
                modalUnusedList.appendChild(modalItem);
            });
        }
        
        addModal.classList.add("show");
    }

    function closeModal() {
        addModal.classList.remove("show");
        currentRowForAdd = null;
    }

    closeModalBtn.onclick = closeModal;
    addModal.onclick = (e) => {
        if (e.target === addModal) closeModal();
    };

    function refreshAddInlineButtons() {
        // Remove existing add buttons
        document.querySelectorAll(".add-inline-btn").forEach(btn => btn.remove());
        
        // Add new add buttons to rows with 1 item
        document.querySelectorAll(".telegram-row").forEach(row => {
            // Count actual key buttons
            const keyCount = row.querySelectorAll('.kb-btn').length;
            if (keyCount === 1 && !row.classList.contains('empty-row')) {
                const addBtn = document.createElement("div");
                addBtn.className = "add-inline-btn";
                addBtn.innerHTML = "➕";
                addBtn.onclick = () => openAddModal(row);
                row.appendChild(addBtn);
            }
        });
    }

    function ensureEmptyRowAtBottom() {
        // Check if the last row is empty
        const rows = telegramBoard.querySelectorAll(".telegram-row");
        let lastRow = rows.length > 0 ? rows[rows.length - 1] : null;
        
        // Remove empty rows that are NOT the last row
        rows.forEach(row => {
            const keyCount = row.querySelectorAll('.kb-btn').length;
            if (keyCount === 0 && row !== lastRow) {
                if (row.sortableInstance && rowSortables.includes(row.sortableInstance)) {
                    // Cleanup Sortable instance
                    row.sortableInstance.destroy();
                    rowSortables = rowSortables.filter(s => s !== row.sortableInstance);
                }
                row.remove();
            }
        });

        // Re-evaluate last row
        const updatedRows = telegramBoard.querySelectorAll(".telegram-row");
        lastRow = updatedRows.length > 0 ? updatedRows[updatedRows.length - 1] : null;

        if (!lastRow || lastRow.querySelectorAll('.kb-btn').length > 0) {
            // Last row has items, create a new empty row
            const newEmptyRow = createRowElement();
            newEmptyRow.classList.add("empty-row");
            telegramBoard.appendChild(newEmptyRow);
            initRowSortable(newEmptyRow);
        } else {
            // Last row is already empty
            lastRow.classList.add("empty-row");
        }

        // Clean up empty-row class from non-empty rows
        telegramBoard.querySelectorAll(".telegram-row").forEach(row => {
            if (row.querySelectorAll('.kb-btn').length > 0) {
                row.classList.remove("empty-row");
            }
        });
        
        refreshAddInlineButtons();
    }

    function initSortables() {
        const sortableOptions = {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            delay: 150, // Delay to prevent accidental drags when scrolling on mobile
            delayOnTouchOnly: true,
            forceFallback: true, // Fixes iOS and Safari dragging issues
            fallbackClass: "sortable-fallback",
            fallbackOnBody: true, // Fixes drag offset on mobile/scrolled containers
            filter: ".add-inline-btn, .remove-btn", // Prevent dragging these
            onEnd: ensureEmptyRowAtBottom
        };

        // Sortable for unused keys area
        new Sortable(unusedKeysContainer, sortableOptions);

        // Initialize sortable for existing rows
        document.querySelectorAll(".telegram-row").forEach(row => {
            initRowSortable(row, sortableOptions);
        });
    }

    function initRowSortable(rowElement, options) {
        if (!options) {
            options = {
                group: "shared",
                animation: 150,
                ghostClass: "sortable-ghost",
                delay: 150,
                delayOnTouchOnly: true,
                forceFallback: true,
                fallbackClass: "sortable-fallback",
                fallbackOnBody: true,
                filter: ".add-inline-btn, .remove-btn",
                onEnd: ensureEmptyRowAtBottom
            };
        }
        const sortable = new Sortable(rowElement, options);
        rowElement.sortableInstance = sortable;
        rowSortables.push(sortable);
    }

    saveBtn.addEventListener("click", async () => {
        saveBtn.innerText = "در حال ذخیره...";
        saveBtn.disabled = true;

        const keyboardData = [];
        
        document.querySelectorAll(".telegram-row").forEach(row => {
            const rowData = [];
            row.querySelectorAll(".kb-btn").forEach(btn => {
                if (btn.dataset.key) {
                    rowData.push({ text: btn.dataset.key });
                }
            });
            // Only add non-empty rows
            if (rowData.length > 0) {
                keyboardData.push(rowData);
            }
        });

        try {
            const response = await fetch("", { // Post to same page (keyboard.php)
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(keyboardData)
            });
            
            if (response.ok) {
                // Flash success
                saveBtn.style.background = "#059669";
                saveBtn.innerText = "ذخیره شد!";
                setTimeout(() => {
                    saveBtn.style.background = "";
                    saveBtn.innerText = "ذخیره تغییرات";
                    saveBtn.disabled = false;
                }, 2000);
            } else {
                throw new Error("Server response not OK");
            }
        } catch (error) {
            console.error(error);
            alert("خطا در ذخیره‌سازی");
            saveBtn.innerText = "ذخیره تغییرات";
            saveBtn.disabled = false;
        }
    });
});
