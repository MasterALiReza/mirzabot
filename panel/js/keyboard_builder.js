document.addEventListener("DOMContentLoaded", () => {
    const telegramBoard = document.getElementById("telegram-board");
    const unusedKeysContainer = document.getElementById("unused-keys");
    const saveBtn = document.getElementById("save-keyboard-btn");
    
    const data = window.KEYBOARD_INITIAL_DATA || { keylist: [], userlist: [], text: {} };
    const textDict = data.text;
    
    let rowSortables = [];

    // Initialize layout
    renderUnusedKeys(data.keylist);
    renderActiveKeyboard(data.userlist);
    ensureEmptyRowAtBottom();
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
        
        // Mobile tap to show remove btn
        btn.onclick = (e) => {
            document.querySelectorAll('.kb-btn.show-actions').forEach(b => {
                if (b !== btn) b.classList.remove('show-actions');
            });
            btn.classList.toggle('show-actions');
        };
        
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

        // Clean up empty-row class and add slots
        telegramBoard.querySelectorAll(".telegram-row").forEach(row => {
            const keys = row.querySelectorAll('.kb-btn');
            
            // Remove existing add-slots
            row.querySelectorAll('.add-slot').forEach(el => el.remove());
            
            if (keys.length > 0) {
                row.classList.remove("empty-row");
                // Add slot if row has exactly 1 button
                if (keys.length === 1) {
                    const addSlot = document.createElement("div");
                    addSlot.className = "add-slot";
                    addSlot.innerHTML = "➕ افزودن";
                    addSlot.onclick = (e) => {
                        e.stopPropagation();
                        openAddPopup(row);
                    };
                    row.appendChild(addSlot);
                }
            }
        });
    }

    let currentRowForAdd = null;
    window.openAddPopup = function(row) {
        currentRowForAdd = row;
        const addBtnList = document.getElementById("addBtnList");
        addBtnList.innerHTML = "";
        
        const unusedBtns = unusedKeysContainer.querySelectorAll('.kb-btn');
        if (unusedBtns.length === 0) {
            addBtnList.innerHTML = "<p style='color:#64748b; font-size:14px; width:100%;'>دکمه‌ای برای افزودن وجود ندارد.</p>";
        } else {
            unusedBtns.forEach(btn => {
                const clone = btn.cloneNode(true);
                const rmBtn = clone.querySelector('.remove-btn');
                if (rmBtn) rmBtn.remove();
                
                clone.style.cursor = "pointer";
                clone.style.flex = "0 0 auto";
                clone.style.minWidth = "120px";
                clone.style.background = "#f8fafc";
                clone.style.border = "1px dashed #cbd5e1";
                clone.style.color = "#64748b";
                
                clone.onclick = () => {
                    // Reset styles when added back to row
                    btn.style.flex = "";
                    btn.style.minWidth = "";
                    btn.style.background = "";
                    btn.style.border = "";
                    btn.style.color = "";
                    
                    row.appendChild(btn); 
                    document.getElementById('addBtnModalVeil').style.display = 'none';
                    ensureEmptyRowAtBottom();
                };
                addBtnList.appendChild(clone);
            });
        }
        
        document.getElementById('addBtnModalVeil').style.display = 'flex';
    };

    function initSortables() {
        const sortableOptions = {
            group: "shared",
            animation: 150,
            ghostClass: "sortable-ghost",
            delay: window.innerWidth <= 600 ? 150 : 0, // Delay to prevent accidental drags when scrolling on mobile
            delayOnTouchOnly: true,
            fallbackTolerance: 5,
            forceFallback: true, // Fixes iOS and Safari dragging issues
            fallbackClass: "sortable-fallback",
            fallbackOnBody: false, // Prevents offset issues on scrolled containers in mobile
            filter: ".add-inline-btn, .remove-btn, .add-slot", // Prevent dragging these
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
                delay: window.innerWidth <= 600 ? 150 : 0,
                delayOnTouchOnly: true,
                fallbackTolerance: 5,
                forceFallback: true,
                fallbackClass: "sortable-fallback",
                fallbackOnBody: false,
                filter: ".add-inline-btn, .remove-btn, .add-slot",
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
